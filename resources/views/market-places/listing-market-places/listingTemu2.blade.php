@extends('layouts.vertical', ['title' => 'Listing Temu 2', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
    <link rel="stylesheet" href="{{ asset('css/listing-page-tools.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* ========== TABLE SHELL ========== */
        #temu2-listing-wrap {
            overflow-x: auto;
            overflow-y: visible;
            width: 100%;
        }

        #temu2-listing-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
            width: 100% !important;
        }

        .card-body:has(#temu2-listing-toolbar) {
            width: 100%;
        }

        #temu2-listing-wrap .tabulator .tabulator-tableholder {
            background: #fff;
        }

        /* ========== HEADER ========== */
        #temu2-listing-wrap .tabulator .tabulator-header {
            background: #00d5d5;
            border-bottom: 1px solid #ffffff;
        }

        #temu2-listing-wrap .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        #temu2-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #temu2-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: normal !important;
        }

        #temu2-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
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

        #temu2-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: auto !important;
            min-height: 34px;
            padding: 0;
        }

        #temu2-listing-wrap .tabulator .tabulator-header .tabulator-col {
            height: auto !important;
            min-height: 34px;
            vertical-align: middle;
            background: #00d5d5 !important;
            border-right: 1px solid #ffffff;
            color: #000 !important;
            font-weight: bold;
        }

        #temu2-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder {
            padding-left: 2px !important;
            padding-right: 2px !important;
        }

        /* Header filters */
        #temu2-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-header-filter input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 12px;
            color: #475569;
            background: #fff;
            box-shadow: none;
        }

        #temu2-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-header-filter input:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.15);
        }

        /* ========== ROWS / CELLS ========== */
        #temu2-listing-wrap .tabulator .tabulator-row {
            min-height: 36px;
            border-bottom: 1px solid #f1f5f9;
        }

        #temu2-listing-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 5px 6px !important;
            border-right: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        #temu2-listing-wrap .tabulator-row .tabulator-cell input[type="checkbox"],
        #temu2-listing-wrap .tabulator-header .tabulator-col input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #4361ee;
            margin: 0;
            vertical-align: middle;
        }

        #temu2-listing-wrap .tabulator-row.parent-row .tabulator-cell input[type="checkbox"] {
            display: none;
        }

        #temu2-listing-wrap .tabulator .tabulator-row:hover {
            background-color: #f8fafc !important;
        }

        #temu2-listing-wrap .tabulator .tabulator-row.tabulator-row-even {
            background-color: #fcfcfd;
        }

        #temu2-listing-wrap .tabulator-row.parent-row,
        #temu2-listing-wrap .tabulator-row.parent-row .tabulator-cell {
            background-color: #fffef2 !important;
            font-weight: 700 !important;
            color: #0f172a;
        }

        #temu2-listing-wrap .tabulator-row.parent-row:hover,
        #temu2-listing-wrap .tabulator-row.parent-row:hover .tabulator-cell {
            background-color: #fefce8 !important;
        }

        /* ========== FOOTER / PAGINATION ========== */
        #temu2-listing-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }

        #temu2-listing-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        #temu2-listing-wrap .tabulator .tabulator-footer .tabulator-paginator label {
            margin-right: 6px;
            font-size: 12px;
            color: #475569;
            font-weight: 600;
        }

        #temu2-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page-size {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 13px;
            color: #475569;
            background: #fff;
            min-height: 36px;
        }

        #temu2-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
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

        #temu2-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
        }

        #temu2-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important;
            border-color: #4361ee !important;
            color: #fff !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67, 97, 238, 0.3) !important;
        }

        #temu2-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important;
            cursor: not-allowed !important;
        }

        #temu2-listing-wrap .tabulator .tabulator-footer .tabulator-page-counter {
            margin: 0 0.5rem;
            font-size: 12px;
            color: #334155;
        }

        /* ========== TOOLBAR (badges + filters, one line, autofit page) ========== */
        #temu2-listing-toolbar {
            background: transparent;
            border: none;
            border-radius: 0;
            padding: 0;
            overflow: visible;
            width: 100%;
            box-sizing: border-box;
        }

        #temu2-listing-toolbar .temu2-listing-toolbar-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        #temu2-listing-toolbar .listing-stat-badges {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: stretch;
            gap: 0;
            margin: 0;
            padding: 0;
        }

        #temu2-listing-toolbar .listing-stat-badge {
            flex: 0 0 auto;
            justify-content: center;
            margin: 0 !important;
            border-radius: 0;
        }

        #temu2-listing-toolbar .listing-stat-badges .listing-stat-badge:first-child {
            border-radius: 8px 0 0 8px;
        }

        #temu2-listing-toolbar .listing-stat-badges .listing-stat-badge:last-child {
            border-radius: 0 8px 8px 0;
        }

        #temu2-listing-toolbar .filter-select {
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

        #temu2-listing-toolbar .filter-select:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.15);
        }

        #temu2-listing-toolbar .toolbar-actions {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            gap: 4px;
            margin-left: 0;
        }

        #temu2-listing-toolbar .temu2-bulk-publish-btn {
            height: 30px;
            padding: 0 10px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        #temu2-listing-toolbar .temu2-bulk-publish-btn:disabled {
            opacity: 0.65;
            cursor: wait;
        }

        #temu2-listing-toolbar .listing-io-btn {
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

        #temu2-listing-toolbar .listing-io-btn::after {
            display: none;
        }

        #temu2-listing-toolbar .listing-io-menu {
            min-width: 42px;
            padding: 4px;
        }

        #temu2-listing-toolbar .listing-io-menu .dropdown-item {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 32px;
            padding: 0;
            border-radius: 4px;
            font-size: 14px;
        }

        #temu2-listing-toolbar .listing-io-menu .dropdown-item:hover {
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
        .listing-stat-badge--rows { background: #334155; color: #fff; }

        /* ========== DROPDOWNS ========== */
        #temu2-listing-wrap select.nr-req-dropdown,
        #temu2-listing-wrap select.listed-dropdown {
            border: 1px solid transparent;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            padding: 4px 6px;
            cursor: pointer;
            appearance: auto;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        #temu2-listing-wrap select.nr-req-dropdown:focus,
        #temu2-listing-wrap select.listed-dropdown:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.25);
        }

        #temu2-listing-wrap select.nr-req-dropdown[data-val="REQ"],
        #temu2-listing-wrap select.nr-req-dropdown option.req-option {
            background-color: #28a745;
            color: #fff;
        }

        #temu2-listing-wrap select.nr-req-dropdown[data-val="NR"],
        #temu2-listing-wrap select.nr-req-dropdown option.nr-option {
            background-color: #dc3545;
            color: #fff;
        }

        #temu2-listing-wrap select.listed-dropdown[data-val="Listed"],
        #temu2-listing-wrap select.listed-dropdown option.listed-option {
            background-color: #28a745;
            color: #fff;
        }

        #temu2-listing-wrap select.listed-dropdown[data-val="Pending"],
        #temu2-listing-wrap select.listed-dropdown option.pending-option {
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

        .temu2-publish-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 3px 10px;
            border: 0;
            border-radius: 6px;
            background: #0d6efd;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.3;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .temu2-publish-btn:hover {
            background: #0b5ed7;
            color: #fff;
        }

        .temu2-publish-btn:disabled,
        .temu2-publish-btn.is-publishing {
            opacity: 0.75;
            cursor: wait;
        }

        .temu2-publish-modal-note {
            font-size: 13px;
            color: #475569;
            margin-bottom: 12px;
        }

        .temu2-publish-mode {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 14px;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            font-size: 13px;
            color: #0f172a;
        }

        .temu2-publish-mode label {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }

        .temu2-publish-mode input {
            margin-top: 3px;
        }

        .temu2-publish-group {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 12px;
            overflow: hidden;
        }

        .temu2-publish-group-head {
            background: #f8fafc;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 13px;
            color: #0f172a;
        }

        .temu2-publish-group table {
            width: 100%;
            margin: 0;
            font-size: 12px;
        }

        .temu2-publish-group th,
        .temu2-publish-group td {
            padding: 6px 10px;
            vertical-align: middle;
        }

        .temu2-publish-status {
            font-weight: 700;
            white-space: nowrap;
        }

        .temu2-publish-status.is-publish { color: #15803d; }
        .temu2-publish-status.is-skip { color: #64748b; }
        .temu2-publish-status.is-missing { color: #b45309; }

        #temu2-publish-progress {
            font-size: 13px;
            color: #334155;
            min-height: 1.2em;
        }

        /* ========== LINK CELL ========== */
        #temu2-listing-wrap a.listing-item-link {
            font-weight: 600;
            color: #0d6efd;
            text-decoration: none;
        }

        #temu2-listing-wrap a.listing-item-link:hover {
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
        #temu2-listing-wrap .tabulator-placeholder {
            color: #64748b;
            font-weight: 600;
            padding: 24px;
        }

    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => 'Listing Temu 2', 'sub_title' => 'Temu'])

    <div class="row">
        <div class="col-12">
            <div class="card position-relative">
                <div class="card-body">
                    <div id="temu2-listing-toolbar" class="mb-3">
                        <div class="temu2-listing-toolbar-row">
                            <div class="listing-stat-badges">
                                <span class="listing-stat-badge listing-stat-badge--req">REQ:<span id="req-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--nrl">NRL:<span id="nrl-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--nolink">No Link:<span id="without-link-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--listed">Listed:<span id="listed-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--pending" id="missing-l-badge" role="button" tabindex="0" title="Click to show Missing L SKUs (REQ + not listed). Click again to clear.">Missing L:<span id="pending-total">0</span></span>
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
                            <button type="button"
                                class="btn btn-sm btn-primary temu2-bulk-publish-btn"
                                id="bulk-publish-btn"
                                title="Publish selected SKUs as parent variations">
                                <i class="fas fa-cloud-upload-alt"></i> Publish selected
                            </button>
                            <div class="toolbar-actions">
                                <button type="button"
                                    class="btn btn-sm btn-primary listing-io-btn"
                                    id="import-btn"
                                    title="Import">
                                    <i class="fas fa-file-import"></i>
                                </button>
                                <button type="button"
                                    class="btn btn-sm btn-primary listing-io-btn"
                                    id="export-btn"
                                    title="Export visible / filtered rows">
                                    <i class="fas fa-file-export"></i>
                                </button>
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

                    <div class="modal fade" id="temu2PublishModal" tabindex="-1" aria-labelledby="temu2PublishModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="temu2PublishModalLabel">Publish to Temu 2</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="temu2-publish-modal-note">
                                        The parent is used as the Temu goods title. Check the children you want on this listing.
                                        Unchecked siblings stay off. Products in Temu Deleted can be published again.
                                    </p>
                                    <div class="temu2-publish-mode" role="radiogroup" aria-label="Publish mode">
                                        <label>
                                            <input type="radio" name="temu2-publish-mode" value="variation" checked>
                                            <span>One variation listing — all checked SKUs under this parent</span>
                                        </label>
                                        <label>
                                            <input type="radio" name="temu2-publish-mode" value="single">
                                            <span>Each checked SKU as its own listing — still uses the parent title</span>
                                        </label>
                                    </div>
                                    <div id="temu2-publish-groups"></div>
                                    <div id="temu2-publish-progress"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="temu2-publish-confirm">
                                        <i class="fas fa-cloud-upload-alt"></i> Publish
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="temu2-listing-wrap">
                        <div id="temu2Listing-table"></div>
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

        let temu2ListingTable = null;
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
            setTimeout(() => notification.find('.alert').alert('close'), type === 'danger' ? 10000 : 4000);
        }

        function showLoader() {
            $('#data-loader').fadeIn(100);
        }

        function copySkuToClipboard(text, btn) {
            const done = function () {
                showNotification('success', 'Copied: ' + text);
                if (!btn) return;
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-copy');
                    icon.classList.add('fa-check');
                    setTimeout(function () {
                        icon.classList.remove('fa-check');
                        icon.classList.add('fa-copy');
                    }, 1200);
                }
            };
            const fallback = function () {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.top = '0';
                ta.style.left = '0';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                try {
                    document.execCommand('copy');
                    done();
                } catch (err) {
                    showNotification('danger', 'Could not copy SKU');
                }
                document.body.removeChild(ta);
            };
            if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(fallback);
            } else {
                fallback();
            }
        }

        function hideLoader() {
            $('#data-loader').fadeOut(100);
        }

        function normalizeListingRows(rows) {
            const mapped = (rows || []).map(item => {
                const inv = parseFloat(item.INV) || 0;
                const goodsId = String(item.goods_id || item.listing_id || item.eBay_item_id || '').trim();
                // Automated: NRL from Temu2DataView; Listed from temu2_metrics.goods_id (API)
                const nrReq = (item.nr_req === 'NR' || item.nr_req === 'NRL') ? 'NR' : 'REQ';
                const listed = goodsId ? 'Listed' : 'Pending';
                return {
                    ...item,
                    parent: item.parent ?? item.Parent ?? '',
                    sku: item.sku ?? '',
                    INV: inv,
                    L30: parseFloat(item.L30) || 0,
                    nr_req: nrReq,
                    listed: listed,
                    goods_id: goodsId || null,
                    eBay_item_id: goodsId || null,
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
                if (!temu2ListingTable) {
                    resetMetricsToZero();
                    return;
                }

                const rows = temu2ListingTable.getData('active') || [];
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
                            // No Link: REQ rows with no Temu goods_id (dynamic buyer link unavailable)
                            if (!String(item.goods_id || item.eBay_item_id || '').trim()) {
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
            if (!temu2ListingTable) return;

            const dataType = $('#row-data-type').val();
            const invFilter = $('#inv-filter').val();
            const nrReqFilter = $('#nr-req-filter').val();
            const linkFilter = $('#link-filter').val();
            const listedFilter = $('#listed-filter').val();

            temu2ListingTable.setFilter(function (data) {
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

        function formatTemuItemLink(cell, type) {
            const data = cell.getRow().getData();
            if (data.is_parent) return '';

            const isBuyer = type === 'buyer';
            const goodsId = String(data.goods_id || data.listing_id || data.eBay_item_id || '').trim();

            if (!goodsId) {
                return '<span class="text-muted" title="No Temu goods_id">—</span>';
            }

            // Stable URLs (session/refer params omitted)
            const href = isBuyer
                ? ('https://www.temu.com/goods.html?_bg_fs=1&goods_id=' + encodeURIComponent(goodsId))
                : ('https://seller.temu.com/product-info.html?add_method=1&click_type=1&goods_id=' + encodeURIComponent(goodsId));
            const label = isBuyer ? 'Buyer' : 'Seller';
            const title = isBuyer
                ? ('Buyer — Temu goods_id ' + goodsId)
                : ('Seller — Temu product-info goods_id ' + goodsId);

            return `<a href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer" class="listing-item-link"
                title="${escapeHtml(title)}" onclick="event.stopPropagation();">
                <i class="fas fa-external-link-alt me-1"></i>${label}
            </a>`;
        }

        function formatBuyerLink(cell) {
            return formatTemuItemLink(cell, 'buyer');
        }

        function formatSellerLink(cell) {
            return formatTemuItemLink(cell, 'seller');
        }

        function formatListed(cell) {
            const data = cell.getRow().getData();
            if (data.is_parent) return '';

            const goodsId = String(data.goods_id || data.eBay_item_id || '').trim();
            if (goodsId) {
                return `<span class="listing-listed-tick" title="Listed (temu2_metrics.goods_id)" aria-label="Listed">
                    <i class="fas fa-check"></i>
                </span>`;
            }
            return `<span class="listing-auto-badge listing-auto-badge--not-listed" title="Missing L — no Temu goods_id">Missing L</span>`;
        }

        function formatPublishToTemu2(cell) {
            const data = cell.getRow().getData();
            if (data.is_parent) return '';

            const goodsId = String(data.goods_id || data.listing_id || data.eBay_item_id || '').trim();
            if (goodsId) {
                return '<span class="text-muted" title="Already listed on Temu 2">—</span>';
            }

            const nrReq = String(data.nr_req || 'REQ');
            if (nrReq === 'NR' || nrReq === 'NRL') {
                return '<span class="text-muted" title="NRL SKUs are not published">—</span>';
            }

            const sku = String(data.sku || '').trim();
            if (!sku) return '';

            return `<button type="button" class="temu2-publish-btn" data-sku="${escapeHtml(sku)}" title="Review variations for ${escapeHtml(sku)} then publish to Temu 2">
                <i class="fas fa-cloud-upload-alt"></i> Publish
            </button>`;
        }

        $(document).ready(function () {
            showLoader();

            temu2ListingTable = new Tabulator('#temu2Listing-table', {
                ajaxURL: '/listing_temu2/view-data',
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
                        minWidth: 180,
                        widthGrow: 1.2,
                        formatter: function (cell) {
                            const sku = String(cell.getValue() || '').trim();
                            if (!sku) return '';
                            const safe = escapeHtml(sku);
                            return `<span class="sku-cell"><span class="sku-cell-text">${safe}</span><button type="button" class="copy-sku-btn" data-sku="${safe}" title="Copy SKU"><i class="fas fa-copy"></i></button></span>`;
                        },
                        cellClick: function (e) {
                            if (e.target.closest && e.target.closest('.copy-sku-btn')) {
                                e.stopPropagation();
                            }
                        }
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
                        field: 'goods_id',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        minWidth: 100,
                        widthGrow: 1,
                        headerTooltip: 'Dynamic buyer link: https://www.temu.com/goods.html?_bg_fs=1&goods_id={goods_id}',
                        formatter: formatBuyerLink
                    },
                    {
                        title: 'Seller Link',
                        field: 'seller_link',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        minWidth: 100,
                        widthGrow: 1,
                        headerTooltip: 'Dynamic seller link: https://seller.temu.com/product-info.html?add_method=1&click_type=1&goods_id={goods_id}',
                        formatter: formatSellerLink
                    },
                    {
                        title: 'Missing L',
                        field: 'listed',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        width: 130,
                        headerTooltip: 'Automatic from temu2_metrics.goods_id (Missing L when REQ and no goods_id)',
                        formatter: formatListed
                    },
                    {
                        title: 'Publish to Temu2',
                        field: 'publish_to_temu2',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        width: 150,
                        headerTooltip: 'Review parent variations, then publish Missing L SKUs to Temu 2',
                        formatter: formatPublishToTemu2
                    }
                ]
            });

            temu2ListingTable.on('dataProcessed', function () {
                hideLoader();
                applyListingFilters();
            });
            temu2ListingTable.on('dataFiltered', function () {
                calculateTotals();
            });
            temu2ListingTable.on('dataLoadError', function () {
                hideLoader();
                showNotification('danger', 'Failed to load data. Please try again.');
            });

            $('#row-data-type, #inv-filter, #nr-req-filter, #link-filter, #listed-filter').on('change', applyListingFilters);

            // Missing L badge → filter table to unlisted REQ SKUs (toggle)
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
                    $('#inv-filter').val('inv-only');
                    $('#nr-req-filter').val('REQ');
                    $('#link-filter').val('all');
                    $('#listed-filter').val('Pending');
                    $badge.addClass('is-active');
                } else {
                    $('#listed-filter').val('all');
                    $('#nr-req-filter').val('all');
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
            $('#listed-filter, #nr-req-filter, #inv-filter, #row-data-type').on('change.missingLBadge', function () {
                if (!missingLFilterActive) return;
                const stillMissing =
                    $('#listed-filter').val() === 'Pending' &&
                    $('#nr-req-filter').val() === 'REQ' &&
                    $('#inv-filter').val() === 'inv-only' &&
                    $('#row-data-type').val() === 'sku';
                if (!stillMissing) {
                    missingLFilterActive = false;
                    $('#missing-l-badge').removeClass('is-active');
                }
            });

            function publishStatusLabel(status, reason) {
                if (status === 'will_publish') {
                    const extra = reason ? ` title="${escapeHtml(reason)}"` : '';
                    return `<span class="temu2-publish-status is-publish"${extra}>${reason ? escapeHtml(reason) : 'Will publish'}</span>`;
                }
                const text = reason || 'Skipped';
                const missing = ['skipped_no_title', 'skipped_no_description', 'skipped_no_image', 'skipped_no_dim'].indexOf(status) !== -1;
                return `<span class="temu2-publish-status ${missing ? 'is-missing' : 'is-skip'}">${escapeHtml(text)}</span>`;
            }

            function listingParentForSku(sku) {
                sku = String(sku || '').trim();
                const rows = (temu2ListingTable && temu2ListingTable.getData()) || allListingData || [];
                for (let i = 0; i < rows.length; i++) {
                    if (String(rows[i].sku || '').trim() === sku) {
                        return String(rows[i].parent || '').trim();
                    }
                }
                return '';
            }

            function skuParentsMap(skus) {
                const parentKeys = {};
                (skus || []).forEach(function (sku) {
                    const parent = listingParentForSku(sku);
                    if (parent) parentKeys[parent.toUpperCase()] = true;
                });
                const map = {};
                const rows = (temu2ListingTable && temu2ListingTable.getData()) || allListingData || [];
                rows.forEach(function (row) {
                    const sku = String(row.sku || '').trim();
                    if (!sku || isParentSku(sku)) return;
                    const parent = String(row.parent || '').trim();
                    const selected = (skus || []).some(function (s) { return String(s || '').trim() === sku; });
                    if (selected || (parent && parentKeys[parent.toUpperCase()])) {
                        map[sku] = parent;
                    }
                });
                (skus || []).forEach(function (sku) {
                    sku = String(sku || '').trim();
                    if (sku && map[sku] === undefined) map[sku] = listingParentForSku(sku);
                });
                return map;
            }

            function selectedPublishMode() {
                const mode = String($('input[name="temu2-publish-mode"]:checked').val() || 'variation').toLowerCase();
                return mode === 'single' ? 'single' : 'variation';
            }

            function renderPublishGroups(groups) {
                if (!groups || !groups.length) {
                    $('#temu2-publish-groups').html('<p class="text-muted mb-0">No children found to publish.</p>');
                    $('#temu2-publish-confirm').prop('disabled', true);
                    return;
                }
                let html = '';
                let canPublish = false;
                groups.forEach(function (group, gi) {
                    const parent = String(group.parent || 'Standalone');
                    const children = group.children || [];
                    const selectedCount = children.filter(function (child) {
                        return child.status === 'will_publish' && child.selected !== false;
                    }).length;
                    html += `<div class="temu2-publish-group" data-group-index="${gi}" data-parent="${escapeHtml(parent)}">`;
                    html += `<div class="temu2-publish-group-head">${escapeHtml(parent)} · ${selectedCount} selected</div>`;
                    html += '<table class="table table-sm mb-0"><thead><tr><th style="width:36px;"></th><th>SKU (spec)</th><th>INV</th><th>Status</th></tr></thead><tbody>';
                    children.forEach(function (child) {
                        const sku = String(child.sku || '');
                        const publishable = child.status === 'will_publish';
                        const checked = publishable && child.selected !== false;
                        if (checked) canPublish = true;
                        html += '<tr>';
                        html += `<td><input type="checkbox" class="temu2-publish-sku-check" data-sku="${escapeHtml(sku)}" ${checked ? 'checked' : ''} ${publishable ? '' : 'disabled'}></td>`;
                        html += `<td><span class="sku-cell"><span class="sku-cell-text">${escapeHtml(sku)}</span><button type="button" class="copy-sku-btn" data-sku="${escapeHtml(sku)}" title="Copy SKU"><i class="fas fa-copy"></i></button></span></td>`;
                        html += `<td>${escapeHtml(String(child.inv ?? 0))}</td>`;
                        html += `<td>${publishStatusLabel(child.status, child.reason)}</td>`;
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                });
                $('#temu2-publish-groups').html(html);
                $('#temu2-publish-confirm').prop('disabled', !canPublish);
                $('#temu2-publish-progress').text('');
            }

            $(document).on('change', '.temu2-publish-sku-check', function () {
                const $group = $(this).closest('.temu2-publish-group');
                const parent = String($group.attr('data-parent') || '').trim();
                const selectedCount = $group.find('.temu2-publish-sku-check:checked:not(:disabled)').length;
                $group.find('.temu2-publish-group-head').text(parent + ' · ' + selectedCount + ' selected');
                const anyChecked = $('#temu2-publish-groups .temu2-publish-sku-check:checked:not(:disabled)').length > 0;
                $('#temu2-publish-confirm').prop('disabled', !anyChecked);
            });

            function selectedPublishGroups() {
                const groups = [];
                $('#temu2-publish-groups .temu2-publish-group').each(function () {
                    const parent = String($(this).attr('data-parent') || $(this).find('.temu2-publish-group-head').text().split(' · ')[0] || '').trim();
                    const skus = [];
                    $(this).find('.temu2-publish-sku-check:checked:not(:disabled)').each(function () {
                        const sku = String($(this).attr('data-sku') || '').trim();
                        if (sku) skus.push(sku);
                    });
                    if (skus.length) {
                        groups.push({ parent: parent, skus: skus });
                    }
                });
                return groups;
            }

            function markRowsListed(skus, goodsId) {
                if (!temu2ListingTable || !skus || !skus.length) return;
                const want = {};
                skus.forEach(function (sku) { want[String(sku).trim()] = true; });
                (temu2ListingTable.getRows() || []).forEach(function (row) {
                    const data = row.getData() || {};
                    if (!want[String(data.sku || '').trim()]) return;
                    row.update({
                        goods_id: goodsId,
                        listing_id: goodsId,
                        eBay_item_id: goodsId,
                        listed: 'Listed'
                    });
                });
                calculateTotals();
            }

            function openPublishPreview(skus) {
                const unique = [];
                const seen = {};
                (skus || []).forEach(function (sku) {
                    sku = String(sku || '').trim();
                    if (!sku || seen[sku]) return;
                    seen[sku] = true;
                    unique.push(sku);
                });
                if (!unique.length) {
                    showNotification('danger', 'Select at least one SKU.');
                    return;
                }
                $('#temu2-publish-groups').html('<p class="text-muted mb-0">Loading variation preview…</p>');
                $('#temu2-publish-confirm').prop('disabled', true);
                $('#temu2-publish-progress').text('');
                $('input[name="temu2-publish-mode"][value="variation"]').prop('checked', true);
                showBsModal('temu2PublishModal');
                $.ajax({
                    url: "{{ url('/listing_temu2/save-status') }}",
                    type: 'POST',
                    data: { skus: unique, sku_parents: skuParentsMap(unique), preview: 1, channel: 'temu2' },
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function (response) {
                        renderPublishGroups((response && response.groups) || []);
                    },
                    error: function (xhr) {
                        hideBsModal('temu2PublishModal');
                        let msg = 'Could not build variation preview.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        showNotification('danger', msg);
                    }
                });
            }

            function publishAjaxError(xhr) {
                if (xhr.status === 0) {
                    return 'Publish timed out or the connection dropped. Try again.';
                }
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    return xhr.responseJSON.message;
                }
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const first = Object.values(xhr.responseJSON.errors)[0];
                    return Array.isArray(first) ? String(first[0]) : String(first);
                }
                if (xhr.status === 419) {
                    return 'Session expired. Refresh the page and try Publish again.';
                }
                return 'Publish to Temu 2 failed.';
            }

            function publishGroup(skus, parent) {
                return $.ajax({
                    url: "{{ url('/listing_temu2/save-status') }}",
                    type: 'POST',
                    data: {
                        skus: skus,
                        confirmed: 1,
                        publish: 1,
                        channel: 'temu2',
                        mode: selectedPublishMode(),
                        parent: parent || ''
                    },
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    timeout: 180000
                });
            }

            $(document).on('click', '.copy-sku-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                const sku = String($(this).attr('data-sku') || '').trim();
                if (sku) copySkuToClipboard(sku, this);
            });

            $(document).on('click', '.temu2-publish-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const sku = String($(this).attr('data-sku') || $(this).data('sku') || '').trim();
                if (!sku) {
                    showNotification('danger', 'SKU is missing.');
                    return;
                }
                openPublishPreview([sku]);
            });

            $('#bulk-publish-btn').on('click', function () {
                if (!temu2ListingTable) return;
                const selected = (temu2ListingTable.getSelectedData() || [])
                    .map(function (row) { return String(row.sku || '').trim(); })
                    .filter(function (sku) { return sku && !isParentSku(sku); });
                if (!selected.length) {
                    showNotification('danger', 'Select one or more SKUs first.');
                    return;
                }
                openPublishPreview(selected);
            });

            $('#temu2-publish-confirm').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                const groups = selectedPublishGroups();
                if (!groups.length) {
                    showNotification('danger', 'Check at least one SKU to publish.');
                    return;
                }
                const originalHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Publishing');
                $('#temu2PublishModal .btn-close, #temu2PublishModal [data-bs-dismiss="modal"]').prop('disabled', true);

                let index = 0;
                const ok = [];
                const fail = [];

                function next() {
                    if (index >= groups.length) {
                        $btn.prop('disabled', false).html(originalHtml);
                        $('#temu2PublishModal .btn-close, #temu2PublishModal [data-bs-dismiss="modal"]').prop('disabled', false);
                        if (ok.length) {
                            showNotification('success', ok.join(' '));
                        }
                        if (fail.length) {
                            showNotification('danger', fail.join(' '));
                        }
                        if (ok.length && !fail.length) {
                            hideBsModal('temu2PublishModal');
                        }
                        return;
                    }
                    const group = groups[index];
                    index += 1;
                    $('#temu2-publish-progress').text('Publishing ' + group.parent + ' (' + index + '/' + groups.length + ')…');
                    publishGroup(group.skus, group.parent).done(function (response) {
                        const goodsId = String((response && response.goods_id) || '').trim();
                        const listedSkus = (response && response.skus) || group.skus;
                        if (goodsId) {
                            markRowsListed(listedSkus, goodsId);
                        }
                        ok.push((response && response.message) ? response.message : ('Published ' + group.parent + '.'));
                        next();
                    }).fail(function (xhr) {
                        fail.push(group.parent + ': ' + publishAjaxError(xhr));
                        next();
                    });
                }
                next();
            });

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
                    url: "{{ route('listing_temu2.import') }}",
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
                        temu2ListingTable.setData('/listing_temu2/view-data');
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

    <script>
        window.listingPageConfig = {
            wrap: '#temu2-listing-wrap',
            tableId: 'temu2Listing-table',
            exportName: 'temu2_listing',
            channel: 'temu2',
            channelLabel: "Temu 2",
            previewUrl: '/listing_temu2/save-status',
            publishUrl: '/listing_temu2/save-status'
        };
    </script>
    <script src="{{ asset('js/listing-page-tools.js') }}?v=4"></script>
@endsection
