@extends('layouts.vertical', ['title' => 'Listing Appscenic', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* ========== TABLE SHELL ========== */
        #appscenic-listing-wrap {
            overflow-x: auto;
            overflow-y: visible;
            width: 100%;
        }

        #appscenic-listing-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
            width: 100% !important;
        }

        .card-body:has(#appscenic-listing-toolbar) {
            width: 100%;
        }

        #appscenic-listing-wrap .tabulator .tabulator-tableholder {
            background: #fff;
        }

        /* ========== HEADER ========== */
        #appscenic-listing-wrap .tabulator .tabulator-header {
            background: #00d5d5;
            border-bottom: 1px solid #ffffff;
        }

        #appscenic-listing-wrap .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        #appscenic-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #appscenic-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: normal !important;
        }

        #appscenic-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
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

        #appscenic-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: auto !important;
            min-height: 34px;
            padding: 0;
        }

        #appscenic-listing-wrap .tabulator .tabulator-header .tabulator-col {
            height: auto !important;
            min-height: 34px;
            vertical-align: middle;
            background: #00d5d5 !important;
            border-right: 1px solid #ffffff;
            color: #000 !important;
            font-weight: bold;
        }

        #appscenic-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder {
            padding-left: 2px !important;
            padding-right: 2px !important;
        }

        /* Header filters */
        #appscenic-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-header-filter input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 12px;
            color: #475569;
            background: #fff;
            box-shadow: none;
        }

        #appscenic-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-header-filter input:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.15);
        }

        /* ========== ROWS / CELLS ========== */
        #appscenic-listing-wrap .tabulator .tabulator-row {
            min-height: 36px;
            border-bottom: 1px solid #f1f5f9;
        }

        #appscenic-listing-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 5px 6px !important;
            border-right: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        #appscenic-listing-wrap .tabulator-row .tabulator-cell input[type="checkbox"],
        #appscenic-listing-wrap .tabulator-header .tabulator-col input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #4361ee;
            margin: 0;
            vertical-align: middle;
        }

        #appscenic-listing-wrap .tabulator-row.parent-row .tabulator-cell input[type="checkbox"] {
            display: none;
        }

        #appscenic-listing-wrap .tabulator .tabulator-row:hover {
            background-color: #f8fafc !important;
        }

        #appscenic-listing-wrap .tabulator .tabulator-row.tabulator-row-even {
            background-color: #fcfcfd;
        }

        #appscenic-listing-wrap .tabulator-row.parent-row,
        #appscenic-listing-wrap .tabulator-row.parent-row .tabulator-cell {
            background-color: #fffef2 !important;
            font-weight: 700 !important;
            color: #0f172a;
        }

        #appscenic-listing-wrap .tabulator-row.parent-row:hover,
        #appscenic-listing-wrap .tabulator-row.parent-row:hover .tabulator-cell {
            background-color: #fefce8 !important;
        }

        /* ========== FOOTER / PAGINATION ========== */
        #appscenic-listing-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }

        #appscenic-listing-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        #appscenic-listing-wrap .tabulator .tabulator-footer .tabulator-paginator label {
            margin-right: 6px;
            font-size: 12px;
            color: #475569;
            font-weight: 600;
        }

        #appscenic-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page-size {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 13px;
            color: #475569;
            background: #fff;
            min-height: 36px;
        }

        #appscenic-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
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

        #appscenic-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
        }

        #appscenic-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important;
            border-color: #4361ee !important;
            color: #fff !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67, 97, 238, 0.3) !important;
        }

        #appscenic-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important;
            cursor: not-allowed !important;
        }

        #appscenic-listing-wrap .tabulator .tabulator-footer .tabulator-page-counter {
            margin: 0 0.5rem;
            font-size: 12px;
            color: #334155;
        }

        /* ========== TOOLBAR (badges + filters, one line, autofit page) ========== */
        #appscenic-listing-toolbar {
            background: transparent;
            border: none;
            border-radius: 0;
            padding: 0;
            overflow: hidden;
            width: 100%;
            box-sizing: border-box;
        }

        #appscenic-listing-toolbar .appscenic-listing-toolbar-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        #appscenic-listing-toolbar .listing-stat-badges {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: stretch;
            gap: 0;
            margin: 0;
            padding: 0;
        }

        #appscenic-listing-toolbar .listing-stat-badge {
            flex: 0 0 auto;
            justify-content: center;
            margin: 0 !important;
            border-radius: 0;
        }

        #appscenic-listing-toolbar .listing-stat-badges .listing-stat-badge:first-child {
            border-radius: 8px 0 0 8px;
        }

        #appscenic-listing-toolbar .listing-stat-badges .listing-stat-badge:last-child {
            border-radius: 0 8px 8px 0;
        }

        #appscenic-listing-toolbar .filter-select {
            flex: 0 0 auto;
            min-width: 0;
            width: 92px !important;
            max-width: 92px;
            border-radius: 5px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #64748b;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 16px 2px 4px;
            height: 30px;
            line-height: 1.2;
        }

        #appscenic-listing-toolbar .filter-select:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.15);
        }

        #appscenic-listing-toolbar .toolbar-actions {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            margin-left: 0;
        }

        #appscenic-listing-toolbar .listing-io-btn {
            border-radius: 5px;
            font-weight: 600;
            font-size: 14px;
            width: 32px;
            height: 30px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        #appscenic-listing-toolbar .listing-io-btn::after {
            display: none;
        }

        #appscenic-listing-toolbar .listing-io-menu {
            min-width: 42px;
            padding: 4px;
        }

        #appscenic-listing-toolbar .listing-io-menu .dropdown-item {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 32px;
            padding: 0;
            border-radius: 4px;
            font-size: 14px;
        }

        #appscenic-listing-toolbar .listing-io-menu .dropdown-item:hover {
            background: #f1f5f9;
        }

        /* ========== STAT BADGES ========== */
        .listing-stat-badge {
            display: inline-flex;
            align-items: center;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 8px;
            white-space: nowrap;
            line-height: 1.25;
            letter-spacing: 0.2px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .listing-stat-badge > span {
            margin-left: 4px;
            font-size: 16px;
            font-weight: 800;
        }

        .listing-stat-badge--req { background: #22c55e; color: #052e16; }
        .listing-stat-badge--nrl { background: #ef4444; color: #fff; }
        .listing-stat-badge--nolink { background: #f59e0b; color: #1c1917; }
        .listing-stat-badge--listed { background: #0ea5e9; color: #fff; }
        .listing-stat-badge--pending { background: #dc3545; color: #fff; }
        .listing-stat-badge--rows { background: #334155; color: #fff; }

        /* ========== DROPDOWNS ========== */
        #appscenic-listing-wrap select.nr-req-dropdown,
        #appscenic-listing-wrap select.listed-dropdown {
            border: 1px solid transparent;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            padding: 4px 6px;
            cursor: pointer;
            appearance: auto;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        #appscenic-listing-wrap select.nr-req-dropdown:focus,
        #appscenic-listing-wrap select.listed-dropdown:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.25);
        }

        #appscenic-listing-wrap select.nr-req-dropdown[data-val="REQ"],
        #appscenic-listing-wrap select.nr-req-dropdown option.req-option {
            background-color: #28a745;
            color: #fff;
        }

        #appscenic-listing-wrap select.nr-req-dropdown[data-val="NR"],
        #appscenic-listing-wrap select.nr-req-dropdown option.nr-option {
            background-color: #dc3545;
            color: #fff;
        }

        #appscenic-listing-wrap select.listed-dropdown[data-val="Listed"],
        #appscenic-listing-wrap select.listed-dropdown option.listed-option {
            background-color: #28a745;
            color: #fff;
        }

        #appscenic-listing-wrap select.listed-dropdown[data-val="Pending"],
        #appscenic-listing-wrap select.listed-dropdown option.pending-option {
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
        #appscenic-listing-wrap a.listing-item-link {
            font-weight: 600;
            color: #0d6efd;
            text-decoration: none;
        }

        #appscenic-listing-wrap a.listing-item-link:hover {
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
        #appscenic-listing-wrap .tabulator-placeholder {
            color: #64748b;
            font-weight: 600;
            padding: 24px;
        }

    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => 'Listing Appscenic', 'sub_title' => 'Appscenic'])

    <div class="row">
        <div class="col-12">
            <div class="card position-relative">
                <div class="card-body">
                    <div id="appscenic-listing-toolbar" class="mb-3">
                        <div class="appscenic-listing-toolbar-row">
                            <div class="listing-stat-badges">
                                <span class="listing-stat-badge listing-stat-badge--req">REQ:<span id="req-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--nrl">NRL:<span id="nrl-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--nolink">No Link:<span id="without-link-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--listed">Listed:<span id="listed-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--pending">Missing L:<span id="pending-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--rows">Rows:<span id="rows-total">0</span></span>
                            </div>

                            <select id="row-data-type" class="form-select form-select-sm filter-select" aria-label="Data Type">
                                <option value="all" selected>Data Type</option>
                                <option value="sku">SKU (Child)</option>
                                <option value="parent">Parent</option>
                            </select>
                            <select id="inv-filter" class="form-select form-select-sm filter-select" aria-label="INV">
                                <option value="all">INV: All</option>
                                <option value="inv-only" selected>INV Only</option>
                            </select>
                            <select id="nr-req-filter" class="form-select form-select-sm filter-select" aria-label="NRL/REQ">
                                <option value="all" selected>NRL/REQ</option>
                                <option value="REQ">REQ</option>
                                <option value="NR">NRL</option>
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
                            <div class="toolbar-actions dropdown">
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
                                        <a class="dropdown-item" href="{{ route('listing_appscenic.export') }}" title="Export">
                                            <i class="fas fa-file-export text-success"></i>
                                        </a>
                                    </li>
                                </ul>
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

                    <div id="appscenic-listing-wrap">
                        <div id="appscenicListing-table"></div>
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

        let appscenicListingTable = null;
        let allListingData = [];

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
                // Automated: NRL from EbayTwoDataView; Listed from ebay_2_metrics.item_id
                const nrReq = (item.nr_req === 'NR' || item.nr_req === 'NRL') ? 'NR' : 'REQ';
                const listed = itemId ? 'Listed' : 'Pending';
                return {
                    ...item,
                    parent: item.parent ?? item.Parent ?? '',
                    sku: item.sku ?? '',
                    INV: inv,
                    L30: parseFloat(item.L30) || 0,
                    nr_req: nrReq,
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
                if (!appscenicListingTable) {
                    resetMetricsToZero();
                    return;
                }

                const rows = appscenicListingTable.getData('active') || [];
                const metrics = {
                    invTotal: 0,
                    reqTotal: 0,
                    nrlTotal: 0,
                    withoutLinkTotal: 0,
                    listedTotal: 0,
                    pendingTotal: 0
                };

                rows.forEach(item => {
                    if (parseFloat(item.INV) > 0 && !isParentSku(item.sku)) {
                        metrics.invTotal += parseFloat(item.INV) || 0;

                        if (item.nr_req === 'REQ') {
                            metrics.reqTotal++;
                            // No Link: REQ rows with no ebay item id (dynamic link unavailable)
                            if (!String(item.eBay_item_id || '').trim()) {
                                metrics.withoutLinkTotal++;
                            }
                        }
                        if (item.nr_req === 'NR') {
                            metrics.nrlTotal++;
                        }
                        if (item.nr_req !== 'NR') {
                            if (item.listed === 'Listed') {
                                metrics.listedTotal++;
                            }
                            if (item.listed === 'Pending' || !item.listed) {
                                metrics.pendingTotal++;
                            }
                        }
                    }
                });

                $('#req-total').text(metrics.reqTotal);
                $('#nrl-total').text(metrics.nrlTotal);
                $('#without-link-total').text(metrics.withoutLinkTotal);
                $('#listed-total').text(metrics.listedTotal);
                $('#pending-total').text(metrics.pendingTotal);
                $('#rows-total').text(rows.length.toLocaleString());
            } catch (error) {
                console.error('Error in calculateTotals:', error);
                resetMetricsToZero();
            }
        }

        function resetMetricsToZero() {
            $('#req-total').text('0');
            $('#nrl-total').text('0');
            $('#without-link-total').text('0');
            $('#listed-total').text('0');
            $('#pending-total').text('0');
            $('#rows-total').text('0');
        }

        function applyListingFilters() {
            if (!appscenicListingTable) return;

            const dataType = $('#row-data-type').val();
            const invFilter = $('#inv-filter').val();
            const nrReqFilter = $('#nr-req-filter').val();
            const linkFilter = $('#link-filter').val();
            const listedFilter = $('#listed-filter').val();

            appscenicListingTable.setFilter(function (data) {
                if (dataType === 'parent' && !data.is_parent) return false;
                if (dataType === 'sku' && data.is_parent) return false;

                if (invFilter === 'inv-only') {
                    if (!data.is_parent && !(parseFloat(data.INV) > 0)) return false;
                }

                if (nrReqFilter !== 'all' && data.nr_req !== nrReqFilter) return false;

                const hasItemLink = !!String(data.eBay_item_id || '').trim();
                if (linkFilter === 'with-link' && !hasItemLink) return false;
                if (linkFilter === 'without-link' && hasItemLink) return false;

                if (listedFilter !== 'all' && data.listed !== listedFilter) return false;

                return true;
            });

            calculateTotals();
        }

        function formatNrReq(cell) {
            const data = cell.getRow().getData();
            if (data.is_parent) return '';

            const value = data.nr_req || 'REQ';
            if (value === 'NR') {
                return `<span class="listing-auto-badge listing-auto-badge--nrl" title="From channel DataView NRL">NRL</span>`;
            }
            return `<span class="listing-auto-badge listing-auto-badge--req" title="From channel DataView NRL">REQ</span>`;
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
                return `<span class="listing-listed-tick" title="Listed (ebay_2_metrics.item_id)" aria-label="Listed">
                    <i class="fas fa-check"></i>
                </span>`;
            }
            return `<span class="listing-auto-badge listing-auto-badge--not-listed" title="Missing L — no ebay item id">Missing L</span>`;
        }

        $(document).ready(function () {
            showLoader();

            appscenicListingTable = new Tabulator('#appscenicListing-table', {
                ajaxURL: '/listing_appscenic/view-data',
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
                        title: 'NRL/REQ',
                        field: 'nr_req',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        width: 110,
                        headerTooltip: 'Automatic from channel DataView NRL',
                        formatter: formatNrReq
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
                        width: 130,
                        headerTooltip: 'Automatic from channel listing signal (EbayTwo Missing L pattern)',
                        formatter: formatListed
                    }
                ]
            });

            appscenicListingTable.on('dataProcessed', function () {
                hideLoader();
                applyListingFilters();
            });
            appscenicListingTable.on('dataFiltered', function () {
                calculateTotals();
            });
            appscenicListingTable.on('dataLoadError', function () {
                hideLoader();
                showNotification('danger', 'Failed to load data. Please try again.');
            });

            $('#row-data-type, #inv-filter, #nr-req-filter, #link-filter, #listed-filter').on('change', applyListingFilters);

            $('#import-btn').on('click', function () {
                showBsModal('importModal');
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
                    url: "{{ route('listing_appscenic.import') }}",
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
                        appscenicListingTable.setData('/listing_appscenic/view-data');
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
