@extends('layouts.vertical', ['title' => 'Title Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        .card.title-master-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(44, 110, 213, 0.06);
        }
        .card.title-master-card .card-body {
            padding: 1.25rem 1.5rem;
        }
        .title-master-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }
        .title-master-toolbar .btn {
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
            border-radius: 6px;
        }
        .title-master-toolbar .btn i {
            font-size: 0.75rem;
        }
        .table-responsive {
            position: relative;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            max-height: 600px;
            overflow-y: auto;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            background-color: white;
        }

        #title-master-table thead th {
            vertical-align: middle !important;
        }
        .table-responsive thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%) !important;
            color: white;
            z-index: 10;
            padding: 6px 8px;
            font-weight: 600;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            font-size: 10px;
            letter-spacing: 0.2px;
            text-transform: uppercase;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .table-responsive thead th:hover {
            background: linear-gradient(135deg, #1a56b7 0%, #0a3d8f 100%) !important;
        }

        .table-responsive thead input {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 4px 6px;
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

        #title-master-table tbody tr {
            align-items: center;
        }
        #title-master-table tbody td {
            padding: 8px 12px;
            vertical-align: middle !important;
            border-bottom: 1px solid #edf2f9;
            font-size: 12px;
            line-height: 1.35;
            color: #475569;
        }
        /* Widen SKU column (4th column) */
        #title-master-table thead th:nth-child(4),
        #title-master-table tbody td:nth-child(4) {
            min-width: 180px;
            width: 180px;
            white-space: nowrap;
        }
        #title-master-table .table-img-cell {
            width: 48px;
            text-align: center;
        }
        #title-master-table tbody td img {
            width: 36px;
            height: 36px;
            object-fit: cover;
            border-radius: 4px;
            vertical-align: middle;
            display: inline-block;
        }

        .table-responsive tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .table-responsive tbody tr:hover {
            background-color: #e8f0fe;
        }

        .title-text {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .title-indicator {
            display: inline-block;
            margin-right: 4px;
            font-size: 14px;
        }
        .title-indicator.success {
            color: #28a745;
            filter: drop-shadow(0 0 2px rgba(40, 167, 69, 0.3));
        }
        .title-indicator.danger {
            color: #dc3545;
            filter: drop-shadow(0 0 2px rgba(220, 53, 69, 0.3));
        }
        .excess-badge {
            display: inline-block;
            background-color: #dc3545;
            color: white;
            font-size: 11px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 12px;
            margin-right: 6px;
            letter-spacing: 0.5px;
        }
        .title-cell {
            cursor: help;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .title-cell:hover {
            overflow: visible;
            white-space: normal;
            word-wrap: break-word;
            background-color: #f8f9fa;
            position: relative;
            z-index: 100;
        }
        .info-icon {
            cursor: help;
            opacity: 0.85;
            font-size: 12px;
        }

        /* Action column: View + Edit side by side */
        .action-buttons-cell {
            white-space: nowrap;
            vertical-align: middle !important;
        }
        .action-buttons-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
        }
        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .marketplace-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            min-width: 120px;
        }

        /* Title Master: align status dots with buttons (same grid per row) */
        #title-master-table .marketplaces-150-cell,
        #title-master-table .marketplaces-100-cell,
        #title-master-table .marketplaces-80-cell {
            vertical-align: middle !important;
            padding: 10px 8px !important;
            min-width: 148px;
        }
        #title-master-table .marketplaces-dots-wrapper {
            width: 100%;
            margin-bottom: 12px;
            padding-bottom: 2px;
            box-sizing: border-box;
        }
        #title-master-table .marketplaces-150-cell .marketplaces-dots,
        #title-master-table .marketplaces-100-cell .marketplaces-dots,
        #title-master-table .marketplaces-80-cell .marketplaces-dots {
            display: grid;
            grid-template-columns: repeat(3, minmax(32px, 1fr));
            column-gap: 12px;
            row-gap: 0;
            align-items: center;
            justify-items: center;
            width: 100%;
            min-height: 16px;
        }
        #title-master-table .marketplaces-150-cell .marketplace-buttons,
        #title-master-table .marketplaces-100-cell .marketplace-buttons,
        #title-master-table .marketplaces-80-cell .marketplace-buttons {
            display: grid;
            grid-template-columns: repeat(3, minmax(32px, 1fr));
            column-gap: 12px;
            row-gap: 8px;
            align-items: center;
            justify-items: center;
            justify-content: center;
            min-width: 0;
            flex-wrap: nowrap;
            width: 100%;
        }
        #title-master-table .marketplaces-150-cell .mp-dot,
        #title-master-table .marketplaces-100-cell .mp-dot,
        #title-master-table .marketplaces-80-cell .mp-dot {
            flex-shrink: 0;
        }
        /* PLS label is wider than icon-only buttons — keep column alignment */
        #title-master-table .marketplaces-100-cell .marketplace-btn.btn-shopify-pls {
            width: auto;
            min-width: 36px;
            padding: 0 6px;
        }
        @media (max-width: 768px) {
            #title-master-table .marketplaces-150-cell .marketplaces-dots,
            #title-master-table .marketplaces-100-cell .marketplaces-dots,
            #title-master-table .marketplaces-80-cell .marketplaces-dots,
            #title-master-table .marketplaces-150-cell .marketplace-buttons,
            #title-master-table .marketplaces-100-cell .marketplace-buttons,
            #title-master-table .marketplaces-80-cell .marketplace-buttons {
                column-gap: 8px;
            }
            #title-master-table .marketplaces-150-cell,
            #title-master-table .marketplaces-100-cell,
            #title-master-table .marketplaces-80-cell {
                min-width: 0;
            }
        }

        .marketplace-btn {
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 4px;
            color: #fff;
            font-weight: 600;
            font-size: 11px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            padding: 0;
        }

        .marketplace-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.18);
        }

        .marketplace-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-amazon { background-color: #146eb4; }
        .btn-temu { background-color: #28a745; }
        .btn-reverb { background-color: #ffc107; color: #333; }
        .btn-wayfair { background-color: #dc3545; }
        .btn-walmart { background-color: #dc3545; }
        .btn-shopify-main { background-color: #198754; }
        .btn-shopify-pls { background-color: #6f42c1; }
        .btn-doba { background-color: #fd7e14; }
        .btn-ebay1 { background-color: #0d6efd; }
        .btn-ebay2 { background-color: #198754; }
        .btn-ebay3 { background-color: #fd7e14; }
        .btn-macy { background-color: #0d6efd; }
        .btn-faire { background-color: #6f42c1; }
        .mp-dot.ebay1 { color: #0d6efd; }
        .mp-dot.ebay2 { color: #198754; }
        .mp-dot.ebay3 { color: #fd7e14; }
        .mp-dot.walmart { color: #0071ce; }
        .mp-dot.macy { color: #0d6efd; }
        .mp-dot.faire { color: #6f42c1; }

        /* Tooltips use Bootstrap (container: body, top) — see initMarketplaceTooltips() */
        #title-master-table .marketplaces-cell,
        #title-master-table .marketplaces-100-cell,
        #title-master-table .marketplaces-80-cell {
            overflow: visible;
        }
        .action-btn i {
            font-size: 11px;
        }
        .view-btn {
            background: #17a2b8;
            color: white;
        }
        .view-btn:hover {
            background: #138496;
            color: white;
            box-shadow: 0 2px 6px rgba(23, 162, 184, 0.3);
        }
        .edit-btn {
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%);
            color: white;
        }
        .edit-btn:hover {
            background: linear-gradient(135deg, #1a56b7 0%, #0a3d8f 100%);
            color: white;
            box-shadow: 0 2px 6px rgba(44, 110, 213, 0.35);
        }
        .push-button-cell {
            vertical-align: middle !important;
            min-width: 95px;
        }
        .push-amazon-btn {
            width: 100%;
            background: #ff9900;
            color: #232f3e;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        .push-amazon-btn:hover {
            background: #e88b00;
            color: white;
            box-shadow: 0 2px 6px rgba(255, 153, 0, 0.35);
        }
        .push-amazon-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        @media (max-width: 768px) {
            .action-buttons-group { flex-direction: column; gap: 4px; }
            .push-button-cell { min-width: 85px; }
        }
        /* Marketplaces column: dot indicators (default; Title Master overrides with grid) */
        .marketplaces-cell { white-space: nowrap; vertical-align: middle !important; }
        .marketplaces-dots { display: flex; align-items: center; justify-content: center; gap: 6px; }
        .mp-dot {
            width: 12px; height: 12px; border-radius: 50%;
            border: 2px solid currentColor;
            background: transparent;
            transition: all 0.2s;
        }
        .mp-dot.success { background: currentColor; border-color: currentColor; }
        .mp-dot.failed { background: #dc3545; border-color: #dc3545; }
        .mp-dot.pending { background: transparent; }
        .mp-dot.loading { background: transparent; border-color: transparent; }
        .mp-dot.amazon { color: #2c6ed5; }
        .mp-dot.temu { color: #28a745; }
        .mp-dot.reverb { color: #ffc107; }
        .mp-dot.wayfair { color: #dc3545; }
        .mp-dot.shopify_main { color: #95bf47; }
        .mp-dot.shopify_pls { color: #2b6cb0; }
        .mp-dot.doba { color: #6f42c1; }
        .mp-dot[title] { cursor: help; }
        .btn-push-all {
            background: #ff9900 !important;
            color: #232f3e !important;
            font-weight: 600;
        }
        .btn-push-all:hover {
            background: #e88b00 !important;
            color: white !important;
        }

        #rainbow-loader {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .rainbow-loader .loading-text {
            margin-top: 20px;
            font-weight: bold;
            color: #2c6ed5;
        }

        .modal-header-gradient {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            border-bottom: 4px solid #4D55E6;
            color: white;
        }

        .char-counter.warning {
            color: #b8860b;
            font-weight: 600;
        }
        .char-counter {
            font-size: 11px;
            color: #6c757d;
            float: right;
        }

        .char-counter.error {
            color: #dc3545;
        }
        .char-counter.success {
            color: #198754;
            font-weight: 600;
        }

        .platform-selector-modal .platform-item {
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .platform-selector-modal .platform-item:hover {
            border-color: #2c6ed5;
            background-color: #f8f9fa;
        }

        .platform-selector-modal .platform-item.selected {
            border-color: #198754;
            background-color: #d1e7dd;
        }

        .platform-selector-modal .form-check-input:checked {
            background-color: #198754;
            border-color: #198754;
        }

        .platform-icon {
            font-size: 20px;
            margin-right: 10px;
        }

        .platform-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            margin-left: 10px;
        }

        .btn-ai-improve {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%) !important;
            color: #ffffff !important;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .btn-ai-improve:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(90, 103, 216, 0.5);
            background: linear-gradient(135deg, #4c51bf 0%, #553c9a 100%) !important;
            color: #ffffff !important;
        }
        .btn-ai-improve:disabled {
            opacity: 0.8;
            cursor: not-allowed;
            transform: none;
            color: #ffffff !important;
        }
        .btn-keep-title {
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 500;
        }
        .btn-keep-title:hover {
            background-color: #218838;
            color: white;
        }
        .btn-regen-titles {
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
        }
        .btn-regen-titles:hover {
            background-color: #5a6268;
            color: white;
        }
        .btn-cancel-ai {
            background: transparent;
            border: 1px solid #dc3545;
            color: #dc3545;
            border-radius: 6px;
            padding: 8px 16px;
        }
        .btn-cancel-ai:hover {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border-color: #dc3545;
        }
        .ai-title-score {
            font-size: 13px;
        }
        .ai-title-score:not(:empty) {
            display: inline-block;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            border: 1px solid #f59e0b;
            box-shadow: 0 1px 3px rgba(245, 158, 11, 0.3);
        }
        .ai-title100-score {
            font-size: 13px;
        }
        .ai-title100-score:not(:empty) {
            display: inline-block;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            border: 1px solid #f59e0b;
            box-shadow: 0 1px 3px rgba(245, 158, 11, 0.3);
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Title Master',
        'sub_title' => 'Manage Product Titles',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card title-master-card">
                <div class="card-body">
                    <div class="mb-3 title-master-toolbar">
                            <button id="addTitleBtn" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Title
                            </button>
                            <button id="exportBtn" class="btn btn-primary">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button id="importBtn" class="btn btn-info">
                                <i class="fas fa-upload"></i> Import
                            </button>
                            <button id="pushAllBtn" class="btn btn-push-all">
                                <i class="fas fa-cloud-upload-alt"></i> Push ALL to All Marketplaces
                            </button>
                            <button id="pushSelectedBtn" class="btn btn-secondary" style="display:none;">
                                <i class="fas fa-cloud-upload-alt"></i> Push Selected (<span id="pushSelectedCount">0</span>) to All
                            </button>
                            <button id="updateAmazonBtn" class="btn btn-warning" style="display:none;">
                                <i class="fas fa-sync"></i> Update Titles (<span id="selectedCount">0</span> selected)
                            </button>
                            <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display: none;">
                            <label class="small text-muted mb-0 ms-2 me-1">Per page</label>
                            <select id="perPageSelect" class="form-select form-select-sm" style="width:88px;display:inline-block;vertical-align:middle;">
                                <option value="50">50</option>
                                <option value="75" selected>75</option>
                                <option value="100">100</option>
                            </select>
                    </div>

                    <div class="table-responsive">
                        <table id="title-master-table" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" title="Select All">
                                    </th>
                                    <th>Images</th>
                                    <th>
                                        <div style="display: flex; align-items: center; gap: 10px;">
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
                                    <th>
                                        <div style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                                            <span>Title 150</span>
                                            <span id="title150MissingCount" class="text-danger" style="font-weight: bold;">(0 missing)</span>
                                            <span class="info-icon" title="✅ = within 150 chars | ❌ (+X) = exceeds by X chars">ⓘ</span>
                                        </div>
                                        <select id="filterTitle150" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                            <option value="exceeds">Exceeds 150 chars</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Title 100 <span id="title100MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterTitle100" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Title 80 <span id="title80MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterTitle80" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Title 60 <span id="title60MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterTitle60" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>ACTION</th>
                                    <th title="Amazon, Temu, Reverb">MARKETPLACES (150)</th>
                                    <th title="Shopify Main, Shopify PLS, Macy's (Title 60 push)">MARKETPLACES (100)</th>
                                    <th title="eBay 1 (AmarjitK), eBay 2 (ProLight), eBay 3 (KaneerKa)">MARKETPLACES (80)</th>
                                    <th>PUSH TO ALL</th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 px-1" id="tmPaginationWrap">
                        <div class="small text-muted" id="tmPageInfo"></div>
                        <nav><ul class="pagination pagination-sm mb-0" id="tmPagination"></ul></nav>
                    </div>

                    <div id="rainbow-loader" class="rainbow-loader">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="loading-text">Loading Title Master Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Title Modal -->
    <div class="modal fade" id="titleModal" tabindex="-1" aria-labelledby="titleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="titleModalLabel">
                        <i class="fas fa-edit me-2"></i><span id="modalTitle">Add Title</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="titleForm">
                        <input type="hidden" id="editSku" name="sku">
                        
                        <div class="mb-3">
                            <label for="selectSku" class="form-label">Select SKU <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectSku" name="sku" required>
                                <option value="">Choose SKU...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="title150" class="form-label">
                                Title 150 <span class="char-counter" id="counter150">0/150</span>
                            </label>
                            <textarea class="form-control" id="title150" name="title150" rows="3" maxlength="500" data-max-display="150"></textarea>
                        </div>

                        <div class="mb-3">
                            <button type="button" class="btn btn-ai-improve" id="aiImproveBtn" title="Generate Title 150 (120-150 chars) with AI and review in popup">
                                <i class="fas fa-magic"></i> Improve with AI
                            </button>
                        </div>

                        <div class="mb-3">
                            <label for="title100" class="form-label">
                                Title 100 <span class="char-counter" id="counter100">0/105</span>
                            </label>
                            <textarea class="form-control" id="title100" name="title100" rows="2" maxlength="105"></textarea>
                        </div>

                        <div class="mb-3">
                            <button type="button" class="btn btn-ai-improve" id="aiImproveBtn100" title="Generate Title 100 (90-105 chars, target 95-100) with AI and review in popup">
                                <i class="fas fa-magic"></i> Improve with AI
                            </button>
                        </div>

                        <div class="mb-3">
                            <label for="title80" class="form-label">
                                Title 80 <span class="char-counter" id="counter80">0/80</span>
                            </label>
                            <textarea class="form-control" id="title80" name="title80" rows="2" maxlength="80"></textarea>
                        </div>

                        <div class="mb-3">
                            <button type="button" class="btn btn-ai-improve" id="aiImproveBtn80" title="Generate Title 80 (75-85 chars) with AI for eBay">
                                <i class="fas fa-magic"></i> Improve with AI
                            </button>
                        </div>

                        <div class="mb-3">
                            <label for="title60" class="form-label">
                                Title 60 <span class="char-counter" id="counter60">0/60</span>
                            </label>
                            <textarea class="form-control" id="title60" name="title60" rows="2" maxlength="60"></textarea>
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-ai-improve" id="aiImproveBtn60" title="Generate Title 60 (55-60 chars) with AI for Macy's/Faire">
                                <i class="fas fa-magic"></i> Improve with AI
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveTitleBtn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Title Modal -->
    <div class="modal fade" id="viewTitleModal" tabindex="-1" aria-labelledby="viewTitleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="viewTitleModalLabel">
                        <i class="fas fa-eye me-2"></i>View Title Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Image</label>
                            <div id="viewImage" class="mt-2"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SKU</label>
                            <div class="form-control-plaintext" id="viewSku"></div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Parent</label>
                            <div class="form-control-plaintext" id="viewParent"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title 150</label>
                        <div class="form-control-plaintext border rounded p-2" id="viewTitle150" style="min-height: 60px; white-space: pre-wrap; word-wrap: break-word;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title 100</label>
                        <div class="form-control-plaintext border rounded p-2" id="viewTitle100" style="min-height: 50px; white-space: pre-wrap; word-wrap: break-word;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title 80</label>
                        <div class="form-control-plaintext border rounded p-2" id="viewTitle80" style="min-height: 50px; white-space: pre-wrap; word-wrap: break-word;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title 60</label>
                        <div class="form-control-plaintext border rounded p-2" id="viewTitle60" style="min-height: 50px; white-space: pre-wrap; word-wrap: break-word;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Generated Title 100 Preview Modal (4 options) -->
    <div class="modal fade" id="aiTitle100Modal" tabindex="-1" aria-labelledby="aiTitle100ModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title" id="aiTitle100ModalLabel" title="90-105 chars. Perfect (95-100), Slightly long (101-105), Good (90-94). Target: 100 chars.">
                        <i class="fas fa-magic me-2"></i>AI Generated Titles (90-105 chars)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="aiTitle100Warning" class="alert alert-warning mb-3 d-none" role="alert"></div>
                    <div id="aiTitle100Option1" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 1:</div>
                        <p class="mb-2 ai-title100-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title100-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char100-badge badge">0/105 chars</span>
                            <span class="ai-char100-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-100" data-option="0"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle100Option2" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 2:</div>
                        <p class="mb-2 ai-title100-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title100-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char100-badge badge">0/105 chars</span>
                            <span class="ai-char100-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-100" data-option="1"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle100Option3" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 3:</div>
                        <p class="mb-2 ai-title100-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title100-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char100-badge badge">0/105 chars</span>
                            <span class="ai-char100-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-100" data-option="2"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle100Option4" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 4:</div>
                        <p class="mb-2 ai-title100-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title100-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char100-badge badge">0/105 chars</span>
                            <span class="ai-char100-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-100" data-option="3"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-regen-titles" id="aiRegenerateBtn100">
                        <i class="fas fa-redo-alt me-1"></i> REGENERATE 4 NEW TITLES
                    </button>
                    <button type="button" class="btn btn-cancel-ai" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Generated Title 80 Preview Modal (4 options, 75-85 chars) -->
    <div class="modal fade" id="aiTitle80Modal" tabindex="-1" aria-labelledby="aiTitle80ModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title" id="aiTitle80ModalLabel" title="75-85 chars for eBay">
                        <i class="fas fa-magic me-2"></i>AI Generated Titles (80 chars)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="aiTitle80Warning" class="alert alert-warning mb-3 d-none" role="alert"></div>
                    <div id="aiTitle80Option1" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 1:</div>
                        <p class="mb-2 ai-title80-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title80-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char80-badge badge">0/80 chars</span>
                            <span class="ai-char80-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-80" data-option="0"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle80Option2" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 2:</div>
                        <p class="mb-2 ai-title80-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title80-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char80-badge badge">0/80 chars</span>
                            <span class="ai-char80-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-80" data-option="1"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle80Option3" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 3:</div>
                        <p class="mb-2 ai-title80-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title80-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char80-badge badge">0/80 chars</span>
                            <span class="ai-char80-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-80" data-option="2"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle80Option4" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 4:</div>
                        <p class="mb-2 ai-title80-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title80-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char80-badge badge">0/80 chars</span>
                            <span class="ai-char80-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-80" data-option="3"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-regen-titles" id="aiRegenerateBtn80">
                        <i class="fas fa-redo-alt me-1"></i> REGENERATE 4 NEW TITLES
                    </button>
                    <button type="button" class="btn btn-cancel-ai" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Generated Title 60 Preview Modal (4 options, 55-60 chars) -->
    <div class="modal fade" id="aiTitle60Modal" tabindex="-1" aria-labelledby="aiTitle60ModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title" id="aiTitle60ModalLabel" title="55-60 chars for Macy's/Faire">
                        <i class="fas fa-magic me-2"></i>AI Generated Titles (60 chars)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="aiTitle60Warning" class="alert alert-warning mb-3 d-none" role="alert"></div>
                    <div id="aiTitle60Option1" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 1:</div>
                        <p class="mb-2 ai-title60-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title60-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char60-badge badge">0/60 chars</span>
                            <span class="ai-char60-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-60" data-option="0"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle60Option2" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 2:</div>
                        <p class="mb-2 ai-title60-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title60-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char60-badge badge">0/60 chars</span>
                            <span class="ai-char60-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-60" data-option="1"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle60Option3" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 3:</div>
                        <p class="mb-2 ai-title60-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title60-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char60-badge badge">0/60 chars</span>
                            <span class="ai-char60-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-60" data-option="2"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle60Option4" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 4:</div>
                        <p class="mb-2 ai-title60-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title60-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char60-badge badge">0/60 chars</span>
                            <span class="ai-char60-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-60" data-option="3"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-regen-titles" id="aiRegenerateBtn60">
                        <i class="fas fa-redo-alt me-1"></i> REGENERATE 4 NEW TITLES
                    </button>
                    <button type="button" class="btn btn-cancel-ai" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Generated Title Preview Modal (3 options) -->
    <div class="modal fade" id="aiTitleModal" tabindex="-1" aria-labelledby="aiTitleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title" id="aiTitleModalLabel">
                        <i class="fas fa-magic me-2"></i>AI Generated Titles (4 Options)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="aiTitleOption1" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 1:</div>
                        <p class="mb-2 ai-title-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char-badge badge">0/150 chars</span>
                            <span class="ai-char-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn" data-option="0"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitleOption2" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 2:</div>
                        <p class="mb-2 ai-title-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char-badge badge">0/150 chars</span>
                            <span class="ai-char-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn" data-option="1"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitleOption3" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 3:</div>
                        <p class="mb-2 ai-title-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char-badge badge">0/150 chars</span>
                            <span class="ai-char-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn" data-option="2"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitleOption4" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 4:</div>
                        <p class="mb-2 ai-title-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char-badge badge">0/150 chars</span>
                            <span class="ai-char-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn" data-option="3"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-regen-titles" id="aiRegenerateBtn">
                        <i class="fas fa-redo-alt me-1"></i> REGENERATE 4 NEW TITLES
                    </button>
                    <button type="button" class="btn btn-cancel-ai" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Selection Modal -->
    <div class="modal fade" id="platformModal" tabindex="-1" aria-labelledby="platformModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content platform-selector-modal">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="platformModalLabel">
                        <i class="fas fa-globe me-2"></i>Select Platforms to Update (<span id="platformSkuCount">0</span> SKUs)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Select which platforms you want to update. Each platform will update its corresponding title field.
                    </div>

                    <div class="row">
                        <!-- Amazon -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('amazon')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="amazon" id="platform_amazon">
                                    <label class="form-check-label w-100" for="platform_amazon">
                                        <i class="fab fa-amazon platform-icon text-warning"></i>
                                        <strong>Amazon</strong>
                                        <span class="badge bg-primary platform-badge">Title 150</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Shopify Main -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('shopify_main')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="shopify_main" id="platform_shopify_main">
                                    <label class="form-check-label w-100" for="platform_shopify_main">
                                        <i class="fab fa-shopify platform-icon text-success"></i>
                                        <strong>Shopify</strong>
                                        <span class="badge bg-success platform-badge">Title 100</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Shopify PLS (ProLightSounds) -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('shopify_pls')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="shopify_pls" id="platform_shopify_pls">
                                    <label class="form-check-label w-100" for="platform_shopify_pls">
                                        <i class="fab fa-shopify platform-icon text-success"></i>
                                        <strong>Shopify PLS</strong>
                                        <span class="badge bg-success platform-badge">Title 100</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- eBay 1 -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('ebay1')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="ebay1" id="platform_ebay1">
                                    <label class="form-check-label w-100" for="platform_ebay1">
                                        <i class="fas fa-gavel platform-icon text-info"></i>
                                        <strong>eBay 1</strong>
                                        <span class="badge bg-info platform-badge">Title 80</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- eBay 2 -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('ebay2')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="ebay2" id="platform_ebay2">
                                    <label class="form-check-label w-100" for="platform_ebay2">
                                        <i class="fas fa-gavel platform-icon text-info"></i>
                                        <strong>eBay 2</strong>
                                        <span class="badge bg-info platform-badge">Title 80</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- eBay 3 -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('ebay3')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="ebay3" id="platform_ebay3">
                                    <label class="form-check-label w-100" for="platform_ebay3">
                                        <i class="fas fa-gavel platform-icon text-info"></i>
                                        <strong>eBay 3</strong>
                                        <span class="badge bg-info platform-badge">Title 80</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Walmart -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('walmart')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="walmart" id="platform_walmart">
                                    <label class="form-check-label w-100" for="platform_walmart">
                                        <i class="fas fa-store platform-icon text-primary"></i>
                                        <strong>Walmart</strong>
                                        <span class="badge bg-info platform-badge">Title 80</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Temu -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('temu')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="temu" id="platform_temu">
                                    <label class="form-check-label w-100" for="platform_temu">
                                        <i class="fas fa-shopping-bag platform-icon text-danger"></i>
                                        <strong>Temu</strong>
                                        <span class="badge bg-primary platform-badge">Title 150</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Doba -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('doba')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="doba" id="platform_doba">
                                    <label class="form-check-label w-100" for="platform_doba">
                                        <i class="fas fa-box platform-icon text-secondary"></i>
                                        <strong>Doba</strong>
                                        <span class="badge bg-success platform-badge">Title 100</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Shein -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('shein')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="shein" id="platform_shein">
                                    <label class="form-check-label w-100" for="platform_shein">
                                        <i class="fas fa-shopping-bag platform-icon text-danger"></i>
                                        <strong>Shein</strong>
                                        <span class="badge bg-primary platform-badge">Title 150</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Wayfair -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('wayfair')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="wayfair" id="platform_wayfair">
                                    <label class="form-check-label w-100" for="platform_wayfair">
                                        <i class="fas fa-home platform-icon text-info"></i>
                                        <strong>Wayfair</strong>
                                        <span class="badge bg-primary platform-badge">Title 150</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Reverb -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('reverb')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="reverb" id="platform_reverb">
                                    <label class="form-check-label w-100" for="platform_reverb">
                                        <i class="fas fa-guitar platform-icon text-warning"></i>
                                        <strong>Reverb</strong>
                                        <span class="badge bg-primary platform-badge">Title 150</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Faire -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('macy')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="macy" id="platform_macy">
                                    <label class="form-check-label w-100" for="platform_macy">
                                        <i class="fas fa-building platform-icon text-primary"></i>
                                        <strong>Macy's</strong>
                                        <span class="badge bg-info platform-badge">Title 60</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('faire')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="faire" id="platform_faire">
                                    <label class="form-check-label w-100" for="platform_faire">
                                        <i class="fas fa-store platform-icon text-success"></i>
                                        <strong>Faire</strong>
                                        <span class="badge bg-info platform-badge">Title 60</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Aliexpress -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('aliexpress')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="aliexpress" id="platform_aliexpress">
                                    <label class="form-check-label w-100" for="platform_aliexpress">
                                        <i class="fas fa-shopping-cart platform-icon text-danger"></i>
                                        <strong>Aliexpress</strong>
                                        <span class="badge bg-primary platform-badge">Title 150</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- TikTok -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('tiktok')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="tiktok" id="platform_tiktok">
                                    <label class="form-check-label w-100" for="platform_tiktok">
                                        <i class="fab fa-tiktok platform-icon text-dark"></i>
                                        <strong>TikTok</strong>
                                        <span class="badge bg-primary platform-badge">Title 150</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i> <strong>Note:</strong> Updates will respect platform rate limits. This may take several seconds per product.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmUpdateBtn">
                        <i class="fas fa-cloud-upload-alt"></i> Update Selected Platforms
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Push to All Marketplaces Confirmation Modal -->
    <div class="modal fade" id="pushConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #ff9900; color: white;">
                    <h5 class="modal-title"><i class="fas fa-cloud-upload-alt me-2"></i>Push to All Marketplaces</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="pushConfirmMessage">Push 0 titles to Amazon, Temu, Reverb &amp; Wayfair? This may take several minutes.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="pushConfirmBtn" style="background-color: #ff9900;">
                        <i class="fas fa-cloud-upload-alt me-1"></i> Push to All
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Push to All Marketplaces Progress Modal -->
    <div class="modal fade" id="pushProgressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #ff9900; color: white;">
                    <h5 class="modal-title"><i class="fas fa-cloud-upload-alt me-2"></i>Pushing to All Marketplaces</h5>
                </div>
                <div class="modal-body">
                    <div class="progress mb-2" style="height: 25px;">
                        <div id="pushProgressBar" class="progress-bar" role="progressbar" style="width: 0%;">0%</div>
                    </div>
                    <p id="pushProgressText" class="mb-0">Pushing 0/0...</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        @verbatim
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let tableData = [];
        let listMeta = { current_page: 1, last_page: 1, per_page: 75, total: 0, from: null, to: null };
        let titleMasterLoadAbort = null;
        const titleFormatCache = new Map();
        let titleModal;
        let platformModal;
        let aiTitleModalInstance;
        let currentAIGeneratedTitles = [];
        let aiTitle100ModalInstance;
        let currentAIGeneratedTitles100 = [];
        let aiTitle80ModalInstance;
        let currentAIGeneratedTitles80 = [];
        let aiTitle60ModalInstance;
        let currentAIGeneratedTitles60 = [];

        document.addEventListener('DOMContentLoaded', function() {
            titleModal = new bootstrap.Modal(document.getElementById('titleModal'));
            platformModal = new bootstrap.Modal(document.getElementById('platformModal'));
            const aiTitleModalEl = document.getElementById('aiTitleModal');
            if (aiTitleModalEl) aiTitleModalInstance = new bootstrap.Modal(aiTitleModalEl);
            const aiTitle100ModalEl = document.getElementById('aiTitle100Modal');
            if (aiTitle100ModalEl) aiTitle100ModalInstance = new bootstrap.Modal(aiTitle100ModalEl);
            const aiTitle80ModalEl = document.getElementById('aiTitle80Modal');
            if (aiTitle80ModalEl) aiTitle80ModalInstance = new bootstrap.Modal(aiTitle80ModalEl);
            const aiTitle60ModalEl = document.getElementById('aiTitle60Modal');
            if (aiTitle60ModalEl) aiTitle60ModalInstance = new bootstrap.Modal(aiTitle60ModalEl);
            loadTitleData(1);
            document.getElementById('perPageSelect')?.addEventListener('change', function() { loadTitleData(1); });
            setupSearchHandlers();
            setupModalHandlers();
            setupButtonHandlers();
            setupCheckboxHandlers();
            setupPlatformModalHandlers();
            setupPlatformCheckboxes();
        });

        function setupButtonHandlers() {
            // Add Title Button
            document.getElementById('addTitleBtn').addEventListener('click', function() {
                openModal('add');
            });

            // Push ALL Button
            const pushAllBtn = document.getElementById('pushAllBtn');
            if (pushAllBtn) {
                pushAllBtn.addEventListener('click', function() {
                    const items = (tableData || []).filter(function(item) {
                        if (item.SKU && item.SKU.toUpperCase().includes('PARENT')) return false;
                        const t = (item.amazon_title || item.title150 || '').toString().trim();
                        return t.length > 0;
                    }).map(function(item) {
                        return { sku: item.SKU, title: (item.amazon_title || item.title150 || '').toString().trim() };
                    });
                    if (items.length === 0) {
                        alert('No titles to push. Ensure rows have Title 150 data.');
                        return;
                    }
                    document.getElementById('pushConfirmMessage').textContent = 'Push ' + items.length + ' title(s) on this page to Amazon, Temu & Reverb? This may take several minutes.';
                    const confirmModalEl = document.getElementById('pushConfirmModal');
                    const confirmModal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);
                    document.getElementById('pushConfirmBtn').onclick = function() {
                        confirmModal.hide();
                        runPushBulk(items);
                    };
                    confirmModal.show();
                });
            }

            // Push Selected Button
            const pushSelectedBtn = document.getElementById('pushSelectedBtn');
            if (pushSelectedBtn) {
                pushSelectedBtn.addEventListener('click', function() {
                    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                    const skus = Array.from(checkedBoxes).map(function(cb) { return cb.getAttribute('data-sku'); });
                    const items = skus.map(function(sku) {
                        const item = tableData.find(function(d) { return d.SKU === sku; });
                        const t = item ? (item.amazon_title || item.title150 || '').toString().trim() : '';
                        return { sku: sku, title: t };
                    }).filter(function(x) { return x.title.length > 0; });
                    if (items.length === 0) {
                        alert('No titles to push. Selected rows need Title 150 data.');
                        return;
                    }
                    document.getElementById('pushConfirmMessage').textContent = 'Push ' + items.length + ' selected title(s) on this page to Amazon, Temu & Reverb?';
                    const confirmModalEl = document.getElementById('pushConfirmModal');
                    const confirmModal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);
                    document.getElementById('pushConfirmBtn').onclick = function() {
                        confirmModal.hide();
                        runPushBulk(items);
                    };
                    confirmModal.show();
                });
            }

            // Export Button
            document.getElementById('exportBtn').addEventListener('click', function() {
                exportToExcel();
            });

            // Import Button
            document.getElementById('importBtn').addEventListener('click', function() {
                document.getElementById('importFile').click();
            });

            // Import File Handler
            document.getElementById('importFile').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    importFromExcel(file);
                }
            });

            // Update Titles Button - opens platform selection modal
            document.getElementById('updateAmazonBtn').addEventListener('click', function() {
                openPlatformSelectionModal();
            });
        }

        function setupPlatformModalHandlers() {
            // Confirm Update Button
            document.getElementById('confirmUpdateBtn').addEventListener('click', function() {
                updateSelectedPlatforms();
            });
        }

        function togglePlatform(platformId) {
            const checkbox = document.getElementById('platform_' + platformId);
            if (!checkbox) return;
            
            const platformItem = checkbox.closest('.platform-item');
            
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                platformItem.classList.add('selected');
            } else {
                platformItem.classList.remove('selected');
            }
        }

        // Setup platform checkbox click handlers to prevent double-toggle
        function setupPlatformCheckboxes() {
            document.querySelectorAll('[id^="platform_"]').forEach(checkbox => {
                checkbox.addEventListener('click', function(e) {
                    // Stop event from bubbling to parent platform-item
                    e.stopPropagation();
                    // The checkbox will handle its own checked state
                    const platformItem = this.closest('.platform-item');
                    if (this.checked) {
                        platformItem.classList.add('selected');
                    } else {
                        platformItem.classList.remove('selected');
                    }
                });
            });
        }

        function openPlatformSelectionModal() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            selectedSkusForUpdate = Array.from(checkedBoxes).map(cb => cb.getAttribute('data-sku'));

            if (selectedSkusForUpdate.length === 0) {
                alert('Please select at least one product');
                return;
            }

            // Update SKU count in modal
            document.getElementById('platformSkuCount').textContent = selectedSkusForUpdate.length;

            // Reset all platform selections
            document.querySelectorAll('.platform-item').forEach(item => {
                item.classList.remove('selected');
            });
            document.querySelectorAll('[id^="platform_"]').forEach(cb => {
                cb.checked = false;
            });

            // Show the platform selection modal
            platformModal.show();
        }

        function updateSelectedPlatforms() {
            // Collect selected platforms
            const platforms = [];
            if (document.getElementById('platform_amazon').checked) platforms.push('amazon');
            if (document.getElementById('platform_shopify_main').checked) platforms.push('shopify_main');
            if (document.getElementById('platform_shopify_pls').checked) platforms.push('shopify_pls');
            if (document.getElementById('platform_ebay1').checked) platforms.push('ebay1');
            if (document.getElementById('platform_ebay2').checked) platforms.push('ebay2');
            if (document.getElementById('platform_ebay3').checked) platforms.push('ebay3');
            if (document.getElementById('platform_walmart').checked) platforms.push('walmart');
            if (document.getElementById('platform_temu').checked) platforms.push('temu');
            if (document.getElementById('platform_doba').checked) platforms.push('doba');
            if (document.getElementById('platform_shein').checked) platforms.push('shein');
            if (document.getElementById('platform_wayfair').checked) platforms.push('wayfair');
            if (document.getElementById('platform_reverb').checked) platforms.push('reverb');
            if (document.getElementById('platform_macy').checked) platforms.push('macy');
            if (document.getElementById('platform_faire').checked) platforms.push('faire');
            if (document.getElementById('platform_aliexpress').checked) platforms.push('aliexpress');
            if (document.getElementById('platform_tiktok').checked) platforms.push('tiktok');

            if (platforms.length === 0) {
                alert('Please select at least one platform to update');
                return;
            }

            // Platform display names
            const platformNames = {
                'amazon': 'Amazon (Title 150)',
                'shopify_main': 'Shopify Main (Title 100)',
                'shopify_pls': 'Shopify PLS (Title 100)',
                'ebay1': 'eBay 1 (Title 80)',
                'ebay2': 'eBay 2 (Title 80)',
                'ebay3': 'eBay 3 (Title 80)',
                'walmart': 'Walmart (Title 150)',
                'temu': 'Temu (Title 150)',
                'doba': 'Doba (Title 100)',
                'shein': 'Shein (Title 150)',
                'wayfair': 'Wayfair (Title 150)',
                'reverb': 'Reverb (Title 150)',
                'macy': "Macy's (Title 60)",
                'faire': 'Faire (Title 60)',
                'aliexpress': 'Aliexpress (Title 150)',
                'tiktok': 'TikTok (Title 150)'
            };

            const platformList = platforms.map(p => platformNames[p]).join('\n');
            const confirmMsg = 'Update ' + selectedSkusForUpdate.length + ' product(s) to:\n\n' + platformList + '\n\nThis may take several seconds. Continue?';

            if (!confirm(confirmMsg)) {
                return;
            }

            // Hide platform modal and show processing
            platformModal.hide();

            const updateBtn = document.getElementById('updateAmazonBtn');
            updateBtn.disabled = true;
            updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            // Send to backend
            fetch('/title-master/update-platforms', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ 
                    skus: selectedSkusForUpdate,
                    platforms: platforms
                })
            })
            .then(response => {
                // Check if response is JSON or HTML
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // It's HTML (error page), read as text
                    return response.text().then(html => {
                        console.error('Server returned HTML instead of JSON:', html);
                        throw new Error('Server error - check browser console for details');
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    let message = 'Update Completed!\n\n';
                    
                    // Show results by platform
                    if (data.results) {
                        for (const [platform, result] of Object.entries(data.results)) {
                            const displayName = platformNames[platform] || platform.toUpperCase();
                            message += displayName + ': ';
                            message += 'Success: ' + result.success + ', Failed: ' + result.failed + '\n';
                        }
                    }
                    
                    message += '\nTotal Success: ' + data.total_success;
                    message += '\nTotal Failed: ' + data.total_failed;
                    
                    if (data.message && data.message.trim() !== '') {
                        message += '\n\nDetails:\n' + data.message;
                    }
                    
                    alert(message);
                    
                    // Uncheck all checkboxes
                    document.querySelectorAll('.row-checkbox:checked').forEach(cb => cb.checked = false);
                    document.getElementById('selectAll').checked = false;
                    updateSelectedCount();
                    
                    // Reload data
                    loadTitleData(listMeta.current_page || 1);
                } else {
                    alert('Error: ' + (data.message || 'Failed to update platforms'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating platforms: ' + error.message);
            })
            .finally(() => {
                updateBtn.disabled = false;
                const count = document.querySelectorAll('.row-checkbox:checked').length;
                updateBtn.innerHTML = '<i class="fas fa-sync"></i> Update Titles (<span id="selectedCount">' + count + '</span> selected)';
                if (count === 0) {
                    updateBtn.style.display = 'none';
                }
            });
        }

        function setupCheckboxHandlers() {
            const table = document.getElementById('title-master-table');
            if (!table) return;
            table.addEventListener('change', function(e) {
                if (e.target.id === 'selectAll') {
                    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = e.target.checked);
                }
                if (e.target.classList.contains('row-checkbox')) {
                    // Individual checkbox changed
                }
                updateSelectedCount();
            });
        }

        function updateSelectedCount() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            const count = checkedBoxes.length;
            const countElement = document.getElementById('selectedCount');
            if (countElement) countElement.textContent = count;
            const pushSelectedCountEl = document.getElementById('pushSelectedCount');
            if (pushSelectedCountEl) pushSelectedCountEl.textContent = count;
            const updateBtn = document.getElementById('updateAmazonBtn');
            if (updateBtn) updateBtn.style.display = count > 0 ? 'inline-block' : 'none';
            const pushSelectedBtn = document.getElementById('pushSelectedBtn');
            if (pushSelectedBtn) pushSelectedBtn.style.display = count > 0 ? 'inline-block' : 'none';
        }

        function updateModalCounter(fieldId) {
            const input = document.getElementById(fieldId);
            const maxLen = parseInt(fieldId.replace('title', ''), 10);
            const counter = document.getElementById('counter' + (fieldId === 'title100' ? 100 : maxLen));
            if (!input || !counter) return;
            const len = input.value.length;
            counter.classList.remove('error', 'warning', 'success', 'opacity-75');
            if (fieldId === 'title100') {
                const max105 = 105;
                counter.textContent = len + '/' + max105;
                if (len >= 95 && len <= 100) counter.classList.add('success');
                else if (len >= 101 && len <= max105) counter.classList.add('warning');
                else if (len >= 90 && len <= 94) counter.classList.add('success', 'opacity-75');
                else counter.classList.add('error');
            } else {
                counter.textContent = len + '/' + maxLen;
                if (len > maxLen) counter.classList.add('error');
            }
        }

        function showFieldLoading(fieldId) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            field.placeholder = 'Generating...';
            field.disabled = true;
            field.classList.add('bg-light');
        }

        function removeFieldLoading(fieldId) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            field.placeholder = '';
            field.disabled = false;
            field.classList.remove('bg-light');
        }

        function setupModalHandlers() {
            // Character counters
            const fields = ['title150', 'title100', 'title80', 'title60'];
            fields.forEach(field => {
                const maxLength = parseInt(field.replace('title', ''));
                const input = document.getElementById(field);
                const counter = document.getElementById('counter' + maxLength);
                
                input.addEventListener('input', function() {
                    const length = this.value.length;
                    counter.classList.remove('error', 'warning', 'success', 'opacity-75');
                    if (field === 'title100') {
                        const max105 = 105;
                        counter.textContent = length + '/' + max105;
                        if (length >= 95 && length <= 100) counter.classList.add('success');
                        else if (length >= 101 && length <= max105) counter.classList.add('warning');
                        else if (length >= 90 && length <= 94) counter.classList.add('success', 'opacity-75');
                        else counter.classList.add('error');
                    } else {
                        counter.textContent = length + '/' + maxLength;
                        if (length > maxLength) counter.classList.add('error');
                    }
                });
            });

            // Save button
            document.getElementById('saveTitleBtn').addEventListener('click', function() {
                saveTitleFromModal();
            });

            // Improve with AI button (generates only Title 150, shows in popup)
            const aiImproveBtn = document.getElementById('aiImproveBtn');
            if (aiImproveBtn) {
                aiImproveBtn.addEventListener('click', function() {
                    const btn = this;
                    const originalHtml = btn.innerHTML;
                    const currentTitle150 = document.getElementById('title150').value.trim();
                    const sku = (document.getElementById('editSku') && document.getElementById('editSku').value) || (document.getElementById('selectSku') && document.getElementById('selectSku').value) || '';
                    const item = tableData && sku ? tableData.find(d => d.SKU === sku) : null;
                    const parentCategory = (item && item.Parent) ? item.Parent : '';

                    if (!currentTitle150) {
                        alert('Please enter or load a Title 150 (e.g. open Edit with a row that has an Amazon title) before using Improve with AI.');
                        return;
                    }

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Generating...';

                    fetch('/title-master/ai/generate-title-150', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            sku: sku,
                            current_title: currentTitle150,
                            parent_category: parentCategory,
                            min_length: 140,
                            max_length: 150
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.items && data.items.length >= 4) {
                            showAITitlePopup(data.items);
                        } else if (data.success && data.titles && data.titles.length >= 3) {
                            var items = data.titles.slice(0, 3).map(function(t) { return { title: t, score: null }; });
                            while (items.length < 4) items.push({ title: '', score: null });
                            showAITitlePopup(items);
                        } else {
                            alert('Failed to generate titles: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(err => {
                        console.error('AI generate title error:', err);
                        alert('Error: ' + (err.message || 'Network or server error'));
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    });
                });
            }

            // Improve with AI button for Title 100 (4 options, ≤100 chars)
            const aiImproveBtn100 = document.getElementById('aiImproveBtn100');
            if (aiImproveBtn100) {
                aiImproveBtn100.addEventListener('click', function() {
                    const btn = this;
                    const originalHtml = btn.innerHTML;
                    const title150 = document.getElementById('title150').value.trim();
                    const currentTitle100 = document.getElementById('title100').value.trim();
                    const sku = (document.getElementById('editSku') && document.getElementById('editSku').value) || (document.getElementById('selectSku') && document.getElementById('selectSku').value) || '';
                    const item = tableData && sku ? tableData.find(d => d.SKU === sku) : null;
                    const category = (item && item.Parent) ? item.Parent : '';

                    if (!title150) {
                        alert('Please enter or load a Title 150 before using Improve with AI for Title 100.');
                        return;
                    }

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Generating 100-char titles...';

                    fetch('/title-master/ai/generate-title-100', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            sku: sku,
                            title_150: title150,
                            current_title_100: currentTitle100,
                            category: category
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.titles && data.titles.length >= 1) {
                            showTitle100Popup(data.titles, data.invalid_count || 0);
                        } else {
                            alert(data.message || 'Failed to generate titles. Please click Regenerate to try again.');
                        }
                    })
                    .catch(err => {
                        console.error('AI generate title 100 error:', err);
                        alert('Error: ' + (err.message || 'Network or server error'));
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    });
                });
            }

            // AI Title 100 popup: Keep buttons (apply to Title 100 field) and Regenerate
            document.querySelectorAll('.ai-keep-btn-100').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.getAttribute('data-option'), 10);
                    const title = currentAIGeneratedTitles100[idx];
                    const el100 = document.getElementById('title100');
                    if (el100 && title) {
                        el100.value = title.length > 105 ? title.substring(0, 105) : title;
                        updateModalCounter('title100');
                    }
                    if (aiTitle100ModalInstance) aiTitle100ModalInstance.hide();
                    alert('Title applied to Title 100 field. Click Save to store.');
                });
            });
            const aiRegenBtn100 = document.getElementById('aiRegenerateBtn100');
            if (aiRegenBtn100) {
                aiRegenBtn100.addEventListener('click', function() {
                    if (aiTitle100ModalInstance) aiTitle100ModalInstance.hide();
                    setTimeout(function() {
                        if (aiImproveBtn100) aiImproveBtn100.click();
                    }, 300);
                });
            }

            // Improve with AI button for Title 80 (4 options, 75-85 chars)
            const aiImproveBtn80 = document.getElementById('aiImproveBtn80');
            if (aiImproveBtn80) {
                aiImproveBtn80.addEventListener('click', function() {
                    const btn = this;
                    const originalHtml = btn.innerHTML;
                    const title150 = document.getElementById('title150').value.trim();
                    const currentTitle80 = document.getElementById('title80').value.trim();
                    const sku = (document.getElementById('editSku') && document.getElementById('editSku').value) || (document.getElementById('selectSku') && document.getElementById('selectSku').value) || '';
                    const item = tableData && sku ? tableData.find(d => d.SKU === sku) : null;
                    const category = (item && item.Parent) ? item.Parent : '';

                    if (!title150) {
                        alert('Please enter or load a Title 150 before using Improve with AI for Title 80.');
                        return;
                    }

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Generating 80-char titles...';

                    fetch('/title-master/ai/generate-title-80', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            sku: sku,
                            title_150: title150,
                            current_title_80: currentTitle80,
                            category: category
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.titles && data.titles.length >= 1) {
                            showTitle80Popup(data.titles, data.invalid_count || 0);
                        } else {
                            alert(data.message || 'Failed to generate titles. Please click Regenerate to try again.');
                        }
                    })
                    .catch(err => {
                        console.error('AI generate title 80 error:', err);
                        alert('Error: ' + (err.message || 'Network or server error'));
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    });
                });
            }

            // AI Title 80 popup: Keep buttons (apply to Title 80 field) and Regenerate
            document.querySelectorAll('.ai-keep-btn-80').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.getAttribute('data-option'), 10);
                    const title = currentAIGeneratedTitles80[idx];
                    const el80 = document.getElementById('title80');
                    if (el80 && title) {
                        el80.value = title.length > 80 ? title.substring(0, 80) : title;
                        updateModalCounter('title80');
                    }
                    if (aiTitle80ModalInstance) aiTitle80ModalInstance.hide();
                    if (typeof showToast === 'function') {
                        showToast('success', 'Title applied to Title 80 field. Click Save to store.');
                    } else {
                        alert('Title applied to Title 80 field. Click Save to store.');
                    }
                });
            });
            const aiRegenBtn80 = document.getElementById('aiRegenerateBtn80');
            if (aiRegenBtn80) {
                aiRegenBtn80.addEventListener('click', function() {
                    if (aiTitle80ModalInstance) aiTitle80ModalInstance.hide();
                    setTimeout(function() {
                        if (document.getElementById('aiImproveBtn80')) document.getElementById('aiImproveBtn80').click();
                    }, 300);
                });
            }

            // Improve with AI button for Title 60 (4 options, 55-60 chars)
            const aiImproveBtn60 = document.getElementById('aiImproveBtn60');
            if (aiImproveBtn60) {
                aiImproveBtn60.addEventListener('click', function() {
                    const btn = this;
                    const originalHtml = btn.innerHTML;
                    const title150 = document.getElementById('title150').value.trim();
                    const currentTitle60 = document.getElementById('title60').value.trim();
                    const sku = (document.getElementById('editSku') && document.getElementById('editSku').value) || (document.getElementById('selectSku') && document.getElementById('selectSku').value) || '';
                    const item = tableData && sku ? tableData.find(d => d.SKU === sku) : null;
                    const category = (item && item.Parent) ? item.Parent : '';

                    if (!title150) {
                        alert('Please enter or load a Title 150 before using Improve with AI for Title 60.');
                        return;
                    }

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Generating 60-char titles...';

                    fetch('/title-master/ai/generate-title-60', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            sku: sku,
                            title_150: title150,
                            current_title_60: currentTitle60,
                            category: category,
                            marketplace: 'macy'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.titles && data.titles.length >= 1) {
                            showTitle60Popup(data.titles, data.invalid_count || 0);
                        } else {
                            alert(data.message || 'Failed to generate titles. Please click Regenerate to try again.');
                        }
                    })
                    .catch(err => {
                        console.error('AI generate title 60 error:', err);
                        alert('Error: ' + (err.message || 'Network or server error'));
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    });
                });
            }

            document.querySelectorAll('.ai-keep-btn-60').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.getAttribute('data-option'), 10);
                    const title = currentAIGeneratedTitles60[idx];
                    const el60 = document.getElementById('title60');
                    if (el60 && title) {
                        el60.value = title.length > 60 ? title.substring(0, 60) : title;
                        updateModalCounter('title60');
                    }
                    if (aiTitle60ModalInstance) aiTitle60ModalInstance.hide();
                    if (typeof showToast === 'function') {
                        showToast('success', 'Title applied to Title 60 field. Click Save to store.');
                    } else {
                        alert('Title applied to Title 60 field. Click Save to store.');
                    }
                });
            });
            const aiRegenBtn60 = document.getElementById('aiRegenerateBtn60');
            if (aiRegenBtn60) {
                aiRegenBtn60.addEventListener('click', function() {
                    if (aiTitle60ModalInstance) aiTitle60ModalInstance.hide();
                    setTimeout(function() {
                        if (document.getElementById('aiImproveBtn60')) document.getElementById('aiImproveBtn60').click();
                    }, 300);
                });
            }

            // AI Title popup: Keep buttons (per option) and Regenerate
            document.querySelectorAll('.ai-keep-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.getAttribute('data-option'), 10);
                    const title = currentAIGeneratedTitles[idx];
                    const el150 = document.getElementById('title150');
                    if (el150 && title) {
                        el150.value = title;
                        updateModalCounter('title150');
                    }
                    if (aiTitleModalInstance) aiTitleModalInstance.hide();
                    alert('Title applied to Title 150 field. Click Save to store.');
                });
            });
            const aiRegenBtn = document.getElementById('aiRegenerateBtn');
            if (aiRegenBtn) {
                aiRegenBtn.addEventListener('click', function() {
                    if (aiTitleModalInstance) aiTitleModalInstance.hide();
                    setTimeout(function() {
                        if (aiImproveBtn) aiImproveBtn.click();
                    }, 300);
                });
            }
        }

        function showAITitlePopup(items) {
            if (!Array.isArray(items) || items.length < 3) return;
            currentAIGeneratedTitles = items.slice(0, 4).map(function(x) {
                return (x && (typeof x === 'string' ? x : x.title)) || '';
            });
            const options = document.querySelectorAll('#aiTitleOption1, #aiTitleOption2, #aiTitleOption3, #aiTitleOption4');
            const minLen = 140;
            const maxLen = 150;
            options.forEach(function(opt, i) {
                const item = items[i];
                const title = (item && (typeof item === 'string' ? item : item.title)) || '';
                const score = item && typeof item === 'object' && item.score != null ? item.score : null;
                const len = title.length;
                const textEl = opt.querySelector('.ai-title-text');
                const scoreEl = opt.querySelector('.ai-title-score');
                const badgeEl = opt.querySelector('.ai-char-badge');
                const statusEl = opt.querySelector('.ai-char-status');
                if (textEl) textEl.textContent = title;
                if (scoreEl) {
                    if (score != null) scoreEl.textContent = 'Success score: ' + score + '/10';
                    else scoreEl.textContent = '';
                }
                if (badgeEl) {
                    badgeEl.textContent = len + '/150 chars';
                    badgeEl.className = 'badge ai-char-badge ';
                    if (len > maxLen) badgeEl.classList.add('bg-danger');
                    else if (len < minLen) badgeEl.classList.add('bg-warning', 'text-dark');
                    else badgeEl.classList.add('bg-success');
                }
                if (statusEl) {
                    if (len > maxLen) statusEl.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Too long</span>';
                    else if (len < minLen) statusEl.innerHTML = '<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Too short (aim 140-150)</span>';
                    else statusEl.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> ✓</span>';
                }
            });
            if (aiTitleModalInstance) aiTitleModalInstance.show();
        }

        function showTitle100Popup(titles, invalidCount) {
            invalidCount = invalidCount || 0;
            if (!Array.isArray(titles) || titles.length < 1) return;
            var padded = titles.slice(0, 4);
            while (padded.length < 4) padded.push({ title: '', score: null });
            currentAIGeneratedTitles100 = padded.map(function(t) { return (t && typeof t === 'string' ? t : (t.title || '')) || ''; });
            const maxLen = 105;
            const optionIds = ['aiTitle100Option1', 'aiTitle100Option2', 'aiTitle100Option3', 'aiTitle100Option4'];
            const warningEl = document.getElementById('aiTitle100Warning');
            if (warningEl) {
                if (invalidCount > 0) {
                    const validN = titles.length;
                    warningEl.textContent = validN + ' title(s) generated, ' + invalidCount + ' was out of range (90-105).';
                    warningEl.classList.remove('d-none');
                } else {
                    warningEl.classList.add('d-none');
                }
            }
            optionIds.forEach(function(id, i) {
                const opt = document.getElementById(id);
                if (!opt) return;
                const item = padded[i];
                const title = currentAIGeneratedTitles100[i] || '';
                const hasTitle = title.length > 0;
                const score = item && typeof item === 'object' && item.score != null ? item.score : null;
                const len = title.length;
                const perfect = len >= 95 && len <= 100;
                const slightlyLong = len >= 101 && len <= 105;
                const good = len >= 90 && len <= 94;
                const textEl = opt.querySelector('.ai-title100-text');
                const scoreEl = opt.querySelector('.ai-title100-score');
                const badgeEl = opt.querySelector('.ai-char100-badge');
                const statusEl = opt.querySelector('.ai-char100-status');
                const keepBtn = opt.querySelector('.ai-keep-btn-100');
                if (hasTitle) {
                    opt.style.display = '';
                    if (textEl) textEl.textContent = title;
                    if (scoreEl) {
                        if (score != null) scoreEl.textContent = 'Success score: ' + score + '/10';
                        else scoreEl.textContent = '';
                    }
                    if (badgeEl) {
                        badgeEl.textContent = len + '/105 chars';
                        let badgeClass = 'badge ai-char100-badge ';
                        if (perfect) badgeClass += 'bg-success';
                        else if (slightlyLong) badgeClass += 'bg-warning text-dark';
                        else if (good) badgeClass += 'bg-success bg-opacity-75';
                        else badgeClass += 'bg-danger';
                        badgeEl.className = badgeClass;
                    }
                    if (statusEl) {
                        let statusText = len + ' chars ';
                        if (perfect) statusText += '✅ Perfect (Target)';
                        else if (slightlyLong) statusText += '⚠️ Slightly long (Acceptable)';
                        else if (good) statusText += '🟢 Good (Within range)';
                        else statusText += '❌ Out of range';
                        statusEl.textContent = statusText;
                        statusEl.className = 'ai-char100-status ' + (perfect ? 'text-success' : slightlyLong ? 'text-warning' : good ? 'text-success' : 'text-danger');
                    }
                    if (keepBtn) { keepBtn.disabled = false; keepBtn.style.display = ''; }
                } else {
                    opt.style.display = 'none';
                    if (keepBtn) keepBtn.disabled = true;
                }
            });
            if (aiTitle100ModalInstance) aiTitle100ModalInstance.show();
        }

        function showTitle80Popup(titles, invalidCount) {
            invalidCount = invalidCount || 0;
            if (!Array.isArray(titles) || titles.length < 1) return;
            var padded = titles.slice(0, 4);
            while (padded.length < 4) padded.push({ title: '', score: null });
            currentAIGeneratedTitles80 = padded.map(function(t) { return (t && typeof t === 'string' ? t : (t.title || '')) || ''; });
            const minLen = 75;
            const maxLen = 85;
            const optionIds = ['aiTitle80Option1', 'aiTitle80Option2', 'aiTitle80Option3', 'aiTitle80Option4'];
            const warningEl = document.getElementById('aiTitle80Warning');
            if (warningEl) {
                if (invalidCount > 0) {
                    const validN = titles.length;
                    warningEl.textContent = validN + ' title(s) generated, ' + invalidCount + ' was out of range (75-85).';
                    warningEl.classList.remove('d-none');
                } else {
                    warningEl.classList.add('d-none');
                }
            }
            optionIds.forEach(function(id, i) {
                const opt = document.getElementById(id);
                if (!opt) return;
                const item = padded[i];
                const title = currentAIGeneratedTitles80[i] || '';
                const hasTitle = title.length > 0;
                const score = item && typeof item === 'object' && item.score != null ? item.score : null;
                const len = title.length;
                const inRange = len >= minLen && len <= maxLen;
                const textEl = opt.querySelector('.ai-title80-text');
                const scoreEl = opt.querySelector('.ai-title80-score');
                const badgeEl = opt.querySelector('.ai-char80-badge');
                const statusEl = opt.querySelector('.ai-char80-status');
                const keepBtn = opt.querySelector('.ai-keep-btn-80');
                if (hasTitle) {
                    opt.style.display = '';
                    if (textEl) textEl.textContent = title;
                    if (scoreEl) {
                        if (score != null) scoreEl.textContent = 'Score: ' + score + '%';
                        else scoreEl.textContent = '';
                    }
                    if (badgeEl) {
                        badgeEl.textContent = len + '/80 chars';
                        let badgeClass = 'badge ai-char80-badge ';
                        if (inRange) badgeClass += 'bg-success';
                        else badgeClass += 'bg-danger';
                        badgeEl.className = badgeClass;
                    }
                    if (statusEl) {
                        statusEl.textContent = inRange ? '✅ Within 75-85' : '❌ Out of range';
                        statusEl.className = 'ai-char80-status ' + (inRange ? 'text-success' : 'text-danger');
                    }
                    if (keepBtn) { keepBtn.disabled = false; keepBtn.style.display = ''; }
                } else {
                    opt.style.display = 'none';
                    if (keepBtn) keepBtn.disabled = true;
                }
            });
            if (aiTitle80ModalInstance) aiTitle80ModalInstance.show();
        }

        function showTitle60Popup(titles, invalidCount) {
            invalidCount = invalidCount || 0;
            if (!Array.isArray(titles) || titles.length < 1) return;
            var padded = titles.slice(0, 4);
            while (padded.length < 4) padded.push({ title: '', score: null });
            currentAIGeneratedTitles60 = padded.map(function(t) { return (t && typeof t === 'string' ? t : (t.title || '')) || ''; });
            const minLen = 55;
            const maxLen = 60;
            const optionIds = ['aiTitle60Option1', 'aiTitle60Option2', 'aiTitle60Option3', 'aiTitle60Option4'];
            const warningEl = document.getElementById('aiTitle60Warning');
            if (warningEl) {
                if (invalidCount > 0) {
                    warningEl.textContent = titles.length + ' title(s) generated, ' + invalidCount + ' out of range (55-60).';
                    warningEl.classList.remove('d-none');
                } else {
                    warningEl.classList.add('d-none');
                }
            }
            optionIds.forEach(function(id, i) {
                const opt = document.getElementById(id);
                if (!opt) return;
                const item = padded[i];
                const title = currentAIGeneratedTitles60[i] || '';
                const hasTitle = title.length > 0;
                const score = item && typeof item === 'object' && item.score != null ? item.score : null;
                const len = title.length;
                const textEl = opt.querySelector('.ai-title60-text');
                const scoreEl = opt.querySelector('.ai-title60-score');
                const badgeEl = opt.querySelector('.ai-char60-badge');
                const statusEl = opt.querySelector('.ai-char60-status');
                const keepBtn = opt.querySelector('.ai-keep-btn-60');
                if (hasTitle) {
                    opt.style.display = '';
                    if (textEl) textEl.textContent = title;
                    if (scoreEl) scoreEl.textContent = score != null ? ('Score: ' + score + '%') : '';
                    if (badgeEl) {
                        badgeEl.textContent = len + '/60 chars';
                        badgeEl.className = 'ai-char60-badge badge ' + ((len >= minLen && len <= maxLen) ? 'bg-success' : 'bg-danger');
                    }
                    if (statusEl) {
                        statusEl.textContent = (len >= minLen && len <= maxLen) ? '✅ In range' : '❌ Out of range';
                        statusEl.className = 'ai-char60-status ' + ((len >= minLen && len <= maxLen) ? 'text-success' : 'text-danger');
                    }
                    if (keepBtn) { keepBtn.disabled = false; keepBtn.style.display = ''; }
                } else {
                    opt.style.display = 'none';
                    if (keepBtn) keepBtn.disabled = true;
                }
            });
            if (aiTitle60ModalInstance) aiTitle60ModalInstance.show();
        }

        function buildTitleMasterQueryParams(forPage) {
            const params = new URLSearchParams();
            const perPage = document.getElementById('perPageSelect')?.value || 75;
            params.set('per_page', String(perPage));
            params.set('page', String(forPage != null ? forPage : 1));
            const qParent = (document.getElementById('parentSearch')?.value || '').trim();
            const qSku = (document.getElementById('skuSearch')?.value || '').trim();
            if (qParent) params.set('q_parent', qParent);
            if (qSku) params.set('q_sku', qSku);
            const f150 = document.getElementById('filterTitle150')?.value;
            const f100 = document.getElementById('filterTitle100')?.value;
            const f80 = document.getElementById('filterTitle80')?.value;
            const f60 = document.getElementById('filterTitle60')?.value;
            if (f150 && f150 !== 'all') params.set('filter_title150', f150);
            if (f100 && f100 !== 'all') params.set('filter_title100', f100);
            if (f80 && f80 !== 'all') params.set('filter_title80', f80);
            if (f60 && f60 !== 'all') params.set('filter_title60', f60);
            return params;
        }

        function updateCountsFromStats(stats) {
            if (!stats) return;
            document.getElementById('parentCount').textContent = '(' + (stats.distinct_parents != null ? stats.distinct_parents : 0) + ')';
            document.getElementById('skuCount').textContent = '(' + (stats.total_rows != null ? stats.total_rows : 0) + ')';
            document.getElementById('title150MissingCount').textContent = '(' + (stats.title150_missing != null ? stats.title150_missing : 0) + ' missing)';
            document.getElementById('title100MissingCount').textContent = '(' + (stats.title100_missing != null ? stats.title100_missing : 0) + ')';
            document.getElementById('title80MissingCount').textContent = '(' + (stats.title80_missing != null ? stats.title80_missing : 0) + ')';
            document.getElementById('title60MissingCount').textContent = '(' + (stats.title60_missing != null ? stats.title60_missing : 0) + ')';
        }

        function renderPagination() {
            const info = document.getElementById('tmPageInfo');
            const ul = document.getElementById('tmPagination');
            if (!info || !ul) return;
            const cur = listMeta.current_page || 1;
            const last = listMeta.last_page || 1;
            const total = listMeta.total || 0;
            const from = listMeta.from;
            const to = listMeta.to;
            info.textContent = (from != null && to != null && total != null)
                ? ('Showing ' + from + '–' + to + ' of ' + total)
                : ('Page ' + cur + ' of ' + last);
            let html = '';
            const addLi = function(label, page, disabled, active) {
                html += '<li class="page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '') + '">';
                html += '<a class="page-link" href="#" data-tm-page="' + page + '">' + label + '</a></li>';
            };
            addLi('«', cur - 1, cur <= 1, false);
            const windowSize = 5;
            let start = Math.max(1, cur - Math.floor(windowSize / 2));
            let end = Math.min(last, start + windowSize - 1);
            start = Math.max(1, end - windowSize + 1);
            for (let i = start; i <= end; i++) addLi(String(i), i, false, i === cur);
            addLi('»', cur + 1, cur >= last, false);
            ul.innerHTML = html;
            ul.querySelectorAll('a.page-link').forEach(function(a) {
                a.addEventListener('click', function(ev) {
                    ev.preventDefault();
                    const pg = parseInt(a.getAttribute('data-tm-page'), 10);
                    if (!pg || pg < 1 || pg > last || pg === cur) return;
                    loadTitleData(pg);
                });
            });
        }

        function loadTitleData(page) {
            if (titleMasterLoadAbort) {
                try { titleMasterLoadAbort.abort(); } catch (e) {}
            }
            titleMasterLoadAbort = new AbortController();
            const p = page != null ? page : 1;
            const params = buildTitleMasterQueryParams(p);
            document.getElementById('rainbow-loader').style.display = 'block';

            fetch('/title-master-data?' + params.toString(), {
                signal: titleMasterLoadAbort.signal,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function(response) {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(function(response) {
                    const data = response.data;
                    if (data && Array.isArray(data)) {
                        tableData = data;
                        listMeta = response.meta || listMeta;
                        renderTable(tableData);
                        updateCountsFromStats(response.stats);
                        renderPagination();
                    } else {
                        console.error('Invalid data:', response);
                        showError('Invalid data format received from server');
                    }
                    document.getElementById('rainbow-loader').style.display = 'none';
                })
                .catch(function(error) {
                    if (error.name === 'AbortError') return;
                    console.error('Error:', error);
                    showError('Failed to load product data: ' + error.message);
                    document.getElementById('rainbow-loader').style.display = 'none';
                });
        }

        function escapeTooltipAttr(text) {
            return String(text == null ? '' : text).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        }

        /**
         * Previously instantiated Bootstrap Tooltip on every marketplace cell (1000+ rows × many buttons),
         * which blocked the main thread for minutes. Native `title` attributes already provide hover text.
         */
        function initMarketplaceTooltips(root) {
            return;
        }

        function renderMarketplaceDots(sku, statusMap) {
            const mps = ['amazon', 'temu', 'reverb'];
            const labels = { amazon: 'Amazon', temu: 'Temu', reverb: 'Reverb' };
            let html = '<div class="marketplaces-dots" data-sku="' + (sku || '') + '">';
            mps.forEach(function(mp) {
                const st = (statusMap && statusMap[mp]) ? statusMap[mp] : 'pending';
                const title = labels[mp] + ': ' + (st === 'success' ? 'Pushed' : st === 'failed' ? 'Failed' : st === 'loading' ? 'In progress...' : 'Not pushed');
                html += '<span class="mp-dot marketplace-tooltip ' + mp + ' ' + st + '" data-marketplace="' + mp + '" title="' + escapeTooltipAttr(title) + '"></span>';
            });
            html += '</div>';
            return html;
        }

        function renderMarketplaceDots80(sku, statusMap) {
            const mps = ['ebay1', 'ebay2', 'ebay3'];
            const labels = { ebay1: 'eBay 1 (AmarjitK)', ebay2: 'eBay 2 (ProLight)', ebay3: 'eBay 3 (KaneerKa)' };
            let html = '<div class="marketplaces-dots" data-sku="' + (sku || '') + '">';
            mps.forEach(function(mp) {
                const st = (statusMap && statusMap[mp]) ? statusMap[mp] : 'pending';
                const title = labels[mp] + ': ' + (st === 'success' ? 'Pushed' : st === 'failed' ? 'Failed' : st === 'loading' ? 'In progress...' : 'Not pushed');
                html += '<span class="mp-dot marketplace-tooltip ' + mp + ' ' + st + '" data-marketplace="' + mp + '" title="' + escapeTooltipAttr(title) + '"></span>';
            });
            html += '</div>';
            return html;
        }

        function renderMarketplaceDots100(sku, statusMap) {
            const mps = ['shopify_main', 'shopify_pls', 'macy'];
            const labels = { shopify_main: 'Shopify Main', shopify_pls: 'Shopify PLS', macy: "Macy's" };
            let html = '<div class="marketplaces-dots" data-sku="' + (sku || '') + '">';
            mps.forEach(function(mp) {
                const st = (statusMap && statusMap[mp]) ? statusMap[mp] : 'pending';
                const title = labels[mp] + ': ' + (st === 'success' ? 'Pushed' : st === 'failed' ? 'Failed' : st === 'loading' ? 'In progress...' : 'Not pushed');
                html += '<span class="mp-dot marketplace-tooltip ' + mp + ' ' + st + '" data-marketplace="' + mp + '" title="' + escapeTooltipAttr(title) + '"></span>';
            });
            html += '</div>';
            return html;
        }

        function updateMarketplaceDotsInRow(row, results) {
            const wrapper = row.querySelector('.marketplaces-150-cell .marketplaces-dots-wrapper');
            if (!wrapper || !results) return;
            const btn = row.querySelector('.push-all-marketplaces-btn');
            const skuVal = (btn && btn.getAttribute('data-sku')) ? btn.getAttribute('data-sku') : '';
            const statusMap = {};
            ['amazon', 'temu', 'reverb'].forEach(function(mp) {
                statusMap[mp] = (results[mp] && results[mp].status) ? results[mp].status : 'pending';
            });
            wrapper.innerHTML = renderMarketplaceDots(skuVal, statusMap);
            initMarketplaceTooltips(wrapper);
        }

        function updateMarketplaceDots100InRow(row, results) {
            const wrapper100 = row.querySelector('.marketplaces-dots-100');
            if (!wrapper100 || !results) return;
            const btn = row.querySelector('.push-all-marketplaces-btn');
            const skuVal = (btn && btn.getAttribute('data-sku')) ? btn.getAttribute('data-sku') : '';
            const statusMap100 = {};
            ['shopify_main', 'shopify_pls', 'macy'].forEach(function(mp) {
                statusMap100[mp] = (results[mp] && results[mp].status) ? results[mp].status : 'pending';
            });
            wrapper100.innerHTML = renderMarketplaceDots100(skuVal, statusMap100);
            initMarketplaceTooltips(wrapper100);
        }

        function formatTitleWithIndicator(title) {
            if (!title || (typeof title === 'string' && title.trim() === '')) {
                return { html: '-', tooltip: '' };
            }
            const key = typeof title === 'string' ? title : String(title);
            if (titleFormatCache.has(key)) {
                return titleFormatCache.get(key);
            }
            const maxLength = 150;
            const totalChars = title.length;
            const excess = totalChars - maxLength;
            let out;
            if (totalChars <= maxLength) {
                out = {
                    html: '<span class="title-indicator success">✅</span> ' + escapeHtml(title),
                    tooltip: '✓ ' + totalChars + '/' + maxLength + ' characters — within limit\n\n' + title
                };
            } else {
                out = {
                    html: '<span class="title-indicator danger">❌</span> <span class="excess-badge">(+' + excess + ')</span> ' + escapeHtml(title),
                    tooltip: '⚠️ ' + totalChars + '/' + maxLength + ' characters\n' + excess + ' characters above limit\n\nFull title:\n' + title
                };
            }
            titleFormatCache.set(key, out);
            return out;
        }

        function renderTable(data) {
            titleFormatCache.clear();
            const tbody = document.getElementById('table-body');
            const frag = document.createDocumentFragment();

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="13" class="text-center">No products found</td></tr>';
                return;
            }

            // Filter out parent rows before rendering
            const filteredData = data.filter(item => {
                return !(item.SKU && item.SKU.toUpperCase().includes('PARENT'));
            });

            if (filteredData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="13" class="text-center">No products found</td></tr>';
                return;
            }

            filteredData.forEach(item => {
                const row = document.createElement('tr');
                if (item.SKU) {
                    row.setAttribute('data-sku', item.SKU);
                }

                // Checkbox
                const checkboxCell = document.createElement('td');
                checkboxCell.innerHTML = '<input type="checkbox" class="row-checkbox" data-sku="' + escapeHtml(item.SKU) + '">';
                row.appendChild(checkboxCell);

                // Images
                const imageCell = document.createElement('td');
                imageCell.className = 'table-img-cell';
                imageCell.innerHTML = item.image_path
                    ? '<img src="' + escapeHtml(item.image_path) + '" alt="" loading="lazy" decoding="async">'
                    : '-';
                row.appendChild(imageCell);

                // Parent
                const parentCell = document.createElement('td');
                parentCell.textContent = (item.Parent != null && item.Parent !== '') ? String(item.Parent) : '-';
                row.appendChild(parentCell);

                // SKU
                const skuCell = document.createElement('td');
                skuCell.textContent = (item.SKU != null && item.SKU !== '') ? String(item.SKU) : '-';
                row.appendChild(skuCell);

                // Title 150 (Amazon title with ✅/❌ and (+X) excess, tooltip with char count)
                const title150Formatted = formatTitleWithIndicator(item.amazon_title);
                const title150Cell = document.createElement('td');
                title150Cell.className = 'title-text title-cell';
                title150Cell.innerHTML = title150Formatted.html;
                const tooltipText = title150Formatted.tooltip || item.amazon_title || '';
                title150Cell.setAttribute('title', tooltipText.replace(/"/g, '&quot;').replace(/</g, '&lt;'));
                row.appendChild(title150Cell);

                // Title 100
                const title100Cell = document.createElement('td');
                title100Cell.className = 'title-text';
                title100Cell.textContent = item.title100 || '-';
                title100Cell.title = item.title100 || '';
                row.appendChild(title100Cell);

                // Title 80
                const title80Cell = document.createElement('td');
                title80Cell.className = 'title-text';
                title80Cell.textContent = item.title80 || '-';
                title80Cell.title = item.title80 || '';
                row.appendChild(title80Cell);

                // Title 60
                const title60Cell = document.createElement('td');
                title60Cell.className = 'title-text';
                title60Cell.textContent = item.title60 || '-';
                title60Cell.title = item.title60 || '';
                row.appendChild(title60Cell);

                // ACTION column: View + Edit side by side
                const actionCell = document.createElement('td');
                actionCell.className = 'action-buttons-cell';
                actionCell.innerHTML = '<div class="action-buttons-group">' +
                    '<button type="button" class="action-btn view-btn" data-sku="' + escapeHtml(item.SKU) + '" title="View title details"><i class="fas fa-eye"></i> View</button>' +
                    '<button type="button" class="action-btn edit-btn" data-sku="' + escapeHtml(item.SKU) + '" title="Edit title"><i class="fas fa-edit"></i> Edit</button>' +
                    '</div>';
                row.appendChild(actionCell);

                // MARKETPLACES (150): Amazon, Temu, Reverb only
                const marketplaces150Cell = document.createElement('td');
                marketplaces150Cell.className = 'marketplaces-cell marketplaces-150-cell';
                const skuEscaped = escapeHtml(item.SKU);
                const hasTitle150 = !!(item.amazon_title && String(item.amazon_title).trim() !== '');
                let mp150Html = '<div class="marketplaces-dots-wrapper">' +
                    renderMarketplaceDots(skuEscaped, null) +
                    '</div>';
                mp150Html += '<div class="marketplace-buttons">';
                mp150Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-150 btn-amazon" data-sku="' + skuEscaped + '" data-marketplace="amazon" data-title-type="150" title="Amazon (Title 150)" ' + (hasTitle150 ? '' : 'disabled') + '><i class="fab fa-amazon"></i></button>';
                mp150Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-150 btn-temu" data-sku="' + skuEscaped + '" data-marketplace="temu" data-title-type="150" title="Temu (Title 150)" ' + (hasTitle150 ? '' : 'disabled') + '>T</button>';
                mp150Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-150 btn-reverb" data-sku="' + skuEscaped + '" data-marketplace="reverb" data-title-type="150" title="Reverb (Title 150)" ' + (hasTitle150 ? '' : 'disabled') + '><i class="fas fa-guitar"></i></button>';
                mp150Html += '</div>';
                marketplaces150Cell.innerHTML = mp150Html;
                row.appendChild(marketplaces150Cell);

                // MARKETPLACES (100): Shopify Main, PLS + Macy's (Title 60 push)
                const marketplaces100Cell = document.createElement('td');
                marketplaces100Cell.className = 'marketplaces-100-cell';
                const hasTitle100 = !!(item.title100 && String(item.title100).trim() !== '');
                const hasTitle60 = !!(item.title60 && String(item.title60).trim() !== '');
                let mp100Html = '<div class="marketplaces-dots-wrapper marketplaces-dots-100" data-sku="' + skuEscaped + '">' + renderMarketplaceDots100(skuEscaped, null) + '</div>';
                mp100Html += '<div class="marketplace-buttons">';
                mp100Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-100 btn-shopify-pls" data-sku="' + skuEscaped + '" data-marketplace="shopify_pls" data-title-type="100" title="Push Title 100 to ProLight Sounds" ' + (hasTitle100 ? '' : 'disabled') + '>PLS</button>';
                mp100Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-100 btn-shopify-main" data-sku="' + skuEscaped + '" data-marketplace="shopify_main" data-title-type="100" title="Push Title 100 to Main Shopify" ' + (hasTitle100 ? '' : 'disabled') + '><i class="fab fa-shopify"></i></button>';
                mp100Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-100 btn-macy" data-sku="' + skuEscaped + '" data-marketplace="macy" data-title-type="60" title="Push Title 60 to Macy&#39;s" ' + (hasTitle60 ? '' : 'disabled') + '>M</button>';
                mp100Html += '</div>';
                marketplaces100Cell.innerHTML = mp100Html;
                row.appendChild(marketplaces100Cell);

                // MARKETPLACES (80): status dots + E1, E2, E3 buttons (eBay 1, 2, 3)
                const marketplaces80Cell = document.createElement('td');
                marketplaces80Cell.className = 'marketplaces-80-cell';
                const hasTitle80 = !!(item.title80 && String(item.title80).trim() !== '');
                let mp80Html = '<div class="marketplaces-dots-wrapper marketplaces-dots-80" data-sku="' + skuEscaped + '">' + renderMarketplaceDots80(skuEscaped, null) + '</div>';
                mp80Html += '<div class="marketplace-buttons">';
                mp80Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-80 btn-ebay1" data-sku="' + skuEscaped + '" data-marketplace="ebay1" data-title-type="80" title="Push Title 80 to eBay Account 1 (AmarjitK)" ' + (hasTitle80 ? '' : 'disabled') + '>E1</button>';
                mp80Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-80 btn-ebay2" data-sku="' + skuEscaped + '" data-marketplace="ebay2" data-title-type="80" title="Push Title 80 to eBay Account 2 (ProLight)" ' + (hasTitle80 ? '' : 'disabled') + '>E2</button>';
                mp80Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-80 btn-ebay3" data-sku="' + skuEscaped + '" data-marketplace="ebay3" data-title-type="80" title="Push Title 80 to eBay Account 3 (KaneerKa)" ' + (hasTitle80 ? '' : 'disabled') + '>E3</button>';
                mp80Html += '</div>';
                marketplaces80Cell.innerHTML = mp80Html;
                row.appendChild(marketplaces80Cell);

                // PUSH TO ALL MARKETPLACES column
                const pushCell = document.createElement('td');
                pushCell.className = 'push-button-cell';
                pushCell.innerHTML = '<button type="button" class="action-btn push-amazon-btn push-all-marketplaces-btn" data-sku="' + escapeHtml(item.SKU) + '" title="Push Title 150 to Amazon, Temu, Reverb"><i class="fas fa-cloud-upload-alt"></i> Push to All</button>';
                row.appendChild(pushCell);

                frag.appendChild(row);
            });

            tbody.innerHTML = '';
            tbody.appendChild(frag);

            setupEditButtons();
            setupViewButtons();
            setupPushAmazonButtons();
            setupIndividualMarketplaceButtons();
            updateSelectedCount();
            initMarketplaceTooltips(document.getElementById('title-master-table'));
        }

        const marketplaceLabels = {
            amazon: 'Amazon',
            temu: 'Temu',
            reverb: 'Reverb',
            wayfair: 'Wayfair',
            walmart: 'Walmart',
            shopify: 'Shopify',
            shopify_main: 'Shopify',
            shopify_pls: 'PLS',
            doba: 'Doba',
            ebay1: 'eBay 1 (AmarjitK)',
            ebay2: 'eBay 2 (ProLight)',
            ebay3: 'eBay 3 (KaneerKa)',
            macy: "Macy's",
            faire: 'Faire',
        };

        function setupIndividualMarketplaceButtons() {
            document.querySelectorAll('.marketplace-btn-150, .marketplace-btn-100, .marketplace-btn-80, .marketplace-btn-60').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const button = this;
                    const sku = button.getAttribute('data-sku');
                    const marketplace = button.getAttribute('data-marketplace');
                    const titleType = button.getAttribute('data-title-type'); // '150', '100', or '80'
                    const marketplaceName = marketplaceLabels[marketplace] || marketplace.toUpperCase();

                    const item = tableData.find(x => x.SKU === sku);
                    let title = '';
                    if (item) {
                        if (titleType === '150') title = item.amazon_title || '';
                        else if (titleType === '100') title = item.title100 || '';
                        else if (titleType === '80') title = item.title80 || '';
                        else if (titleType === '60') title = item.title60 || '';
                    }

                    if (!title || String(title).trim() === '') {
                        if (typeof showToast === 'function') {
                            showToast('error', `No Title ${titleType} available for SKU ${sku}.`);
                        } else {
                            alert(`No Title ${titleType} available for SKU ${sku}.`);
                        }
                        return;
                    }

                    if (marketplace === 'ebay3') {
                        if (!confirm('Warning! This is a Variation Platform, ARE YOU SURE?')) {
                            return;
                        }
                    }

                    const originalHtml = button.innerHTML;
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                    console.log(`🖱️ Pushing ${titleType} to ${marketplaceName}`, { sku, title: String(title).substring(0, 50) });

                    if (typeof showToast === 'function') {
                        // 0 duration hint for persistent loading toast; ignored if not supported
                        showToast('info', `⏳ Pushing Title ${titleType} to ${marketplaceName}...`, 0);
                    }

                    const row = button.closest('tr');

                    fetch('/api/marketplaces/push-single', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            sku: sku,
                            marketplace: marketplace,
                            title_type: titleType,
                            title: title
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (typeof showToast === 'function') {
                                    showToast('success', `✅ ${marketplaceName} (Title ${titleType}) updated for ${sku}`);
                                }
                                console.log('✅ Push successful', data);
                                if (data.statuses && row) {
                                    if (marketplace === 'ebay1' || marketplace === 'ebay2' || marketplace === 'ebay3') {
                                        const wrapper80 = row.querySelector('.marketplaces-dots-80');
                                        if (wrapper80) {
                                            const statusMap80 = {};
                                            ['ebay1', 'ebay2', 'ebay3'].forEach(function(mp) {
                                                statusMap80[mp] = (data.statuses[mp] && data.statuses[mp].status) ? data.statuses[mp].status : 'pending';
                                            });
                                            wrapper80.innerHTML = renderMarketplaceDots80(sku, statusMap80);
                                            initMarketplaceTooltips(wrapper80);
                                        }
                                    } else if (marketplace === 'macy' || marketplace === 'shopify' || marketplace === 'shopify_main' || marketplace === 'shopify_pls') {
                                        updateMarketplaceDots100InRow(row, data.statuses);
                                    } else {
                                        updateMarketplaceDotsInRow(row, data.statuses);
                                    }
                                }
                            } else {
                                const msg = data.message || 'Unknown error';
                                if (typeof showToast === 'function') {
                                    showToast('error', `❌ ${marketplaceName} (Title ${titleType}) failed: ${msg}`);
                                }
                                console.error('❌ Push failed', data);
                            }
                        })
                        .catch(error => {
                            if (typeof showToast === 'function') {
                                showToast('error', `❌ ${marketplaceName} push error: ${error.message}`);
                            }
                            console.error('❌ Push error', error);
                        })
                        .finally(() => {
                            button.disabled = false;
                            button.innerHTML = originalHtml;
                        });
                });
            });
        }

        function setupEditButtons() {
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const sku = this.getAttribute('data-sku');
                    openModal('edit', sku);
                });
            });
        }

        function setupViewButtons() {
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const sku = this.getAttribute('data-sku');
                    openViewModal(sku);
                });
            });
        }

        function setupPushAmazonButtons() {
            document.querySelectorAll('.push-amazon-btn, .push-all-marketplaces-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const sku = this.getAttribute('data-sku');
                    const modalSku = (document.getElementById('editSku') && document.getElementById('editSku').value) || (document.getElementById('selectSku') && document.getElementById('selectSku').value) || '';
                    const title150Input = document.getElementById('title150');
                    let title = '';
                    if (modalSku === sku && title150Input && title150Input.value.trim()) {
                        title = title150Input.value.trim();
                    }
                    if (!title) {
                        const item = tableData.find(d => d.SKU === sku);
                        title = item ? (item.amazon_title || item.title150 || '').toString().trim() : '';
                    }
                    if (!title) {
                        alert('No title to push. Edit the row and set Title 150 first.');
                        return;
                    }
                    pushToAllMarketplaces(this, sku, title);
                });
            });
        }

        function pushToAllMarketplaces(btn, sku, title) {
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
            const row = btn.closest('tr');
            if (row) {
                const cell = row.querySelector('.marketplaces-150-cell');
                const wrap = cell ? cell.querySelector('.marketplaces-dots-wrapper') : null;
                if (wrap) {
                    wrap.innerHTML = renderMarketplaceDots(sku, { amazon: 'loading', temu: 'loading', reverb: 'loading' });
                    initMarketplaceTooltips(wrap);
                }
            }

            fetch('/api/marketplaces/push-title', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ sku: sku, title: title })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.results) {
                    if (row) updateMarketplaceDotsInRow(row, data.results);
                    const r = data.results;
                    const ok = [r.amazon, r.temu, r.reverb].filter(x => x && x.status === 'success').length;
                    const fail = [r.amazon, r.temu, r.reverb].filter(x => x && x.status === 'failed').length;
                    alert('Push completed for ' + sku + ': ' + ok + ' succeeded, ' + fail + ' failed.');
                } else {
                    if (row) {
                        const cell = row.querySelector('.marketplaces-150-cell');
                        const wrap = cell ? cell.querySelector('.marketplaces-dots-wrapper') : null;
                        if (wrap) {
                            wrap.innerHTML = renderMarketplaceDots(sku, null);
                            initMarketplaceTooltips(wrap);
                        }
                    }
                    alert('Failed: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                if (row) {
                    const cell = row.querySelector('.marketplaces-150-cell');
                    const wrap = cell ? cell.querySelector('.marketplaces-dots-wrapper') : null;
                    if (wrap) {
                        wrap.innerHTML = renderMarketplaceDots(sku, null);
                        initMarketplaceTooltips(wrap);
                    }
                }
                alert('Error: ' + err.message);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
        }

        function runPushBulk(items) {
            const batchSize = 5;
            const total = items.length;
            let successCount = 0;
            let failedCount = 0;
            const progressModal = new bootstrap.Modal(document.getElementById('pushProgressModal'));
            const progressBar = document.getElementById('pushProgressBar');
            const progressText = document.getElementById('pushProgressText');
            progressModal.show();
            progressText.textContent = 'Pushing to Amazon, Temu, Reverb...';

            function updateProgress(done) {
                const pct = total ? Math.round((done / total) * 100) : 0;
                progressBar.style.width = pct + '%';
                progressBar.textContent = pct + '%';
                progressText.textContent = 'Pushing ' + done + '/' + total + ' to all marketplaces...';
            }

            function updateRowDots(sku, results) {
                const btn = document.querySelector('.push-all-marketplaces-btn[data-sku="' + sku + '"]');
                if (btn && btn.closest('tr')) updateMarketplaceDotsInRow(btn.closest('tr'), results);
            }

            function doNext(start) {
                if (start >= total) {
                    progressModal.hide();
                    alert(successCount + ' successful, ' + failedCount + ' failed (Amazon, Temu, Reverb)');
                    document.querySelectorAll('.row-checkbox:checked').forEach(function(cb) { cb.checked = false; });
                    document.getElementById('selectAll').checked = false;
                    updateSelectedCount();
                    return;
                }
                const batch = items.slice(start, start + batchSize);
                const skus = batch.map(function(x) { return x.sku; });
                const titles = {};
                batch.forEach(function(x) { titles[x.sku] = x.title; });
                fetch('/api/marketplaces/push-bulk', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ skus: skus, titles: titles })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    successCount += data.success_count || 0;
                    failedCount += data.failed_count || 0;
                    const perSku = data.per_sku_results || {};
                    Object.keys(perSku).forEach(function(sku) {
                        if (perSku[sku] && !perSku[sku].error) updateRowDots(sku, perSku[sku]);
                    });
                    updateProgress(Math.min(start + batchSize, total));
                    doNext(start + batchSize);
                })
                .catch(function(err) {
                    failedCount += batch.length;
                    updateProgress(Math.min(start + batchSize, total));
                    doNext(start + batchSize);
                });
            }

            updateProgress(0);
            doNext(0);
        }

        function openViewModal(sku) {
            const item = tableData.find(d => d.SKU === sku);
            if (!item) {
                showError('Product not found');
                return;
            }

            // Populate view modal
            const viewImage = document.getElementById('viewImage');
            if (item.image_path) {
                viewImage.innerHTML = '<img src="' + escapeHtml(item.image_path) + '" style="width:80px;height:80px;object-fit:cover;border-radius:4px;">';
            } else {
                viewImage.innerHTML = '<span class="text-muted">No image</span>';
            }

            document.getElementById('viewSku').textContent = escapeHtml(item.SKU) || '-';
            document.getElementById('viewParent').textContent = escapeHtml(item.Parent) || '-';
            document.getElementById('viewTitle150').textContent = (item.amazon_title != null && item.amazon_title !== '') ? item.amazon_title : (item.title150 || '-');
            document.getElementById('viewTitle100').textContent = item.title100 || '-';
            document.getElementById('viewTitle80').textContent = item.title80 || '-';
            document.getElementById('viewTitle60').textContent = item.title60 || '-';

            const viewModal = new bootstrap.Modal(document.getElementById('viewTitleModal'));
            viewModal.show();
        }

        function openModal(mode, sku = null) {
            const modal = document.getElementById('titleModal');
            const modalTitle = document.getElementById('modalTitle');
            const selectSku = document.getElementById('selectSku');
            const editSku = document.getElementById('editSku');

            function attachSelect2DestroyOnHide() {
                const modalElement = document.getElementById('titleModal');
                modalElement.addEventListener('hidden.bs.modal', function() {
                    if ($(selectSku).hasClass('select2-hidden-accessible')) {
                        $(selectSku).select2('destroy');
                    }
                }, { once: true });
            }

            // Reset form
            document.getElementById('titleForm').reset();
            ['title150', 'title100', 'title80', 'title60'].forEach(field => {
                const maxLength = parseInt(field.replace('title', ''));
                document.getElementById('counter' + maxLength).textContent = '0/' + maxLength;
                document.getElementById('counter' + maxLength).classList.remove('error');
            });

            if (mode === 'add') {
                modalTitle.textContent = 'Add Title';
                selectSku.style.display = 'block';
                selectSku.required = true;
                editSku.value = '';

                if ($(selectSku).hasClass('select2-hidden-accessible')) {
                    $(selectSku).select2('destroy');
                }

                selectSku.innerHTML = '<option value="">Loading SKUs...</option>';

                fetch('/title-master/sku-options', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        const skus = resp.data || [];
                        selectSku.innerHTML = '<option value="">Choose SKU...</option>';
                        skus.forEach(function(skuVal) {
                            const s = String(skuVal);
                            if (s && !s.toUpperCase().includes('PARENT')) {
                                selectSku.innerHTML += '<option value="' + escapeHtml(s) + '">' + escapeHtml(s) + '</option>';
                            }
                        });
                    })
                    .catch(function(err) {
                        console.error('sku-options:', err);
                        selectSku.innerHTML = '<option value="">Choose SKU...</option>';
                        tableData.forEach(function(item) {
                            if (item.SKU && !item.SKU.toUpperCase().includes('PARENT')) {
                                selectSku.innerHTML += '<option value="' + escapeHtml(item.SKU) + '">' + escapeHtml(item.SKU) + '</option>';
                            }
                        });
                    })
                    .finally(function() {
                        $(selectSku).select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Choose SKU...',
                            allowClear: true,
                            width: '100%',
                            dropdownParent: $('#titleModal')
                        });
                        attachSelect2DestroyOnHide();
                        titleModal.show();
                    });
                return;
            } else if (mode === 'edit' && sku) {
                modalTitle.textContent = 'Edit Title';
                selectSku.style.display = 'none';
                selectSku.required = false;
                editSku.value = sku;
                
                // Destroy Select2 if initialized
                if ($(selectSku).hasClass('select2-hidden-accessible')) {
                    $(selectSku).select2('destroy');
                }
                
                // Load existing data (Title 150 = Amazon title; Title 100/80/60 = Product Master)
                const item = tableData.find(d => d.SKU === sku);
                if (item) {
                    const title150Input = document.getElementById('title150');
                    const cleanTitle150 = (item.amazon_title != null && item.amazon_title !== '') ? item.amazon_title : (item.title150 || '');
                    title150Input.value = cleanTitle150;
                    const len150 = cleanTitle150.length;
                    const counter150 = document.getElementById('counter150');
                    counter150.textContent = len150 + '/150';
                    if (len150 > 150) counter150.classList.add('error'); else counter150.classList.remove('error');
                    ['title100', 'title80', 'title60'].forEach(field => {
                        const input = document.getElementById(field);
                        input.value = item[field] || '';
                        const maxLength = parseInt(field.replace('title', ''));
                        const length = input.value.length;
                        document.getElementById('counter' + maxLength).textContent = length + '/' + maxLength;
                    });
                }
            }

            attachSelect2DestroyOnHide();
            titleModal.show();
        }

        function resetTitleModalForm() {
            const form = document.getElementById('titleForm');
            if (form) {
                form.reset();
            }
            const editSku = document.getElementById('editSku');
            const selectSku = document.getElementById('selectSku');
            if (editSku) {
                editSku.value = '';
            }
            if (selectSku) {
                if (typeof $ !== 'undefined' && $(selectSku).hasClass('select2-hidden-accessible')) {
                    $(selectSku).val(null).trigger('change');
                }
                selectSku.selectedIndex = 0;
            }
            ['title150', 'title100', 'title80', 'title60'].forEach(function(field) {
                const maxLength = field === 'title150' ? 150 : parseInt(field.replace('title', ''), 10);
                const counter = document.getElementById('counter' + maxLength);
                if (counter) {
                    counter.textContent = '0/' + (field === 'title150' ? 150 : maxLength);
                    counter.classList.remove('error');
                }
            });
        }

        function saveTitleFromModal() {
            const form = document.getElementById('titleForm');
            const selectSku = document.getElementById('selectSku');
            const editSku = document.getElementById('editSku');
            const sku = editSku.value || selectSku.value;

            if (!sku) {
                alert('Please select a SKU');
                return;
            }

            const title150 = document.getElementById('title150').value;
            const title100 = document.getElementById('title100').value;
            const title80 = document.getElementById('title80').value;
            const title60 = document.getElementById('title60').value;

            const saveBtn = document.getElementById('saveTitleBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            fetch('/title-master/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    sku: sku,
                    title150: title150,
                    title100: title100,
                    title80: title80,
                    title60: title60
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const index = tableData.findIndex(item => item.SKU === sku);
                    if (index !== -1) {
                        tableData[index].title150 = title150;
                        tableData[index].amazon_title = title150;
                        tableData[index].title100 = title100;
                        tableData[index].title80 = title80;
                        tableData[index].title60 = title60;
                    }
                    titleModal.hide();
                    resetTitleModalForm();
                    loadTitleData(listMeta.current_page || 1);
                    if (typeof showToast === 'function') {
                        showToast('success', 'Titles saved for ' + sku + '.');
                    } else {
                        alert('Title saved successfully!');
                    }
                } else {
                    alert(data.message || 'Failed to save title');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving title: ' + error.message);
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save';
            });
        }

        function exportToExcel() {
            const params = buildTitleMasterQueryParams(1);
            params.set('export', '1');
            const loader = document.getElementById('rainbow-loader');
            if (loader) loader.style.display = 'block';

            fetch('/title-master-data?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function(response) {
                    if (!response.ok) throw new Error('Export request failed');
                    return response.json();
                })
                .then(function(response) {
                    const rows = response.data;
                    if (!Array.isArray(rows)) {
                        throw new Error('Invalid export data');
                    }
                    const exportData = rows
                        .filter(function(item) {
                            return item.SKU && !String(item.SKU).toUpperCase().includes('PARENT');
                        })
                        .map(function(item) {
                            return {
                                'Parent': item.Parent || '',
                                'SKU': item.SKU || '',
                                'Title 150': (item.amazon_title != null && item.amazon_title !== '') ? item.amazon_title : (item.title150 || ''),
                                'Title 100': item.title100 || '',
                                'Title 80': item.title80 || '',
                                'Title 60': item.title60 || ''
                            };
                        });

                    const ws = XLSX.utils.json_to_sheet(exportData);
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Titles');
                    XLSX.writeFile(wb, 'title_master_' + new Date().toISOString().split('T')[0] + '.xlsx');
                })
                .catch(function(err) {
                    console.error(err);
                    alert('Export failed: ' + (err.message || 'Unknown error'));
                })
                .finally(function() {
                    if (loader) loader.style.display = 'none';
                });
        }

        function importFromExcel(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    
                    // First, try to read with default (row 0 as header)
                    let jsonData = XLSX.utils.sheet_to_json(firstSheet);
                    
                    console.log('First attempt - columns:', Object.keys(jsonData[0] || {}));
                    
                    // If we get __EMPTY columns, try reading from row 1 as header
                    const firstCol = Object.keys(jsonData[0] || {})[0];
                    if (firstCol && firstCol.includes('__EMPTY')) {
                        console.log('Detected merged cells or empty headers, trying range option...');
                        // Try reading with header at row 1 (index 1)
                        jsonData = XLSX.utils.sheet_to_json(firstSheet, { range: 1 });
                        console.log('Second attempt - columns:', Object.keys(jsonData[0] || {}));
                    }
                    
                    // Still empty? Try raw data approach
                    if (!jsonData || jsonData.length === 0 || Object.keys(jsonData[0])[0].includes('__EMPTY')) {
                        console.log('Still getting empty columns, reading as raw array...');
                        const rawData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });
                        console.log('Raw data first 3 rows:', rawData.slice(0, 3));
                        
                        // Find the header row (first row with non-empty values)
                        let headerRowIndex = -1;
                        for (let i = 0; i < Math.min(5, rawData.length); i++) {
                            const row = rawData[i];
                            if (row && row.some(cell => cell && cell.toString().trim() !== '')) {
                                headerRowIndex = i;
                                console.log('Found header row at index:', i, 'Values:', row);
                                break;
                            }
                        }
                        
                        if (headerRowIndex >= 0) {
                            // Convert to proper JSON with detected headers
                            const headers = rawData[headerRowIndex];
                            jsonData = [];
                            for (let i = headerRowIndex + 1; i < rawData.length; i++) {
                                const row = rawData[i];
                                if (!row || row.length === 0) continue;
                                
                                const obj = {};
                                for (let j = 0; j < headers.length; j++) {
                                    const header = headers[j] || 'Column_' + j;
                                    obj[header] = row[j];
                                }
                                jsonData.push(obj);
                            }
                            console.log('Converted data - columns:', Object.keys(jsonData[0] || {}));
                            console.log('First data row:', jsonData[0]);
                        }
                    }

                    if (jsonData.length === 0) {
                        alert('No data found in the file');
                        return;
                    }

                    console.log('Final Excel data loaded!');
                    console.log('Total rows:', jsonData.length);
                    console.log('Columns:', Object.keys(jsonData[0]));
                    console.log('First 3 rows:', jsonData.slice(0, 3));
                    
                    // Show user what columns we found
                    const cols = Object.keys(jsonData[0]).join(', ');
                    const proceed = confirm('Found ' + jsonData.length + ' rows with these columns:\n\n' + cols + '\n\nProceed with import?');
                    
                    if (proceed) {
                        // Process and save imported data
                        processImportedData(jsonData);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error reading file: ' + error.message);
                }
            };
            reader.readAsArrayBuffer(file);
            document.getElementById('importFile').value = ''; // Reset file input
        }

        function processImportedData(jsonData) {
            let successCount = 0;
            let errorCount = 0;
            let skippedCount = 0;
            const totalRows = jsonData.length;
            const errors = [];

            // Log first row to see column names
            if (jsonData.length > 0) {
                console.log('=== EXCEL COLUMNS FOUND ===');
                console.log('All columns:', Object.keys(jsonData[0]));
                console.log('First row full data:', jsonData[0]);
                console.log('Second row data:', jsonData[1]);
            }

            // Detect SKU column dynamically - try all columns to find one with SKU-like data
            let skuColumnName = null;
            if (jsonData.length > 0) {
                const firstRow = jsonData[0];
                
                // First priority: columns with 'sku' or 'child' in name
                for (const colName of Object.keys(firstRow)) {
                    const lower = colName.toLowerCase();
                    if ((lower.includes('sku') || lower.includes('child')) && 
                        firstRow[colName] && 
                        firstRow[colName].toString().trim() !== '' &&
                        firstRow[colName].toString().trim() !== '__EMPTY' &&
                        firstRow[colName].toString().trim() !== '0') {
                        skuColumnName = colName;
                        console.log('✓ Found SKU column (priority): "' + skuColumnName + '" = "' + firstRow[colName] + '"');
                        break;
                    }
                }
                
                // Second priority: any column with actual data that looks like SKU
                if (!skuColumnName) {
                    for (const [colName, value] of Object.entries(firstRow)) {
                        const val = value ? value.toString().trim() : '';
                        if (val && val !== '__EMPTY' && val !== '0' && val !== '' && val.length > 2) {
                            skuColumnName = colName;
                            console.log('✓ Found SKU column (fallback): "' + skuColumnName + '" = "' + val + '"');
                            break;
                        }
                    }
                }
            }

            if (!skuColumnName) {
                console.error('❌ Available columns:', Object.keys(jsonData[0]));
                console.error('❌ First row values:', Object.values(jsonData[0]));
                alert('Error: Could not detect SKU column in Excel file.\nPlease check the console for available columns and their values.');
                return;
            }

            // Detect title columns - more flexible matching
            const titleColumns = {
                title150: null,
                title100: null,
                title80: null,
                title60: null
            };

            if (jsonData.length > 0) {
                const columns = Object.keys(jsonData[0]);
                for (const colName of columns) {
                    const lower = colName.toLowerCase();
                    
                    // Match Amazon/150 column
                    if (!titleColumns.title150 && (lower.includes('amazon') || lower.includes('150'))) {
                        titleColumns.title150 = colName;
                    }
                    // Match Shopify/100 column
                    else if (!titleColumns.title100 && (lower.includes('shopify') || (lower.includes('100') && !lower.includes('150')))) {
                        titleColumns.title100 = colName;
                    }
                    // Match eBay/80 column
                    else if (!titleColumns.title80 && (lower.includes('ebay') || (lower.includes('80') && !lower.includes('180')))) {
                        titleColumns.title80 = colName;
                    }
                    // Match Faire/60 column
                    else if (!titleColumns.title60 && (lower.includes('faire') || (lower.includes('60') && !lower.includes('160')))) {
                        titleColumns.title60 = colName;
                    }
                }
                console.log('✓ Detected title columns:', titleColumns);
            }

            const saveJobs = [];
            jsonData.forEach((row, index) => {
                const sku = row[skuColumnName];
                const skuStr = sku ? sku.toString().trim() : '';
                const isParentSKU = /\bPARENT\b/i.test(skuStr);

                if (!sku || skuStr === '' || sku === '__EMPTY' || skuStr === '0' || isParentSKU) {
                    skippedCount++;
                    if (skippedCount <= 3) {
                        const reason = !sku || skuStr === '' ? 'Empty' : isParentSKU ? 'Parent' : 'Invalid';
                        console.log('⊘ Skipped row ' + (index + 2) + ': "' + skuStr + '" (' + reason + ')');
                    }
                    return;
                }

                const title150 = titleColumns.title150 ? (row[titleColumns.title150] || '').toString().substring(0, 150) : '';
                const title100 = titleColumns.title100 ? (row[titleColumns.title100] || '').toString().substring(0, 100) : '';
                const title80 = titleColumns.title80 ? (row[titleColumns.title80] || '').toString().substring(0, 80) : '';
                const title60 = titleColumns.title60 ? (row[titleColumns.title60] || '').toString().substring(0, 60) : '';

                if (successCount + errorCount < 3) {
                    console.log('→ Processing row ' + (index + 2) + ': SKU="' + skuStr + '"');
                }

                saveJobs.push(function() {
                    return fetch('/title-master/save', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            sku: skuStr,
                            title150: title150,
                            title100: title100,
                            title80: title80,
                            title60: title60
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            successCount++;
                            if (successCount <= 3) {
                                console.log('✓ Row ' + (index + 2) + ' success: ' + skuStr);
                            }
                        } else {
                            errorCount++;
                            const errorMsg = 'Row ' + (index + 2) + ' (' + skuStr + '): ' + (data.message || 'Unknown error');
                            if (errorCount <= 10) {
                                console.error('✗ ' + errorMsg);
                                errors.push(errorMsg);
                            }
                        }
                    })
                    .catch(err => {
                        errorCount++;
                        const errorMsg = 'Row ' + (index + 2) + ' (' + skuStr + '): ' + err.message;
                        if (errorCount <= 10) {
                            console.error('✗ ' + errorMsg);
                            errors.push(errorMsg);
                        }
                    });
                });
            });

            const importConcurrency = 8;
            (async function runImportBatches() {
                for (let i = 0; i < saveJobs.length; i += importConcurrency) {
                    const batch = saveJobs.slice(i, i + importConcurrency).map(function(fn) { return fn(); });
                    await Promise.all(batch);
                }

                let message = `Import completed!\n\nSuccess: ${successCount}\nErrors: ${errorCount}\nSkipped (Parent/Empty): ${skippedCount}\nTotal: ${totalRows}`;

                if (errors.length > 0) {
                    message += '\n\nFirst errors:\n' + errors.join('\n');
                }

                console.log('=== IMPORT SUMMARY ===');
                console.log('Success:', successCount);
                console.log('Errors:', errorCount);
                console.log('Skipped:', skippedCount);
                console.log('Total:', totalRows);

                alert(message);

                if (successCount > 0) {
                    loadTitleData(1);
                }
            })();
        }

        let titleMasterFilterDebounce = null;

        function scheduleApplyFilters() {
            if (titleMasterFilterDebounce) {
                clearTimeout(titleMasterFilterDebounce);
            }
            titleMasterFilterDebounce = setTimeout(function() {
                titleMasterFilterDebounce = null;
                loadTitleData(1);
            }, 500);
        }

        /** Server-side filters — reload page 1 */
        function applyFilters() {
            loadTitleData(1);
        }

        function setupSearchHandlers() {
            const parentSearch = document.getElementById('parentSearch');
            const skuSearch = document.getElementById('skuSearch');

            parentSearch.addEventListener('input', scheduleApplyFilters);
            skuSearch.addEventListener('input', scheduleApplyFilters);

            document.getElementById('filterTitle150').addEventListener('change', function() {
                loadTitleData(1);
            });

            document.getElementById('filterTitle100').addEventListener('change', function() {
                loadTitleData(1);
            });

            document.getElementById('filterTitle80').addEventListener('change', function() {
                loadTitleData(1);
            });

            document.getElementById('filterTitle60').addEventListener('change', function() {
                loadTitleData(1);
            });
        }

        function filterTable() {
            loadTitleData(1);
        }

        // Check if value is missing (null, undefined, empty)
        function isMissing(value) {
            return value === null || value === undefined || value === '' || (typeof value === 'string' && value.trim() === '');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function showError(message) {
            alert(message);
        }
        @endverbatim
    </script>
@endsection
