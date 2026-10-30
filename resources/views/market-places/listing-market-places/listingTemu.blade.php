@extends('layouts.vertical', ['title' => 'Listing Temu 1', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
    <link rel="stylesheet" href="{{ asset('css/listing-page-tools.css') }}?v=20261028-attach">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* ========== TABLE SHELL ========== */
        #temu-listing-wrap {
            overflow-x: auto;
            overflow-y: visible;
            width: 100%;
        }

        #temu-listing-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
            width: 100% !important;
        }

        .card-body:has(#temu-listing-toolbar) {
            width: 100%;
        }

        #temu-listing-wrap .tabulator .tabulator-tableholder {
            background: #fff;
        }

        /* ========== HEADER ========== */
        #temu-listing-wrap .tabulator .tabulator-header {
            background: #00d5d5;
            border-bottom: 1px solid #ffffff;
        }

        #temu-listing-wrap .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        #temu-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #temu-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: normal !important;
        }

        #temu-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
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

        #temu-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: auto !important;
            min-height: 34px;
            padding: 0;
        }

        #temu-listing-wrap .tabulator .tabulator-header .tabulator-col {
            height: auto !important;
            min-height: 34px;
            vertical-align: middle;
            background: #00d5d5 !important;
            border-right: 1px solid #ffffff;
            color: #000 !important;
            font-weight: bold;
        }

        #temu-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder {
            padding-left: 2px !important;
            padding-right: 2px !important;
        }

        /* Header filters */
        #temu-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-header-filter input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 12px;
            color: #475569;
            background: #fff;
            box-shadow: none;
        }

        #temu-listing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-header-filter input:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.15);
        }

        /* ========== ROWS / CELLS ========== */
        #temu-listing-wrap .tabulator .tabulator-row {
            min-height: 36px;
            border-bottom: 1px solid #f1f5f9;
        }

        #temu-listing-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 5px 6px !important;
            border-right: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        #temu-listing-wrap .tabulator-row .tabulator-cell input[type="checkbox"],
        #temu-listing-wrap .tabulator-header .tabulator-col input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #4361ee;
            margin: 0;
            vertical-align: middle;
        }

        #temu-listing-wrap .tabulator-row.parent-row .tabulator-cell input[type="checkbox"] {
            display: none;
        }

        #temu-listing-wrap .tabulator .tabulator-row:hover {
            background-color: #f8fafc !important;
        }

        #temu-listing-wrap .tabulator .tabulator-row.tabulator-row-even {
            background-color: #fcfcfd;
        }

        #temu-listing-wrap .tabulator-row.parent-row,
        #temu-listing-wrap .tabulator-row.parent-row .tabulator-cell {
            background-color: #fffef2 !important;
            font-weight: 700 !important;
            color: #0f172a;
        }

        #temu-listing-wrap .tabulator-row.parent-row:hover,
        #temu-listing-wrap .tabulator-row.parent-row:hover .tabulator-cell {
            background-color: #fefce8 !important;
        }

        /* ========== FOOTER / PAGINATION ========== */
        #temu-listing-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }

        #temu-listing-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        #temu-listing-wrap .tabulator .tabulator-footer .tabulator-paginator label {
            margin-right: 6px;
            font-size: 12px;
            color: #475569;
            font-weight: 600;
        }

        #temu-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page-size {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 13px;
            color: #475569;
            background: #fff;
            min-height: 36px;
        }

        #temu-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
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

        #temu-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
        }

        #temu-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important;
            border-color: #4361ee !important;
            color: #fff !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67, 97, 238, 0.3) !important;
        }

        #temu-listing-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important;
            cursor: not-allowed !important;
        }

        #temu-listing-wrap .tabulator .tabulator-footer .tabulator-page-counter {
            margin: 0 0.5rem;
            font-size: 12px;
            color: #334155;
        }

        /* ========== TOOLBAR (badges + filters, one line, autofit page) ========== */
        #temu-listing-toolbar {
            background: transparent;
            border: none;
            border-radius: 0;
            padding: 0;
            overflow: visible;
            width: 100%;
            box-sizing: border-box;
        }

        #temu-listing-toolbar .temu-listing-toolbar-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        #temu-listing-toolbar .listing-stat-badges {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: stretch;
            gap: 0;
            margin: 0;
            padding: 0;
        }

        #temu-listing-toolbar .listing-stat-badge {
            flex: 0 0 auto;
            justify-content: center;
            margin: 0 !important;
            border-radius: 0;
        }

        #temu-listing-toolbar .listing-stat-badges .listing-stat-badge:first-child {
            border-radius: 8px 0 0 8px;
        }

        #temu-listing-toolbar .listing-stat-badges .listing-stat-badge:last-child {
            border-radius: 0 8px 8px 0;
        }

        #temu-listing-toolbar .filter-select {
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

        #temu-listing-toolbar .temu-bulk-publish-btn {
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

        #temu-listing-toolbar .filter-select:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.15);
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
        #temu-listing-wrap select.nr-req-dropdown,
        #temu-listing-wrap select.listed-dropdown {
            border: 1px solid transparent;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            padding: 4px 6px;
            cursor: pointer;
            appearance: auto;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        #temu-listing-wrap select.nr-req-dropdown:focus,
        #temu-listing-wrap select.listed-dropdown:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.25);
        }

        #temu-listing-wrap select.nr-req-dropdown[data-val="REQ"],
        #temu-listing-wrap select.nr-req-dropdown option.req-option {
            background-color: #28a745;
            color: #fff;
        }

        #temu-listing-wrap select.nr-req-dropdown[data-val="NR"],
        #temu-listing-wrap select.nr-req-dropdown option.nr-option {
            background-color: #dc3545;
            color: #fff;
        }

        #temu-listing-wrap select.listed-dropdown[data-val="Listed"],
        #temu-listing-wrap select.listed-dropdown option.listed-option {
            background-color: #28a745;
            color: #fff;
        }

        #temu-listing-wrap select.listed-dropdown[data-val="Pending"],
        #temu-listing-wrap select.listed-dropdown option.pending-option {
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
        .listing-auto-badge--incomplete { background-color: #f59e0b; color: #1c1917; }

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

        /* Missing L badge and Publish button are the same size. */
        .listing-auto-badge--not-listed,
        .listing-auto-badge--incomplete,
        .temu-complete-btn,
        .temu-publish-btn {
            box-sizing: border-box;
            width: 104px;
            height: 30px;
            padding: 0 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            line-height: 1;
            white-space: nowrap;
        }

        .temu-complete-btn {
            gap: 4px;
            border: 0;
            border-radius: 6px;
            background: #f59e0b;
            color: #1c1917;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .temu-complete-btn:disabled,
        .temu-complete-btn.is-working {
            opacity: 0.75;
            cursor: wait;
        }

        .temu-incomplete-flag {
            border: 0;
            background: transparent;
            color: #dc2626;
            font-size: 20px;
            line-height: 1;
            padding: 2px 6px;
            cursor: pointer;
        }

        .temu-incomplete-flag:hover {
            color: #991b1b;
            transform: scale(1.12);
        }

        .temu-incomplete-list {
            list-style: none;
            margin: 0 0 12px;
            padding: 0;
            font-size: 13px;
        }

        .temu-incomplete-list li {
            padding: 4px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .temu-incomplete-list .bad { color: #b91c1c; font-weight: 600; }
        .temu-incomplete-list .good { color: #15803d; }
        .temu-incomplete-list .hint { display: block; color: #64748b; font-size: 12px; font-weight: 400; }

        .temu-complete-btn:hover {
            background: #d97706;
            color: #1c1917;
        }

        .temu-publish-btn {
            gap: 4px;
            border: 0;
            border-radius: 6px;
            background: #0d6efd;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .temu-publish-btn:hover {
            background: #0b5ed7;
            color: #fff;
        }

        .temu-publish-btn:disabled,
        .temu-publish-btn.is-publishing {
            opacity: 0.75;
            cursor: wait;
        }

        .temu-publish-modal-note {
            font-size: 13px;
            color: #475569;
            margin-bottom: 12px;
        }

        .temu-publish-mode {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
            padding: 0;
            border: 0;
            background: transparent;
            font-size: 13px;
            color: #0f172a;
        }

        .temu-publish-mode label {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin: 0;
            cursor: pointer;
            font-weight: 500;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
        }

        .temu-publish-mode label:has(input:checked) {
            border-color: #0d6efd;
            background: #eff6ff;
            box-shadow: 0 0 0 1px #0d6efd;
        }

        .temu-publish-mode label strong { display: block; font-size: 13px; color: #0f172a; }
        .temu-publish-mode label em {
            display: block;
            font-style: normal;
            font-weight: 400;
            font-size: 12px;
            color: #475569;
            margin-top: 2px;
        }

        .temu-publish-mode input {
            margin-top: 3px;
            flex: 0 0 auto;
        }

        .temu-publish-role {
            font-size: 11px;
            font-weight: 700;
            border-radius: 999px;
            padding: 1px 7px;
            white-space: nowrap;
        }
        .temu-publish-role.is-picked { background: #dcfce7; color: #166534; }
        .temu-publish-role.is-suggested { background: #f1f5f9; color: #475569; }

        @media (max-width: 640px) {
            .temu-publish-mode { grid-template-columns: 1fr; }
        }

        .temu-publish-group {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 12px;
            overflow: hidden;
        }

        .temu-publish-group-head {
            background: #f8fafc;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 13px;
            color: #0f172a;
        }

        .temu-publish-group table {
            width: 100%;
            margin: 0;
            font-size: 12px;
        }

        .temu-publish-group th,
        .temu-publish-group td {
            padding: 6px 10px;
            vertical-align: middle;
        }

        .temu-publish-status {
            font-weight: 700;
            white-space: nowrap;
        }

        .temu-publish-status.is-publish { color: #15803d; }
        .temu-publish-status.is-skip { color: #64748b; }
        .temu-publish-status.is-missing { color: #b45309; }

        #temu-publish-groups table tbody tr {
            display: table-row;
        }

        #temu-publish-groups .sku-cell {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            color: #0f172a;
        }

        #temu-publish-progress {
            font-size: 13px;
            color: #334155;
            min-height: 1.2em;
        }

        /* ========== LINK CELL ========== */
        #temu-listing-wrap a.listing-item-link {
            font-weight: 600;
            color: #0d6efd;
            text-decoration: none;
        }

        #temu-listing-wrap a.listing-item-link:hover {
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
        #temu-listing-wrap .tabulator-placeholder {
            color: #64748b;
            font-weight: 600;
            padding: 24px;
        }

    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => 'Listing Temu 1', 'sub_title' => 'Temu 1'])

    <div class="row">
        <div class="col-12">
            <div class="card position-relative">
                <div class="card-body">
                    <div id="temu-listing-toolbar" class="mb-3">
                        <div class="temu-listing-toolbar-row">
                            <div class="listing-stat-badges">
                                <span class="listing-stat-badge listing-stat-badge--req">REQ:<span id="req-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--nrl">NRL:<span id="nrl-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--nolink">No Link:<span id="without-link-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--listed">Listed:<span id="listed-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--pending" id="missing-l-badge" role="button" tabindex="0" title="Click to show Missing L SKUs (REQ + not listed). Click again to clear.">Missing L:<span id="pending-total">0</span></span>
                                <span class="listing-stat-badge listing-stat-badge--rows">Rows:<span id="rows-total">0</span></span>
                            </div>

                            <select id="row-data-type" class="form-select form-select-sm filter-select" aria-label="Show" title="Show parent rows, SKU rows, or both">
                                <option value="all" selected>All</option>
                                <option value="parent">Parent</option>
                                <option value="sku">SKU</option>
                            </select>
                            <select id="inv-filter" class="form-select form-select-sm filter-select" aria-label="INV">
                                <option value="all" selected>INV: All</option>
                                <option value="inv-only">INV Only</option>
                            </select>
                            <select id="listed-filter" class="form-select form-select-sm filter-select" aria-label="Listed">
                                <option value="all" selected>Listed</option>
                                <option value="Listed">Listed Only</option>
                                <option value="Pending">Missing L</option>
                            </select>
                            <button type="button"
                                class="btn btn-sm btn-primary temu-bulk-publish-btn"
                                id="bulk-publish-btn"
                                title="Publish the checked SKUs to Temu 1 as parent variations">
                                <i class="fas fa-cloud-upload-alt"></i> Publish selected
                            </button>
                        </div>
                    </div>

                    <div class="modal fade" id="temuPublishModal" tabindex="-1" aria-labelledby="temuPublishModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="temuPublishModalLabel">Publish listings</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="temu-publish-modal-note" id="temu-publish-modal-note">
                                        Only SKUs you check are published. Suggested siblings stay off until you check them.
                                    </p>
                                    <div class="temu-publish-mode" role="radiogroup" aria-label="How do you want to list?">
                                        <label>
                                            <input type="radio" name="temu-publish-mode" value="single" checked>
                                            <span>
                                                <strong>Single listing</strong>
                                                <em>Each checked SKU becomes its own listing. Suggested siblings are hidden.</em>
                                            </span>
                                        </label>
                                        <label>
                                            <input type="radio" name="temu-publish-mode" value="variation">
                                            <span>
                                                <strong>Variation listing</strong>
                                                <em>One listing with the SKUs you check. Suggested siblings start unchecked.</em>
                                            </span>
                                        </label>
                                    </div>
                                    <div id="temu-publish-groups"></div>
                                    <div id="temu-publish-progress"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="temu-publish-confirm">
                                        <i class="fas fa-cloud-upload-alt"></i> Publish listing(s)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="temuIncompleteModal" tabindex="-1" aria-labelledby="temuIncompleteModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="temuIncompleteModalLabel"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Incomplete on Temu</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body" id="temu-incomplete-body">Loading…</div>
                                <div class="modal-footer">
                                    <a href="#" target="_blank" rel="noopener" class="btn btn-outline-secondary me-auto d-none" id="temu-incomplete-seller">Open in Seller Center</a>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-warning" id="temu-incomplete-complete" data-sku="" disabled>
                                        <i class="fas fa-check-circle"></i> Complete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="temu-listing-wrap">
                        <div id="temuListing-table"></div>
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

        let temuListingTable = null;
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
                // Automated: NRL from TemuDataView; Listed from temu_metrics.goods_id (API)
                const nrReq = (item.nr_req === 'NR' || item.nr_req === 'NRL') ? 'NR' : 'REQ';
                // A goods that is Incomplete in Seller Center is not Missing L: it exists and has to be finished there.
                const listed = goodsId ? 'Listed'
                    : (String(item.temu_status || '') === 'Incomplete' ? 'Incomplete' : 'Pending');
                return {
                    ...item,
                    parent: item.parent ?? item.Parent ?? '',
                    sku: item.sku ?? '',
                    INV: inv,
                    L30: parseFloat(item.L30) || 0,
                    nr_req: nrReq,
                    listed: listed,
                    goods_id: goodsId || null,
                    sku_id: String(item.sku_id || '').trim() || null,
                    eBay_item_id: goodsId || null,
                    buyer_link: item.buyer_link || '',
                    seller_link: item.seller_link || '',
                    is_parent: isParentSku(item.sku)
                };
            });

            mapped.sort((a, b) => {
                const parentCmp = String(a.parent || '').localeCompare(String(b.parent || ''), undefined, { sensitivity: 'base' });
                if (parentCmp !== 0) return parentCmp;
                // SKUs of a variation first, its parent row below them.
                return (a.is_parent ? 1 : 0) - (b.is_parent ? 1 : 0);
            });

            // Goods ID mismatch: when the SKUs of one variation (parent) carry different goods IDs,
            // every Goods ID of that variation is marked red.
            const groupIds = {};
            const groupKey = r => String(r.parent || '').replace(/^PARENT\s+/i, '').trim().toLowerCase();
            const effectiveGoodsId = r => String(r.goods_id || r.temu_goods_id || '').trim();
            mapped.forEach(r => {
                const key = groupKey(r);
                const id = effectiveGoodsId(r);
                if (!key || !id) return;
                (groupIds[key] = groupIds[key] || new Set()).add(id);
            });
            mapped.forEach(r => {
                const set = groupIds[groupKey(r)];
                r.goods_id_mismatch = !!(set && set.size > 1);
            });

            // Parent row status: summarised from its SKUs, e.g. "Active · 1 of 4 inactive" (as Seller Center shows).
            const statusGroups = {};
            mapped.forEach(r => {
                if (r.is_parent) return;
                const key = groupKey(r);
                if (!key) return;
                const g = statusGroups[key] = statusGroups[key] || { total: 0, counts: {} };
                g.total++;
                const s = String(r.temu_status || '');
                if (s) g.counts[s] = (g.counts[s] || 0) + 1;
            });
            mapped.forEach(r => {
                if (!r.is_parent) return;
                const g = statusGroups[groupKey(r)];
                if (!g) return;
                const inactive = g.counts['Inactive'] || 0;
                if (!r.temu_status) {
                    if (g.counts['Active']) r.temu_status = 'Active';
                    else if (inactive) r.temu_status = 'Inactive';
                }
                if (inactive && r.temu_status !== 'Inactive') {
                    r.temu_status_note = inactive + ' of ' + g.total + ' inactive';
                }
            });

            return mapped;
        }

        function calculateTotals() {
            try {
                if (!temuListingTable) {
                    resetMetricsToZero();
                    return;
                }

                // All loaded rows, independent of the filters (INV Only, Parent/SKU, ...).
                const rows = (allListingData && allListingData.length) ? allListingData : (temuListingTable.getData() || []);
                const metrics = {
                    invTotal: 0,
                    reqTotal: 0,
                    nrlTotal: 0,
                    withoutLinkTotal: 0,
                    listedTotal: 0,
                    pendingTotal: 0
                };

                // Badges count every loaded row: parent rows and zero-INV SKUs included.
                rows.forEach(item => {
                    {
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
                            // Missing L counts SKU rows only - parent rows are never counted.
                            if (!item.is_parent && (item.listed === 'Pending' || !item.listed)) {
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
            if (!temuListingTable) return;

            const dataType = $('#row-data-type').val();
            const invFilter = $('#inv-filter').val();
            const listedFilter = $('#listed-filter').val();

            // The SKU (child) filters: INV, Listed / Missing L.
            const childPasses = function (data) {
                if (invFilter === 'inv-only' && !(parseFloat(data.INV) > 0)) return false;
                if (listedFilter !== 'all' && data.listed !== listedFilter) return false;

                return true;
            };
            const childFiltersActive = invFilter === 'inv-only' || listedFilter !== 'all';

            // A parent row is a header for its SKUs: it has no INV / NRL / link / listed state of its
            // own, so it is shown whenever at least one of its SKUs passes the filters above.
            const parentKey = function (data) {
                return String(data.parent || '').trim().toLowerCase();
            };
            const parentsWithMatch = {};
            if (dataType !== 'sku' && childFiltersActive) {
                (allListingData || []).forEach(function (row) {
                    if (!row.is_parent && childPasses(row)) {
                        parentsWithMatch[parentKey(row)] = true;
                    }
                });
            }

            temuListingTable.setFilter(function (data) {
                if (data.is_parent) {
                    if (dataType === 'sku') return false;
                    return !childFiltersActive || parentsWithMatch[parentKey(data)] === true;
                }
                if (dataType === 'parent') return false;

                return childPasses(data);
            });

            calculateTotals();
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
                return `<span class="listing-listed-tick" title="Listed (temu_metrics.goods_id)" aria-label="Listed">
                    <i class="fas fa-check"></i>
                </span>`;
            }
            if (data.listed === 'Incomplete') {
                return `<span class="listing-auto-badge listing-auto-badge--incomplete" title="Goods ${escapeHtml(data.temu_goods_id || '')} is Incomplete in Temu Seller Center - finish it there">Incomplete</span>`;
            }
            return `<span class="listing-auto-badge listing-auto-badge--not-listed" title="Missing L — no Temu goods_id">Missing L</span>`;
        }

        // Publish button: only for SKUs with no live Temu 1 listing (Missing L). Parent rows get none.
        function formatPublishToTemu(cell) {
            const data = cell.getRow().getData();
            if (data.is_parent) return '';

            const goodsId = String(data.goods_id || data.listing_id || data.eBay_item_id || '').trim();
            if (goodsId) {
                return '<span class="text-muted" title="Already listed on Temu">—</span>';
            }

            const sku = String(data.sku || '').trim();
            if (!sku) return '';

            // Incomplete goods already exists on Temu: publishing again would not complete it, update it instead.
            if (data.listed === 'Incomplete') {
                return `<button type="button" class="temu-complete-btn" data-sku="${escapeHtml(sku)}" title="Send the listing data to the Incomplete Temu product (goods ${escapeHtml(data.temu_goods_id || '')}) so it can be completed">
                    <i class="fas fa-check-circle"></i> Complete
                </button>`;
            }

            return `<button type="button" class="temu-publish-btn" data-sku="${escapeHtml(sku)}" title="Review variations for ${escapeHtml(sku)} then publish to Temu as a new listing">
                <i class="fas fa-cloud-upload-alt"></i> Publish
            </button>`;
        }

        // fallback = the ID Seller Center holds for this SKU when our own link is missing (e.g. an Incomplete goods).
        function formatTemuId(value, fallback, mismatch) {
            const id = String(value || '').trim();
            const fb = String(fallback || '').trim();
            if (mismatch && (id || fb)) {
                return '<span style="font-family:monospace;font-size:12px;font-weight:700;color:#dc2626;" title="Goods ID mismatch: the SKUs of this variation are on different Temu goods">' + escapeHtml(id || fb) + '</span>';
            }
            if (id) return '<span style="font-family:monospace;font-size:12px;">' + escapeHtml(id) + '</span>';
            if (fb) {
                return '<span style="font-family:monospace;font-size:12px;color:#b45309;" title="Found in Temu Seller Center, not linked as a live listing">' + escapeHtml(fb) + '</span>';
            }
            return '<span class="text-muted">—</span>';
        }

        // Red triangle for SKUs whose goods is Incomplete on Temu; click shows what is missing.
        function formatIncompleteFlag(cell) {
            const d = cell.getRow().getData() || {};
            if (d.is_parent || String(d.temu_status || '') !== 'Incomplete') return '';
            const sku = String(d.sku || '').trim();
            if (!sku) return '';
            return `<button type="button" class="temu-incomplete-flag" data-sku="${escapeHtml(sku)}" title="Incomplete on Temu - click to see what is missing" aria-label="Incomplete - show details"><i class="fas fa-exclamation-triangle"></i></button>`;
        }

        function formatTemuStatus(cell) {
            const d = cell.getRow().getData() || {};
            const status = String(d.temu_status || '').trim();
            if (!status) {
                if (!d.temu_status_checked) return '<span class="text-muted" title="Click Refresh listings to read the status from Temu">—</span>';
                if (d.is_parent) return '<span class="text-muted">—</span>';
                return '<span class="text-muted" title="No status for this SKU yet. Click Refresh listings to read it from Temu.">Not on Temu</span>';
            }
            const colors = {
                'Active': 'background:#dcfce7;color:#15803d;',
                'Inactive': 'background:#e2e8f0;color:#334155;',
                'Incomplete': 'background:#ffedd5;color:#c2410c;',
                'Draft': 'background:#dbeafe;color:#1d4ed8;',
                'Deleted': 'background:#fee2e2;color:#b91c1c;'
            };
            const style = colors[status] || 'background:#f1f5f9;color:#475569;';
            const raw = d.temu_status_raw ? ' (' + d.temu_status_raw + ')' : '';
            const note = d.temu_status_note ? ' · ' + d.temu_status_note : '';
            const source = d.temu_status_source ? ' - source: ' + d.temu_status_source : '';
            return '<span title="' + escapeHtml('Status in Temu' + raw + source) + '" style="display:inline-block;padding:1px 8px;border-radius:10px;font-weight:700;white-space:nowrap;' + style + '">' + escapeHtml(status + note) + '</span>';
        }

        $(document).ready(function () {
            showLoader();

            temuListingTable = new Tabulator('#temuListing-table', {
                ajaxURL: '/listing_temu/view-data',
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
                        title: 'SKU VS Parent',
                        field: 'sku_vs_parent',
                        width: 92,
                        minWidth: 80,
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerWordWrap: true,
                        headerTooltip: 'SKUs of this parent listed on Temu vs the SKUs under the parent in our Product Master (CP master). Hover a value to see the missing SKUs.',
                        sorter: function (a, b, aRow, bRow) {
                            function missingCount(row) {
                                const d = (row && row.getData()) || {};
                                if (d.sku_parent_total == null) return -1;
                                return (parseInt(d.sku_parent_total, 10) || 0) - (parseInt(d.sku_parent_listed, 10) || 0);
                            }
                            return missingCount(aRow) - missingCount(bRow);
                        },
                        formatter: function (cell) {
                            const d = cell.getRow().getData() || {};
                            if (d.sku_parent_total == null) return '';
                            const listed = parseInt(d.sku_parent_listed, 10) || 0;
                            const total = parseInt(d.sku_parent_total, 10) || 0;
                            const missing = Array.isArray(d.sku_parent_missing) ? d.sku_parent_missing : [];
                            const ok = missing.length === 0;
                            const shown = missing.slice(0, 60).map(function (m) {
                                if (typeof m === 'string') return m;
                                return String(m.sku || '') + '  |  SID: ' + (m.sid ? m.sid : '-') + '  |  Inv: ' + (parseInt(m.inv, 10) || 0);
                            });
                            const tip = ok
                                ? 'All ' + total + ' SKUs of this parent are listed on Temu'
                                : 'Missing on Temu (' + missing.length + '):  SKU  |  SID  |  Inv\n' + shown.join('\n')
                                    + (missing.length > shown.length ? '\n+' + (missing.length - shown.length) + ' more' : '');
                            return '<span title="' + escapeHtml(tip) + '" style="display:inline-block;padding:1px 8px;border-radius:10px;font-weight:700;cursor:help;'
                                + (ok ? 'background:#dcfce7;color:#15803d;' : 'background:#fee2e2;color:#b91c1c;') + '">'
                                + listed + ' / ' + total + '</span>';
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

                        title: 'Std Prc',

                        field: 'std_price',

                        hozAlign: 'center',

                        headerHozAlign: 'center',

                        sorter: 'number',

                        width: 100,

                        headerTooltip: 'Std Prc from LMP Overall. Same STANDARD_PRICE, including linked SKUs.',

                        formatter: function (cell) {

                            const v = parseFloat(cell.getValue());

                            if (!isFinite(v) || v <= 0) return '\u2014';

                            return '<span style="color:#198754;font-weight:600;">$' + v.toFixed(2) + '</span>';

                        }

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
                        title: 'Goods ID',
                        field: 'listing_id',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        minWidth: 130,
                        headerTooltip: 'Temu goods ID (imported from the Temu API)',
                        formatter: function (cell) { const d = cell.getRow().getData(); return formatTemuId(d.goods_id, d.temu_goods_id, d.goods_id_mismatch); }
                    },
                    {
                        title: 'SKU ID',
                        field: 'sku_id',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        minWidth: 130,
                        headerTooltip: 'Temu SKU ID (imported from the Temu API)',
                        formatter: function (cell) { const d = cell.getRow().getData(); return formatTemuId(d.sku_id, d.temu_sku_id); }
                    },
                    {
                        title: 'Temu Status',
                        field: 'temu_status',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        width: 170,
                        headerTooltip: 'Status of this SKU in Temu Seller Center: Active, Inactive, Incomplete, Draft or Deleted',
                        formatter: formatTemuStatus
                    },
                    {
                        title: 'Incomplete',
                        field: 'temu_incomplete',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        width: 95,
                        headerTooltip: 'Red triangle = this SKU is in an Incomplete product on Temu. Click it to see what is missing.',
                        formatter: formatIncompleteFlag
                    },
                    {
                        title: 'Missing L',
                        field: 'listed',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        width: 130,
                        headerTooltip: 'Automatic from temu_metrics.goods_id (Missing L when REQ and no goods_id)',
                        formatter: formatListed
                    },
                    {
                        title: 'Publish to Temu',
                        field: 'publish_to_temu',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        width: 150,
                        headerTooltip: 'Publish this SKU to Temu 1 as a new listing',
                        formatter: formatPublishToTemu
                    }
                ]
            });

            temuListingTable.on('dataProcessed', function () {
                hideLoader();
                applyListingFilters();
            });
            temuListingTable.on('dataFiltered', function () {
                calculateTotals();
            });
            temuListingTable.on('dataLoadError', function () {
                hideLoader();
                showNotification('danger', 'Failed to load data. Please try again.');
            });

            $('#row-data-type, #inv-filter, #listed-filter').on('change', applyListingFilters);

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
                    $('#row-data-type').val('sku'); // Missing L never includes parent rows
                    $('#inv-filter').val('all');
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
                    $('#inv-filter').val() === 'all' &&
                    $('#row-data-type').val() === 'sku';
                if (!stillMissing) {
                    missingLFilterActive = false;
                    $('#missing-l-badge').removeClass('is-active');
                }
            });

            function publishStatusLabel(status, reason) {
                if (status === 'will_publish') {
                    const extra = reason ? ` title="${escapeHtml(reason)}"` : '';
                    return `<span class="temu-publish-status is-publish"${extra}>${reason ? escapeHtml(reason) : 'Will publish'}</span>`;
                }
                const text = reason || 'Skipped';
                const missing = ['skipped_no_title', 'skipped_no_description', 'skipped_no_image', 'skipped_no_dim'].indexOf(status) !== -1;
                return `<span class="temu-publish-status ${missing ? 'is-missing' : 'is-skip'}">${escapeHtml(text)}</span>`;
            }

            function listingRows() {
                if (allListingData && allListingData.length) {
                    return allListingData;
                }
                return (temuListingTable && temuListingTable.getData()) || [];
            }

            function skuKey(sku) {
                return String(sku || '').trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
            }

            function normalizePreviewChildren(children) {
                if (Array.isArray(children)) {
                    return children.filter(function (child) { return child && typeof child === 'object'; });
                }
                if (children && typeof children === 'object') {
                    if (children.sku) {
                        return [children];
                    }
                    return Object.keys(children).map(function (key) {
                        return children[key];
                    }).filter(function (child) { return child && typeof child === 'object'; });
                }
                return [];
            }

            function listingParentForSku(sku) {
                sku = String(sku || '').trim();
                const want = skuKey(sku);
                const rows = listingRows();
                for (let i = 0; i < rows.length; i++) {
                    const rowSku = String(rows[i].sku || '').trim();
                    if (rowSku === sku || skuKey(rowSku) === want) {
                        return String(rows[i].parent || '').trim();
                    }
                }
                return '';
            }

            function listingChildrenForParent(parent) {
                parent = String(parent || '').trim();
                const parentWant = skuKey(parent);
                if (!parentWant) return [];
                const out = [];
                const seen = {};
                listingRows().forEach(function (row) {
                    const sku = String(row.sku || '').trim();
                    if (!sku || isParentSku(sku) || seen[sku]) return;
                    const rowParent = String(row.parent || '').trim();
                    if (skuKey(rowParent) !== parentWant && rowParent.toUpperCase() !== parent.toUpperCase()) return;
                    seen[sku] = true;
                    out.push(row);
                });
                return out;
            }

            let temuPreviewSeedSkus = [];

            function isPreviewSeedSku(sku) {
                const want = skuKey(sku);
                return temuPreviewSeedSkus.some(function (seed) {
                    return skuKey(seed) === want;
                });
            }

            function skuParentsMap(skus) {
                const pairs = [];
                const seen = {};
                (skus || []).forEach(function (sku) {
                    sku = String(sku || '').trim();
                    if (!sku || seen[sku]) return;
                    seen[sku] = true;
                    pairs.push({ sku: sku, parent: listingParentForSku(sku) });
                });
                return pairs;
            }

            function expandPreviewSkus(skus) {
                const unique = [];
                const seen = {};
                (skus || []).forEach(function (sku) {
                    sku = String(sku || '').trim();
                    if (!sku || isParentSku(sku) || seen[sku]) return;
                    seen[sku] = true;
                    unique.push(sku);
                });
                return unique;
            }

            function fallbackGroupsFromTable(skus) {
                const parentOrder = [];
                const byParent = {};
                (skus || []).forEach(function (sku) {
                    sku = String(sku || '').trim();
                    if (!sku || isParentSku(sku)) return;
                    const parent = listingParentForSku(sku) || sku;
                    const key = parent.toUpperCase();
                    if (!byParent[key]) {
                        byParent[key] = { parent: parent, children: [] };
                        parentOrder.push(key);
                    }
                    const single = selectedPublishMode() === 'single';
                    listingChildrenForParent(parent).forEach(function (row) {
                        const childSku = String(row.sku || '').trim();
                        if (!childSku) return;
                        if (single && !isPreviewSeedSku(childSku)) return;
                        if (byParent[key].children.some(function (c) { return skuKey(c.sku) === skuKey(childSku); })) return;
                        byParent[key].children.push({
                            sku: childSku,
                            spec: childSku,
                            inv: row.INV ?? 0,
                            status: 'will_publish',
                            reason: '',
                            selected: isPreviewSeedSku(childSku)
                        });
                    });
                    if (!byParent[key].children.some(function (c) { return skuKey(c.sku) === skuKey(sku); })) {
                        byParent[key].children.push({
                            sku: sku,
                            spec: sku,
                            inv: 0,
                            status: 'will_publish',
                            reason: '',
                            selected: isPreviewSeedSku(sku)
                        });
                    }
                });
                return parentOrder.map(function (key) { return byParent[key]; }).filter(function (g) {
                    return g.children && g.children.length;
                });
            }

            function mergePreviewGroups(groups, skus) {
                const parentOrder = [];
                const byParent = {};
                const addChild = function (parent, child, overwrite) {
                    parent = String(parent || '').trim() || 'Standalone';
                    const key = parent.toUpperCase();
                    if (!byParent[key]) {
                        byParent[key] = { parent: parent, children: [], single_child: false };
                        parentOrder.push(key);
                    }
                    const sku = String((child && child.sku) || '').trim();
                    if (!sku) return;
                    const existing = byParent[key].children.find(function (row) {
                        return skuKey(row.sku) === skuKey(sku);
                    });
                    if (existing) {
                        if (overwrite) Object.assign(existing, child);
                        return;
                    }
                    byParent[key].children.push(child);
                };
                (groups || []).forEach(function (group) {
                    const parent = String(group.parent || '').trim();
                    if (group.single_child) {
                        const key = (parent || 'Standalone').toUpperCase();
                        if (!byParent[key]) {
                            byParent[key] = { parent: parent || 'Standalone', children: [], single_child: true };
                            parentOrder.push(key);
                        } else {
                            byParent[key].single_child = true;
                        }
                    }
                    normalizePreviewChildren(group.children).forEach(function (child) {
                        addChild(parent, child, true);
                    });
                });
                fallbackGroupsFromTable(skus).forEach(function (group) {
                    normalizePreviewChildren(group.children).forEach(function (child) {
                        addChild(group.parent, child, false);
                    });
                });
                return parentOrder.map(function (key) {
                    const group = byParent[key];
                    group.single_child = group.single_child || group.children.length <= 1;
                    return group;
                }).filter(function (group) {
                    return group.children.length;
                });
            }

            function selectedPublishMode() {
                const mode = String($('input[name="temu-publish-mode"]:checked').val() || 'single').toLowerCase();
                return mode === 'variation' ? 'variation' : 'single';
            }

            function updateTemuModalCopy() {
                const single = selectedPublishMode() === 'single';
                $('#temuPublishModalLabel').text(single ? 'Publish single listings' : 'Publish variation listing');
                $('#temu-publish-modal-note').text(single
                    ? 'Each checked SKU becomes its own Temu listing. Suggested siblings are hidden in this mode.'
                    : 'One Temu listing per group. Only the SKUs you check are included — suggested siblings stay off until you check them.');
                const $btn = $('#temu-publish-confirm');
                if (!$btn.prop('disabled')) {
                    $btn.html(single
                        ? '<i class="fas fa-cloud-upload-alt"></i> Publish listing(s)'
                        : '<i class="fas fa-cloud-upload-alt"></i> Publish variation(s)');
                }
            }

            function childShouldCheck(child) {
                if (child.status !== 'will_publish') return false;
                if (typeof child.selected === 'boolean') return child.selected;
                return isPreviewSeedSku(child.sku);
            }

            function renderPublishGroups(groups) {
                if (!groups || !groups.length) {
                    $('#temu-publish-groups').html('<p class="text-muted mb-0">No children found to publish.</p>');
                    $('#temu-publish-confirm').prop('disabled', true);
                    return;
                }
                const single = selectedPublishMode() === 'single';
                let html = '';
                let canPublish = false;
                groups.forEach(function (group, gi) {
                    const parent = String(group.parent || 'Standalone');
                    const children = normalizePreviewChildren(group.children);
                    const selectedCount = children.filter(childShouldCheck).length;
                    html += `<div class="temu-publish-group" data-group-index="${gi}" data-parent="${escapeHtml(parent)}">`;
                    html += `<div class="temu-publish-group-head"><span class="listing-publish-group-title">${escapeHtml(parent)} · ${selectedCount} selected for ${single ? 'single listing' + (selectedCount === 1 ? '' : 's') : 'this variation'}</span><button type="button" class="listing-publish-group-close" title="Remove this parent from this publish" aria-label="Remove ${escapeHtml(parent)}">&times;</button></div>`;
                    if (!single && window.ListingParentCategory) html += window.ListingParentCategory.block(parent, group.suggested_category);
                    if (!single && window.ListingParentPicker) { html += window.ListingParentPicker.block(group, gi, { channel: 'temu', label: 'Temu', attachSupported: false, required: false }); }
                    html += '<table class="table table-sm mb-0"><thead><tr><th style="width:36px;"></th><th>SKU</th><th>INV</th><th>In this listing</th><th>Status</th></tr></thead><tbody>';
                    children.forEach(function (child) {
                        const sku = String(child.sku || '');
                        const publishable = child.status === 'will_publish';
                        const checked = childShouldCheck(child);
                        if (checked) canPublish = true;
                        const role = checked
                            ? '<span class="temu-publish-role is-picked">Your pick</span>'
                            : '<span class="temu-publish-role is-suggested">Suggested sibling</span>';
                        html += '<tr>';
                        html += `<td><input type="checkbox" class="temu-publish-sku-check" data-sku="${escapeHtml(sku)}" ${checked ? 'checked' : ''} ${publishable ? '' : 'disabled'}></td>`;
                        html += `<td><span class="sku-cell"><span class="sku-cell-text">${escapeHtml(sku)}</span><button type="button" class="copy-sku-btn" data-sku="${escapeHtml(sku)}" title="Copy SKU"><i class="fas fa-copy"></i></button></span></td>`;
                        html += `<td>${escapeHtml(String(child.inv ?? 0))}</td>`;
                        html += `<td>${role}</td>`;
                        html += `<td>${publishStatusLabel(child.status, child.reason)}</td>`;
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                });
                $('#temu-publish-groups').html(html);
                $('#temu-publish-confirm').prop('disabled', !canPublish);
                if (window.ListingParentPicker) window.ListingParentPicker.bindConfirm('#temu-publish-groups', '#temu-publish-confirm');
                $('#temu-publish-progress').text('');
                updateTemuModalCopy();
            }

            $(document).on('change', '.temu-publish-sku-check', function () {
                const $group = $(this).closest('.temu-publish-group');
                const parent = String($group.attr('data-parent') || '').trim();
                const selectedCount = $group.find('.temu-publish-sku-check:checked:not(:disabled)').length;
                const single = selectedPublishMode() === 'single';
                const $role = $(this).closest('tr').find('.temu-publish-role');
                if ($role.length) {
                    if (this.checked && !this.disabled) {
                        $role.removeClass('is-suggested').addClass('is-picked').text('Your pick');
                    } else {
                        $role.removeClass('is-picked').addClass('is-suggested').text('Suggested sibling');
                    }
                }
                $group.find('.listing-publish-group-title').text(
                    parent + ' · ' + selectedCount + ' selected for ' +
                    (single ? 'single listing' + (selectedCount === 1 ? '' : 's') : 'this variation')
                );
                const anyChecked = $('#temu-publish-groups .temu-publish-sku-check:checked:not(:disabled)').length > 0;
                $('#temu-publish-confirm').prop('disabled', !anyChecked || !(window.ListingParentPicker ? window.ListingParentPicker.allReady('#temu-publish-groups') : true));
            });

            function selectedPublishGroups() {
                const groups = [];
                $('#temu-publish-groups .temu-publish-group').each(function () {
                    const parent = String($(this).attr('data-parent') || $(this).find('.temu-publish-group-head').text().split(' · ')[0] || '').trim();
                    const skus = [];
                    $(this).find('.temu-publish-sku-check:checked:not(:disabled)').each(function () {
                        const sku = String($(this).attr('data-sku') || '').trim();
                        if (sku) skus.push(sku);
                    });
                    if (skus.length) {
                        const pick = window.ListingParentPicker ? window.ListingParentPicker.read(this) : null;
                        let sendParent = parent;
                        if (pick && pick.attach === 'new' && pick.newParent) sendParent = pick.newParent;
                        else if (pick && pick.attach === 'existing' && pick.existingParent) sendParent = pick.existingParent;
                        const cat = window.ListingParentCategory ? window.ListingParentCategory.read(this) : {};
                        groups.push({ parent: sendParent, skus: skus, category_id: cat.category_id || '', category_name: cat.category_name || '', category_uuid: cat.category_uuid || '' });
                    }
                });
                if (selectedPublishMode() !== 'single') return groups;
                const out = [];
                groups.forEach(function (group) {
                    group.skus.forEach(function (sku) {
                        out.push({ parent: sku, skus: [sku] });
                    });
                });
                return out;
            }

            function markRowsListed(skus, goodsId) {
                if (!temuListingTable || !skus || !skus.length) return;
                const want = {};
                skus.forEach(function (sku) { want[String(sku).trim()] = true; });
                (temuListingTable.getRows() || []).forEach(function (row) {
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

            function requestTemuPreview(unique) {
                $.ajax({
                    url: "{{ url('/listing_temu/save-status') }}",
                    type: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    data: JSON.stringify({
                        skus: unique,
                        sku_parents: skuParentsMap(unique),
                        preview: 1,
                        channel: 'temu',
                        mode: selectedPublishMode()
                    }),
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    success: function (response) {
                        renderPublishGroups(mergePreviewGroups((response && response.groups) || [], unique));
                    },
                    error: function (xhr) {
                        const groups = mergePreviewGroups((xhr.responseJSON && xhr.responseJSON.groups) || [], unique);
                        if (groups.length) {
                            renderPublishGroups(groups);
                            return;
                        }
                        hideBsModal('temuPublishModal');
                        let msg = 'Could not build listing preview.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        showNotification('danger', msg);
                    }
                });
            }

            function openPublishPreview(skus) {
                const unique = expandPreviewSkus(skus);
                if (!unique.length) {
                    showNotification('danger', 'Select at least one SKU.');
                    return;
                }
                temuPreviewSeedSkus = unique.slice();
                $('#temu-publish-groups').html('<p class="text-muted mb-0">Loading listing preview…</p>');
                $('#temu-publish-confirm').prop('disabled', true);
                $('#temu-publish-progress').text('');
                updateTemuModalCopy();
                showBsModal('temuPublishModal');
                renderPublishGroups(fallbackGroupsFromTable(unique));
                requestTemuPreview(unique);
            }

            $(document).on('change', 'input[name="temu-publish-mode"]', function () {
                updateTemuModalCopy();
                if (!temuPreviewSeedSkus.length) return;
                $('#temu-publish-groups').html('<p class="text-muted mb-0">Updating preview…</p>');
                requestTemuPreview(temuPreviewSeedSkus);
            });

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
                return 'Publish to Temu failed.';
            }

            function publishGroup(skus, parent, category) {
                category = category || {};
                return $.ajax({
                    url: "{{ url('/listing_temu/save-status') }}",
                    type: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    data: JSON.stringify({
                        skus: skus,
                        confirmed: 1,
                        publish: 1,
                        channel: 'temu',
                        mode: selectedPublishMode(),
                        parent: parent || '',
                        category_id: category.category_id || '',
                        category_name: category.category_name || '',
                        category_uuid: category.category_uuid || ''
                    }),
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
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

            // Publish selected: the checked SKU rows go to the same review modal as the row Publish button.
            $('#bulk-publish-btn').on('click', function () {
                if (!temuListingTable) return;
                const selected = (temuListingTable.getSelectedData() || [])
                    .map(function (row) { return String(row.sku || '').trim(); })
                    .filter(function (sku) { return sku && !isParentSku(sku); });
                if (!selected.length) {
                    showNotification('danger', 'Select one or more SKUs first.');
                    return;
                }
                openPublishPreview(selected);
            });

            // ---- Incomplete products: red triangle details + Complete ----
            function incompleteError(xhr, fallback) {
                if (xhr.status === 0) return 'The request timed out or the connection dropped. Try again.';
                if (xhr.status === 419) return 'Session expired. Refresh the page and try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) return xhr.responseJSON.message;
                return fallback;
            }

            function runComplete(sku, $btn, onDone) {
                const original = $btn.html();
                $btn.prop('disabled', true).addClass('is-working').html('<i class="fas fa-spinner fa-spin"></i> Completing');
                $.ajax({
                    url: "{{ url('/listing_temu/complete-incomplete') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: { sku: sku },
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    timeout: 240000
                }).done(function (response) {
                    const goodsId = String((response && response.goods_id) || '').trim();
                    if (goodsId) markRowsListed((response && response.skus) || [sku], goodsId);
                    showNotification('success', (response && response.message) || 'Sent to Temu.');
                    if (onDone) onDone(true);
                }).fail(function (xhr) {
                    showNotification('danger', incompleteError(xhr, 'Complete failed.'));
                    if (onDone) onDone(false);
                }).always(function () {
                    $btn.prop('disabled', false).removeClass('is-working').html(original);
                });
            }

            $(document).on('click', '.temu-complete-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const sku = String($(this).attr('data-sku') || '').trim();
                if (!sku) return;
                runComplete(sku, $(this));
            });

            function renderIncompleteDetails(r) {
                let html = '<p class="mb-2"><strong>Goods ' + escapeHtml(r.goods_id) + '</strong>' +
                    (r.goods_name ? ' &middot; ' + escapeHtml(r.goods_name) : '') + '<br>' +
                    '<span class="text-muted small">SKUs in this product: ' + escapeHtml((r.skus || []).join(', ')) +
                    ' &middot; found via ' + escapeHtml(r.found_by || 'Temu') + '</span></p>';

                html += '<h6 class="mt-3">What Temu says</h6>';
                if (r.temu && r.temu.length) {
                    html += '<ul class="temu-incomplete-list">' + r.temu.map(function (t) {
                        return '<li><strong>' + escapeHtml(t.label) + ':</strong> ' + escapeHtml(t.value) + '</li>';
                    }).join('') + '</ul>';
                } else {
                    html += '<p class="text-muted small">' + (r.record_found
                        ? 'Temu returned no reason text for this product through the API.'
                        : 'Temu did not return this product from its goods list, so no reason text is available.') +
                        ' Open it in Seller Center to see its "Product to be completed" message.</p>';
                }

                html += '<h6 class="mt-3">What our masters need for Temu</h6>';
                const rows = (r.checks || []).map(function (c) {
                    const who = c.sku ? '<code>' + escapeHtml(c.sku) + '</code> &middot; ' : '';
                    return '<li class="' + (c.ok ? 'good' : 'bad') + '">' + (c.ok ? '&#10003; ' : '&#10007; ') + who + escapeHtml(c.label) +
                        (c.ok ? '' : '<span class="hint">' + escapeHtml(c.hint || 'Missing') + '</span>') + '</li>';
                });
                html += '<ul class="temu-incomplete-list">' + rows.join('') + '</ul>';
                html += r.can_complete
                    ? '<p class="text-success small mb-0">Everything we need is present. Click Complete to send it to Temu.</p>'
                    : '<p class="text-danger small mb-0">Fix the red items above, then click Complete.</p>';
                $('#temu-incomplete-body').html(html);
            }

            $(document).on('click', '.temu-incomplete-flag', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const sku = String($(this).attr('data-sku') || '').trim();
                if (!sku) return;
                $('#temu-incomplete-body').html('<p class="text-muted mb-0">Reading the product from Temu…</p>');
                $('#temu-incomplete-complete').attr('data-sku', sku).prop('disabled', true);
                $('#temu-incomplete-seller').addClass('d-none');
                showBsModal('temuIncompleteModal');
                $.ajax({
                    url: "{{ url('/listing_temu/incomplete-details') }}",
                    type: 'GET',
                    dataType: 'json',
                    data: { sku: sku },
                    headers: { 'Accept': 'application/json' },
                    timeout: 120000
                }).done(function (r) {
                    renderIncompleteDetails(r || {});
                    $('#temu-incomplete-complete').prop('disabled', false);
                    if (r && r.seller_url) $('#temu-incomplete-seller').attr('href', r.seller_url).removeClass('d-none');
                }).fail(function (xhr) {
                    $('#temu-incomplete-body').html('<p class="text-danger mb-0">' + escapeHtml(incompleteError(xhr, 'Could not read the product from Temu.')) + '</p>');
                });
            });

            $('#temu-incomplete-complete').on('click', function () {
                const sku = String($(this).attr('data-sku') || '').trim();
                if (!sku) return;
                runComplete(sku, $(this), function (ok) {
                    if (ok) hideBsModal('temuIncompleteModal');
                });
            });

            $(document).on('click', '.temu-publish-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const sku = String($(this).attr('data-sku') || $(this).data('sku') || '').trim();
                if (!sku) {
                    showNotification('danger', 'SKU is missing.');
                    return;
                }
                openPublishPreview([sku]);
            });

            $('#temu-publish-confirm').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                const groups = selectedPublishGroups();
                if (!groups.length) {
                    showNotification('danger', 'Check at least one SKU to publish.');
                    return;
                }
                const originalHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Publishing');
                $('#temuPublishModal .btn-close, #temuPublishModal [data-bs-dismiss="modal"]').prop('disabled', true);

                let index = 0;
                const ok = [];
                const fail = [];

                function next() {
                    if (index >= groups.length) {
                        $btn.prop('disabled', false).html(originalHtml);
                        $('#temuPublishModal .btn-close, #temuPublishModal [data-bs-dismiss="modal"]').prop('disabled', false);
                        if (ok.length) {
                            showNotification('success', ok.join(' '));
                        }
                        if (fail.length) {
                            showNotification('danger', fail.join(' '));
                        }
                        if (ok.length && !fail.length) {
                            hideBsModal('temuPublishModal');
                        }
                        return;
                    }
                    const group = groups[index];
                    index += 1;
                    $('#temu-publish-progress').text('Publishing ' + group.parent + ' (' + index + '/' + groups.length + ')…');
                    publishGroup(group.skus, group.parent, group).done(function (response) {
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

        });
    </script>

    <script>
        window.listingPageConfig = {
            wrap: '#temu-listing-wrap',
            tableId: 'temuListing-table',
            exportName: 'temu_listing',
            channel: 'temu',
            channelLabel: "Temu",
            ownPublishColumn: true,
            previewUrl: '/listing_temu/save-status',
            publishUrl: '/listing_temu/save-status'
        };
    </script>
    <script src="{{ asset('js/listing-page-tools.js') }}?v=20261030-bulk1"></script>
@include('partials.listing-sku-search')
@endsection
