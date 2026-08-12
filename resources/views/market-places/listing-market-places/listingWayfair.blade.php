@extends('layouts.vertical', ['title' => 'Listing Wayfair', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* ========== TABLE SHELL ========== */
        #wayfair-listing-wrap {
            overflow-x: auto;
            overflow-y: visible;
            width: 100%;
        }

        #wayfair-listing-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
            width: 100% !important;
        }

        .card-body:has(#wayfair-listing-toolbar) {
            width: 100%;
        }

        #wayfair-listing-wrap .tabulator .tabulator-tableholder {
            background: #fff;
        }

        /* ========== HEADER ========== */
        #wayfair-listing-wrap .tabulator .tabulator-header {
            background: #00d5d5;
            border-bottom: 1px solid #ffffff;
        }

        #wayfair-listing-wrap .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        #wayfair-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #wayfair-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: normal !important;
        }

        #wayfair-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: normal !important;
            height: auto !important;
            min-height: 0 !important;
            display: block;
            align-items: unset;
            justify-content: unset;
            font-size: 12.5px;
            font-weight: 700;
            line-height: 1.25;
            padding: 5px 2px;
            text-align: center;
            color: #000 !important;
        }

        #wayfair-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: auto !important;
            min-height: 34px;
            padding: 0;
        }

        #wayfair-listing-wrap .tabulator .tabulator-header .tabulator-col {
            height: auto !important;
            min-height: 34px;
            vertical-align: middle;
            background: #00d5d5 !important;
            border-right: 1px solid #ffffff;
            color: #000 !important;
            font-weight: bold;
        }

        #wayfair-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder {
            padding-left: 2px !important;
            padding-right: 2px !important;
        }

        /* Header filters */
        #wayfair-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-header-filter input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 12px;
            color: #475569;
            background: #fff;
            box-shadow: none;
        }

        #wayfair-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-header-filter input:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.15);
        }

        /* ========== ROWS / CELLS ========== */
        #wayfair-listing-wrap .tabulator .tabulator-row {
            min-height: 36px;
            border-bottom: 1px solid #f1f5f9;
        }

        #wayfair-listing-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 5px 6px !important;
            border-right: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        #wayfair-listing-wrap .tabulator-row .tabulator-cell input[type="checkbox"],
        #wayfair-listing-wrap .tabulator-header .tabulator-col input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #4361ee;
            margin: 0;
            vertical-align: middle;
        }

        #wayfair-listing-wrap .tabulator-row.parent-row .tabulator-cell input[type="checkbox"] {
            display: none;
        }

        #wayfair-listing-wrap .tabulator .tabulator-row:hover {
            background-color: #f8fafc !important;
        }

        #wayfair-listing-wrap .tabulator .tabulator-row.tabulator-row-even {
            background-color: #fcfcfd;
        }

        #wayfair-listing-wrap .tabulator-row.parent-row,
        #wayfair-listing-wrap .tabulator-row.parent-row .tabulator-cell {
            background-color: #fffef2 !important;
            font-weight: 700 !important;
            color: #0f172a;
        }

        #wayfair-listing-wrap .tabulator-row.parent-row:hover,
        #wayfair-listing-wrap .tabulator-row.parent-row:hover .tabulator-cell {
            background-color: #fefce8 !important;
        }

        /* ========== FOOTER / PAGINATION ========== */
        #wayfair-listing-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }

        #wayfair-listing-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        #wayfair-listing-wrap .tabulator .tabulator-footer .tabulator-paginator label {
            margin-right: 6px;
            font-size: 12px;
            color: #475569;
            font-weight: 600;
        }

        #wayfair-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page-size {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 13px;
            color: #475569;
            background: #fff;
            min-height: 36px;
        }

        #wayfair-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important;
            font-weight: 500 !important;
            min-width: 36px !important;
            height: 36px !important;
            line-height: 36px !important;
            padding: 0 10px !important;
            border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important;
            background: #fff !important;
            color: #475569 !important;
            cursor: pointer;
            transition: all 0.15s ease !important;
            text-align: center !important;
        }

        #wayfair-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
        }

        #wayfair-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important;
            border-color: #4361ee !important;
            color: #fff !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67, 97, 238, 0.3) !important;
        }

        #wayfair-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important;
            cursor: not-allowed !important;
        }

        #wayfair-listing-wrap .tabulator .tabulator-footer .tabulator-page-counter {
            margin: 0 0.5rem;
            font-size: 12px;
            color: #334155;
        }

        /* ========== TOOLBAR (badges row + filters row, fit page, keep font) ========== */
        #wayfair-listing-toolbar {
            background: transparent;
            border: none;
            border-radius: 0;
            padding: 0;
            overflow: visible;
            width: 100%;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        #wayfair-listing-toolbar .wayfair-listing-toolbar-row {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        #wayfair-listing-toolbar .listing-stat-badges {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            align-items: stretch;
            gap: 6px;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        #wayfair-listing-toolbar .listing-stat-badge {
            flex: none;
            justify-content: center;
            margin: 0 !important;
            min-width: 0;
            width: 100%;
            height: 45px;
            padding: 0 10px;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            overflow: visible;
        }

        #wayfair-listing-toolbar .listing-stat-badge > span {
            font-size: 18px;
            flex-shrink: 0;
        }

        #wayfair-listing-toolbar .listing-toolbar-controls {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            width: 100%;
            min-width: 0;
        }

        #wayfair-listing-toolbar .listing-toolbar-filters {
            display: flex;
            flex: 1 1 auto;
            align-items: center;
            gap: 6px;
            min-width: 0;
        }

        #wayfair-listing-toolbar .filter-select {
            flex: 1 1 0;
            min-width: 0;
            width: auto !important;
            max-width: none;
            border-radius: 5px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #64748b;
            font-size: 16px;
            font-weight: 600;
            padding: 2px 22px 2px 8px;
            height: 45px;
            line-height: 1.2;
        }

        #wayfair-listing-toolbar .filter-select:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.15);
        }

        #wayfair-listing-toolbar .toolbar-actions {
            display: flex;
            flex: 0 0 auto;
            flex-shrink: 0;
            align-items: center;
            gap: 6px;
            margin-left: 0;
        }

        #wayfair-listing-toolbar .verify-listings-btn {
            height: 45px;
            font-size: 16px;
            font-weight: 700;
            white-space: nowrap;
            padding: 0 14px;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        #wayfair-listing-toolbar .listing-io-btn {
            border-radius: 5px;
            font-weight: 600;
            font-size: 21px;
            width: 48px;
            height: 45px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        #wayfair-listing-toolbar .listing-io-btn::after {
            display: none;
        }

        #wayfair-listing-toolbar .listing-io-menu {
            min-width: 42px;
            padding: 4px;
        }

        #wayfair-listing-toolbar .listing-io-menu .dropdown-item {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 32px;
            padding: 0;
            border-radius: 4px;
            font-size: 14px;
        }

        #wayfair-listing-toolbar .listing-io-menu .dropdown-item:hover {
            background: #f1f5f9;
        }

        /* ========== STAT BADGES ========== */
        .listing-stat-badge {
            display: inline-flex;
            align-items: center;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 10px;
            border-radius: 8px;
            white-space: nowrap;
            line-height: 1.2;
            letter-spacing: 0.2px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .listing-stat-badge > span {
            margin-left: 4px;
            font-size: 13px;
            font-weight: 800;
        }

        .listing-stat-badge--nolink { background: #f59e0b; color: #1c1917; }
        .listing-stat-badge--listed { background: #0ea5e9; color: #fff; }
        .listing-stat-badge--inv0 { background: #64748b; color: #fff; }
        .listing-stat-badge--listed,
        .listing-stat-badge--inv0 {
            cursor: pointer;
            user-select: none;
        }
        .listing-stat-badge--listed:hover,
        .listing-stat-badge--inv0:hover {
            filter: brightness(0.95);
        }
        .listing-stat-badge--listed.is-active {
            outline: 2px solid #fff;
            box-shadow: 0 0 0 2px #0284c7;
        }
        .listing-stat-badge--inv0.is-active {
            outline: 2px solid #fff;
            box-shadow: 0 0 0 2px #475569;
        }
        .listing-stat-badge--pending { background: #dc3545; color: #fff; }
        .listing-stat-badge--pending {
            cursor: pointer;
            user-select: none;
        }
        .listing-stat-badge--pending:hover {
            filter: brightness(0.95);
        }
        .listing-stat-badge--pending.is-active {
            outline: 2px solid #fff;
            box-shadow: 0 0 0 2px #dc3545;
        }
        .listing-stat-badge--pending-inv0 { background: #7c3aed; color: #fff; }
        .listing-stat-badge--pending-inv0 {
            cursor: pointer;
            user-select: none;
        }
        .listing-stat-badge--pending-inv0:hover {
            filter: brightness(0.95);
        }
        .listing-stat-badge--pending-inv0.is-active {
            outline: 2px solid #fff;
            box-shadow: 0 0 0 2px #7c3aed;
        }
        .listing-stat-badge--rows { background: #334155; color: #fff; }
        .listing-stat-badge--parents { background: #5eead4; color: #0f172a; }
        .listing-stat-badge--skus { background: #60a5fa; color: #0f172a; }
        .listing-stat-badge--parents,
        .listing-stat-badge--skus {
            cursor: pointer;
            user-select: none;
        }
        .listing-stat-badge--parents:hover,
        .listing-stat-badge--skus:hover {
            filter: brightness(0.95);
        }
        .listing-stat-badge--parents.is-active {
            outline: 2px solid #fff;
            box-shadow: 0 0 0 2px #14b8a6;
        }
        .listing-stat-badge--skus.is-active {
            outline: 2px solid #fff;
            box-shadow: 0 0 0 2px #2563eb;
        }

        /* ========== DROPDOWNS ========== */
        #wayfair-listing-wrap select.listed-dropdown {
            border: 1px solid transparent;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            padding: 4px 6px;
            cursor: pointer;
            appearance: auto;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        #wayfair-listing-wrap select.listed-dropdown:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.25);
        }

        #wayfair-listing-wrap select.listed-dropdown[data-val="Listed"],
        #wayfair-listing-wrap select.listed-dropdown option.listed-option {
            background-color: #28a745;
            color: #fff;
        }

        #wayfair-listing-wrap select.listed-dropdown[data-val="Pending"],
        #wayfair-listing-wrap select.listed-dropdown option.pending-option {
            background-color: #dc3545;
            color: #fff;
        }

        .nrl-badge-btn,
        .listing-auto-badge {
            display: inline-block;
            color: #fff;
            padding: 6px 10px;
            border: none;
            cursor: default;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            border-radius: 6px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
            min-width: 72px;
        }

        .listing-auto-badge--req,
        .listing-auto-badge--listed { background-color: #28a745; }
        .listing-auto-badge--nrl,
        .listing-auto-badge--not-listed { background-color: #dc3545; }
        .listing-auto-badge--not-listed-inv0 { background-color: #7c3aed; }

        .listing-listed-tick {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background-color: #28a745;
            color: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .listing-listed-tick > i {
            font-size: 14px;
            font-weight: 700;
            line-height: 1;
        }

        /* ========== LINK CELL ========== */
        #wayfair-listing-wrap a.listing-item-link {
            font-weight: 600;
            color: #0d6efd;
            text-decoration: none;
        }

        #wayfair-listing-wrap a.listing-item-link:hover {
            color: #1d4ed8 !important;
            text-decoration: underline;
        }

        /* ========== LOADER ========== */
        .card-loader-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.78);
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.375rem;
        }

        .loader-content {
            text-align: center;
            padding: 16px 20px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        .loader-text {
            margin-top: 10px;
            font-weight: 600;
            color: #475569;
        }

        /* ========== PLACEHOLDER ========== */
        #wayfair-listing-wrap .tabulator-placeholder {
            color: #64748b;
            font-weight: 600;
            padding: 24px;
        }

    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => 'Listing Wayfair', 'sub_title' => 'Wayfair'])

    <div class="row">
        <div class="col-12">
            <div class="card position-relative">
                <div class="card-body">
                    <div id="wayfair-listing-toolbar" class="mb-3">
                        <div class="wayfair-listing-toolbar-row">
                            <div class="listing-stat-badges">
                                <span class="listing-stat-badge listing-stat-badge--rows">Rows:<span id="rows-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--parents" id="parent-badge" role="button" tabindex="0" title="Unique parents from CP Master. Click to show Parent rows.">Parent <span id="parent-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--skus" id="sku-badge" role="button" tabindex="0" title="Child SKUs from CP Master. Click to show SKU rows.">SKU <span id="sku-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--nolink">No Link:<span id="without-link-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--listed" id="listed-badge" role="button" tabindex="0" title="Listed on Wayfair, including inv=0. Click to show listed SKUs.">Listed:<span id="listed-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--inv0" id="inv0-badge" role="button" tabindex="0" title="Child SKUs with inv=0. Click to show them.">inv=0:<span id="inv0-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--pending" id="missing-l-badge" role="button" tabindex="0" title="Click to show Missing L SKUs with inv&gt;0. Click again to clear.">Missing L &gt;0:<span id="pending-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--pending-inv0" id="missing-l-inv0-badge" role="button" tabindex="0" title="Click to show Missing L SKUs with inv=0. Click again to clear.">Missing L = 0:<span id="pending-inv0-total">0</span></span>
                            </div>

                            <div class="listing-toolbar-controls">
                                <div class="listing-toolbar-filters">
                                    <select id="row-data-type" class="form-select form-select-sm filter-select" aria-label="Data Type">
                                        <option value="all" selected>all</option>
                                        <option value="sku">SKU (Child)</option>
                                        <option value="parent">Parent</option>
                                    </select>
                                    <select id="inv-filter" class="form-select form-select-sm filter-select" aria-label="INV">
                                        <option value="all" selected>inv all</option>
                                        <option value="gt0">inv&gt;0</option>
                                        <option value="eq0">inv=0</option>
                                    </select>
                                    <select id="link-filter" class="form-select form-select-sm filter-select" aria-label="Buyer Link">
                                        <option value="all" selected>Buyer Link</option>
                                        <option value="with-link">With Link</option>
                                        <option value="without-link">Without Link</option>
                                    </select>
                                    <select id="listed-filter" class="form-select form-select-sm filter-select" aria-label="Listed">
                                        <option value="all" selected>Listed</option>
                                        <option value="Listed">Listed Only</option>
                                        <option value="Pending">Missing L</option>
                                    </select>
                                </div>
                                <div class="toolbar-actions dropdown">
                                    <button type="button"
                                        class="btn btn-sm btn-success verify-listings-btn"
                                        id="verify-listings-btn"
                                        title="Check Missing L SKUs against the Wayfair API and update listed ones">
                                        <i class="fas fa-check-double"></i> Verify
                                    </button>
                                    <a href="{{ route('wayfair.variation.verify') }}"
                                        class="btn btn-sm btn-outline-primary verify-listings-btn"
                                        title="Wayfair Variation Verify (Parent vs Listed SKU)">
                                        <i class="fas fa-table"></i> Variation Verify
                                    </a>
                                    <button type="button"
                                        class="btn btn-sm btn-primary listing-io-btn"
                                        id="listing-io-btn"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false"
                                        title="Import / Export">
                                        <i class="fas fa-file-import"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end listing-io-menu" aria-labelledby="listing-io-btn">
                                        <li>
                                            <button type="button" class="dropdown-item" id="import-btn" title="Import">
                                                <i class="fas fa-file-import text-primary"></i>
                                            </button>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('listing_wayfair.export') }}" title="Export">
                                                <i class="fas fa-file-export text-success"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Import Editable Fields</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <a href="{{ asset('sample_excel/sample_listing_file.csv') }}" download class="btn btn-outline-secondary mb-3">📄 Download Sample File</a>
                                    <input type="file" id="importFile" name="file" accept=".csv,.txt" class="form-control" />
                                    <small class="text-muted">Only CSV or TXT files are supported</small>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" id="confirmImportBtn">Import</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="wayfair-listing-wrap">
                        <div id="wayfairListing-table"></div>
                    </div>

                    <div id="data-loader" class="card-loader-overlay" style="display: none;">
                        <div class="loader-content">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="loader-text">Loading Listing data...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.body.style.zoom = "80%";

        let wayfairListingTable = null;
        let allListingData = [];
        let syncCatalogBadgeActive = function () {};

        function isParentSku(sku) {
            return String(sku || '').toUpperCase().includes('PARENT');
        }

        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function showNotification(type, message) {
            const notification = $(`
                <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        ${escapeHtml(message)}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            `);
            $('body').append(notification);
            setTimeout(() => notification.find('.alert').alert('close'), 3000);
        }

        function showLoader() {
            $('#data-loader').fadeIn(100);
        }

        function hideLoader() {
            $('#data-loader').fadeOut(100);
        }

        function normalizeListingRows(rows) {
            const mapped = (rows || []).map(item => {
                const inv = parseFloat(item.INV) || 0;
                const itemId = String(item.eBay_item_id || '').trim();
                const listed = itemId ? 'Listed' : 'Pending';
                return {
                    ...item,
                    parent: item.parent ?? item.Parent ?? '',
                    sku: item.sku ?? '',
                    INV: inv,
                    L30: parseFloat(item.L30) || 0,
                    listed: listed,
                    eBay_item_id: itemId || null,
                    buyer_link: item.buyer_link || '',
                    seller_link: item.seller_link || '',
                    is_parent: isParentSku(item.sku)
                };
            });

            mapped.sort((a, b) => {
                const parentCmp = String(a.parent || '').localeCompare(String(b.parent || ''), undefined, { sensitivity: 'base' });
                if (parentCmp !== 0) return parentCmp;
                return (a.is_parent ? 1 : 0) - (b.is_parent ? 1 : 0);
            });

            return mapped;
        }

        function calculateTotals() {
            try {
                if (!wayfairListingTable) {
                    resetMetricsToZero();
                    return;
                }

                const rows = wayfairListingTable.getData('active') || [];
                const parentSet = new Set();
                let skuCount = 0;
                let listedTotal = 0;
                let pendingTotal = 0;
                let withoutLinkTotal = 0;
                let inv0Total = 0;
                let pendingInv0Total = 0;
                allListingData.forEach(item => {
                    const parent = String(item.parent || '').trim();
                    if (parent) parentSet.add(parent);
                    if (!item.sku || isParentSku(item.sku)) return;
                    skuCount++;
                    if ((parseFloat(item.INV) || 0) === 0) inv0Total++;
                    if (item.listed === 'Listed') {
                        listedTotal++;
                    } else {
                        if ((parseFloat(item.INV) || 0) === 0) {
                            pendingInv0Total++;
                        } else {
                            pendingTotal++;
                        }
                    }
                    const hasLink = !!String(item.buyer_link || item.eBay_item_id || '').trim();
                    if (!hasLink) withoutLinkTotal++;
                });
                $('#parent-total').text(parentSet.size.toLocaleString());
                $('#sku-total').text(skuCount.toLocaleString());
                $('#listed-total').text(listedTotal.toLocaleString());
                $('#inv0-total').text(inv0Total.toLocaleString());
                $('#pending-total').text(pendingTotal.toLocaleString());
                $('#pending-inv0-total').text(pendingInv0Total.toLocaleString());
                $('#without-link-total').text(withoutLinkTotal.toLocaleString());
                $('#rows-total').text(rows.length.toLocaleString());
            } catch (error) {
                console.error('Error in calculateTotals:', error);
                resetMetricsToZero();
            }
        }

        function resetMetricsToZero() {
            $('#parent-total').text('0');
            $('#sku-total').text('0');
            $('#without-link-total').text('0');
            $('#listed-total').text('0');
            $('#inv0-total').text('0');
            $('#pending-total').text('0');
            $('#pending-inv0-total').text('0');
            $('#rows-total').text('0');
        }

        function applyListingFilters() {
            if (!wayfairListingTable) return;

            const dataType = $('#row-data-type').val();
            const invFilter = $('#inv-filter').val();
            const linkFilter = $('#link-filter').val();
            const listedFilter = $('#listed-filter').val();

            wayfairListingTable.setFilter(function (data) {
                if (dataType === 'parent' && !data.is_parent) return false;
                if (dataType === 'sku' && data.is_parent) return false;

                if (!data.is_parent) {
                    const inv = parseFloat(data.INV) || 0;
                    if (invFilter === 'gt0' && !(inv > 0)) return false;
                    if (invFilter === 'eq0' && inv !== 0) return false;
                }

                const hasItemLink = !!String(data.eBay_item_id || '').trim();
                if (linkFilter === 'with-link' && !hasItemLink) return false;
                if (linkFilter === 'without-link' && hasItemLink) return false;

                if (listedFilter !== 'all' && data.listed !== listedFilter) return false;

                return true;
            });

            calculateTotals();
            if (typeof syncCatalogBadgeActive === 'function') {
                syncCatalogBadgeActive();
            }
        }

        function showBsModal(id) {
            const el = document.getElementById(id);
            if (!el) return;
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).show();
            } else {
                $(el).modal('show');
            }
        }

        function hideBsModal(id) {
            const el = document.getElementById(id);
            if (!el) return;
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).hide();
            } else {
                $(el).modal('hide');
            }
        }

        function formatEbayItemLink(cell, type) {
            const data = cell.getRow().getData();
            const isBuyer = type === 'buyer';
            const stored = String(isBuyer ? (data.buyer_link || '') : (data.seller_link || '')).trim();
            if (stored) {
                const label = isBuyer ? 'Buyer' : 'Seller';
                return `<a href="${escapeHtml(stored)}" target="_blank" rel="noopener noreferrer" class="listing-item-link"
                title="${escapeHtml(label + ' link')}" onclick="event.stopPropagation();">
                <i class="fas fa-external-link-alt"></i> ${label}
            </a>`;
            }
            return '<span class="listing-link-empty">—</span>';
        }

        function formatBuyerLink(cell) {
            return formatEbayItemLink(cell, 'buyer');
        }

        function formatSellerLink(cell) {
            return formatEbayItemLink(cell, 'seller');
        }

        function formatListed(cell) {
            const data = cell.getRow().getData();
            if (data.is_parent) return '';

            // Missing Listing: channel listing id / price signal = Listed
            const itemId = String(data.eBay_item_id || '').trim();
            if (itemId) {
                return `<span class="listing-listed-tick" title="Listed (Wayfair pricing or listing status)" aria-label="Listed">
                    <i class="fas fa-check"></i>
                </span>`;
            }
            const inv = parseFloat(data.INV) || 0;
            if (inv === 0) {
                return `<span class="listing-auto-badge listing-auto-badge--not-listed-inv0" title="Missing L = 0 — not listed and inv=0">Missing L = 0</span>`;
            }
            return `<span class="listing-auto-badge listing-auto-badge--not-listed" title="Missing L &gt;0 — not listed and inv&gt;0">Missing L &gt;0</span>`;
        }

        $(document).ready(function () {
            showLoader();

            wayfairListingTable = new Tabulator('#wayfairListing-table', {
                ajaxURL: '/listing_wayfair/view-data',
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    allListingData = normalizeListingRows(rows);
                    return allListingData;
                },
                height: '650px',
                layout: 'fitColumns',
                placeholder: 'No matching records found',
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 250, 500],
                paginationCounter: 'rows',
                paginationButtonCount: 10,
                selectableRows: true,
                selectableRowsCheck: function (row) {
                    return !row.getData().is_parent;
                },
                rowFormatter: function (row) {
                    const el = row.getElement();
                    if (row.getData().is_parent) {
                        el.classList.add('parent-row');
                    } else {
                        el.classList.remove('parent-row');
                    }
                },
                columns: [
                    {
                        formatter: 'rowSelection',
                        titleFormatter: 'rowSelection',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        width: 44,
                        minWidth: 44,
                        resizable: false,
                        frozen: true,
                        cellClick: function (e, cell) {
                            e.stopPropagation();
                            const data = cell.getRow().getData();
                            if (data.is_parent) return;
                            cell.getRow().toggleSelect();
                        }
                    },
                    {
                        title: 'Parent',
                        field: 'parent',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 140,
                        widthGrow: 1
                    },
                    {
                        title: 'Sku',
                        field: 'sku',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 160,
                        widthGrow: 1.2
                    },
                    {
                        title: 'INV',
                        field: 'INV',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        sorter: 'number',
                        width: 90,
                        formatter: function (cell) {
                            const v = parseFloat(cell.getValue()) || 0;
                            return v.toLocaleString();
                        }
                    },
                    {
                        title: 'Buyer Link',
                        field: 'eBay_item_id',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        minWidth: 100,
                        widthGrow: 1,
                        headerTooltip: 'Buyer link from listing status',
                        formatter: formatBuyerLink
                    },
                    {
                        title: 'Seller Link',
                        field: 'seller_item_link',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        minWidth: 100,
                        widthGrow: 1,
                        headerTooltip: 'Dynamic seller link: https://www.ebay.com/sh/lst/active?keyword={item_id}&source=filterbar&action=search',
                        formatter: formatSellerLink
                    },
                    {
                        title: 'Missing L',
                        field: 'listed',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        width: 150,
                        headerTooltip: 'Red = Missing L &gt;0 (has inventory). Purple = Missing L = 0 (inv=0).',
                        formatter: formatListed
                    }
                ]
            });

            wayfairListingTable.on('dataProcessed', function () {
                hideLoader();
                applyListingFilters();
            });
            wayfairListingTable.on('dataFiltered', function () {
                calculateTotals();
            });
            wayfairListingTable.on('dataLoadError', function () {
                hideLoader();
                showNotification('danger', 'Failed to load data. Please try again.');
            });

            $('#row-data-type, #inv-filter, #link-filter, #listed-filter').on('change', applyListingFilters);

            syncCatalogBadgeActive = function () {
                const dataType = $('#row-data-type').val();
                const invFilter = $('#inv-filter').val();
                const listedFilter = $('#listed-filter').val();
                const linkFilter = $('#link-filter').val();
                const catalogView = listedFilter === 'all' && linkFilter === 'all' && (invFilter === 'all' || dataType === 'parent');
                $('#parent-badge').toggleClass('is-active', catalogView && dataType === 'parent');
                $('#sku-badge').toggleClass('is-active', catalogView && dataType === 'sku' && invFilter === 'all');
                $('#listed-badge').toggleClass('is-active',
                    dataType === 'sku' && listedFilter === 'Listed' && invFilter === 'all' && linkFilter === 'all'
                );
                $('#inv0-badge').toggleClass('is-active',
                    dataType === 'sku' && invFilter === 'eq0' && listedFilter === 'all' && linkFilter === 'all'
                );
                $('#missing-l-inv0-badge').toggleClass('is-active',
                    dataType === 'sku' && listedFilter === 'Pending' && invFilter === 'eq0' && linkFilter === 'all'
                );
            }

            function applyCatalogBadgeFilter(kind) {
                const $parent = $('#parent-badge');
                const $sku = $('#sku-badge');
                const already =
                    (kind === 'parent' && $parent.hasClass('is-active')) ||
                    (kind === 'sku' && $sku.hasClass('is-active'));

                applyMissingLBadgeFilter(true);
                $('#link-filter').val('all');
                $('#listed-filter').val('all');
                $('#inv-filter').val('all');

                if (already) {
                    $('#row-data-type').val('all');
                } else {
                    $('#row-data-type').val(kind === 'parent' ? 'parent' : 'sku');
                }
                applyListingFilters();
                syncCatalogBadgeActive();
            }

            $(document).on('click', '#parent-badge', function () {
                applyCatalogBadgeFilter('parent');
            });
            $(document).on('click', '#sku-badge', function () {
                applyCatalogBadgeFilter('sku');
            });
            $(document).on('keydown', '#parent-badge, #sku-badge', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    applyCatalogBadgeFilter(this.id === 'parent-badge' ? 'parent' : 'sku');
                }
            });
            $('#row-data-type, #inv-filter, #link-filter, #listed-filter').on('change.catalogBadges', syncCatalogBadgeActive);

            $(document).on('click', '#listed-badge', function () {
                const already = $('#listed-badge').hasClass('is-active');
                applyMissingLBadgeFilter(true);
                $('#row-data-type').val('sku');
                $('#inv-filter').val('all');
                $('#link-filter').val('all');
                $('#listed-filter').val(already ? 'all' : 'Listed');
                applyListingFilters();
                syncCatalogBadgeActive();
            });
            $(document).on('click', '#inv0-badge', function () {
                const already = $('#inv0-badge').hasClass('is-active');
                applyMissingLBadgeFilter(true);
                $('#row-data-type').val('sku');
                $('#link-filter').val('all');
                $('#listed-filter').val('all');
                if (already) {
                    $('#row-data-type').val('all');
                    $('#inv-filter').val('all');
                } else {
                    $('#inv-filter').val('eq0');
                }
                applyListingFilters();
                syncCatalogBadgeActive();
            });
            $(document).on('keydown', '#listed-badge, #inv0-badge', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });

            $(document).on('click', '#missing-l-inv0-badge', function () {
                const already = $('#missing-l-inv0-badge').hasClass('is-active');
                applyMissingLBadgeFilter(true);
                $('#row-data-type').val('sku');
                $('#link-filter').val('all');
                if (already) {
                    $('#row-data-type').val('all');
                    $('#inv-filter').val('all');
                    $('#listed-filter').val('all');
                } else {
                    $('#inv-filter').val('eq0');
                    $('#listed-filter').val('Pending');
                }
                applyListingFilters();
                syncCatalogBadgeActive();
            });
            $(document).on('keydown', '#missing-l-inv0-badge', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });

            // Missing L badge → filter table to unlisted SKUs (toggle)
            let missingLFilterActive = false;
            function applyMissingLBadgeFilter(forceOff) {
                if (forceOff === true) {
                    missingLFilterActive = false;
                } else {
                    missingLFilterActive = !missingLFilterActive;
                }
                const $badge = $('#missing-l-badge');
                if (missingLFilterActive) {
                    $('#row-data-type').val('sku');
                    $('#inv-filter').val('gt0');
                    $('#link-filter').val('all');
                    $('#listed-filter').val('Pending');
                    $badge.addClass('is-active');
                } else {
                    $('#listed-filter').val('all');
                    $badge.removeClass('is-active');
                }
                applyListingFilters();
            }
            $(document).on('click', '#missing-l-badge', function () {
                applyMissingLBadgeFilter();
            });
            $(document).on('keydown', '#missing-l-badge', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    applyMissingLBadgeFilter();
                }
            });
            $('#listed-filter, #inv-filter, #row-data-type').on('change.missingLBadge', function () {
                if (!missingLFilterActive) return;
                const stillMissing =
                    $('#listed-filter').val() === 'Pending' &&
                    $('#inv-filter').val() === 'gt0' &&
                    $('#row-data-type').val() === 'sku';
                if (!stillMissing) {
                    missingLFilterActive = false;
                    $('#missing-l-badge').removeClass('is-active');
                }
            });

            $('#import-btn').on('click', function () {
                showBsModal('importModal');
            });

            $(document).on('click', '#verify-listings-btn', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                if (!confirm('Verify Missing L SKUs against the Wayfair API and update any that are listed?')) {
                    return;
                }
                $btn.prop('disabled', true);
                const prevHtml = $btn.html();
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Verifying…');
                $('#data-loader .loader-text').text('Verifying missing listings with Wayfair API...');
                showLoader();
                $.ajax({
                    url: "{{ route('listing_wayfair.verify') }}",
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    timeout: 0,
                    success: function (response) {
                        const ok = !!(response && response.success);
                        showNotification(ok ? 'success' : 'warning', (response && response.message) ? response.message : 'Verify finished.');
                        wayfairListingTable.setData('/listing_wayfair/view-data');
                    },
                    error: function (xhr) {
                        hideLoader();
                        let errorMessage = 'Verify listings failed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        showNotification('danger', errorMessage);
                    },
                    complete: function () {
                        $btn.prop('disabled', false).html(prevHtml);
                        $('#data-loader .loader-text').text('Loading Listing data...');
                    }
                });
            });

            $(document).on('click', '#confirmImportBtn', function () {
                const file = $('#importFile')[0].files[0];
                if (!file) {
                    alert('Please select a file to import.');
                    return;
                }

                const formData = new FormData();
                formData.append('file', file);

                showLoader();
                $.ajax({
                    url: "{{ route('listing_wayfair.import') }}",
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function (response) {
                        hideBsModal('importModal');
                        $('#importFile').val('');
                        let message = 'File imported successfully!';
                        if (response.success) {
                            message = response.success;
                            if (response.processed !== undefined) {
                                message += ` (Processed: ${response.processed}`;
                                if (response.skipped !== undefined) message += `, Skipped: ${response.skipped}`;
                                if (response.errors !== undefined && response.errors > 0) message += `, Errors: ${response.errors}`;
                                message += ')';
                            }
                        }
                        showNotification('success', message);
                        wayfairListingTable.setData('/listing_wayfair/view-data');
                    },
                    error: function (xhr) {
                        hideLoader();
                        let errorMessage = 'Import failed';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMessage = xhr.responseJSON.error;
                        }
                        showNotification('danger', errorMessage);
                    }
                });
            });

        });
    </script>
@endsection
