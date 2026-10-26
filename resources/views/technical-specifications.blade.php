@extends('layouts.vertical', ['title' => 'Technical Specifications', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    @include('partials.parent-row-highlight')
    <style>
        .card.ts-master-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .card.ts-master-card .card-body { padding: 1.25rem 1.5rem; }
        .ts-master-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; }
        .ts-master-toolbar .btn { padding:.3rem .6rem; font-size:.8rem; }
        .ts-search-row { display:flex; flex-wrap:wrap; gap:.6rem; margin:0 0 .7rem; }
        .ts-search-row label {
            display:flex; flex-direction:column; gap:.2rem; min-width:180px; flex:1;
            font-size:.72rem; font-weight:700; letter-spacing:.03em; text-transform:uppercase;
        }
        .ts-search-row input {
            height:32px; border-radius:8px; border:1px solid #cbd5e1; padding:0 .6rem;
            font-size:.84rem; font-weight:500; text-transform:none; letter-spacing:0;
        }
        .ts-search-row input:focus { outline:none; box-shadow:0 0 0 3px rgba(37,99,235,.15); }
        .ts-search-parent { color:#6d28d9; }
        .ts-search-parent input { background:#f5f3ff; border-color:#c4b5fd; }
        .ts-search-sku { color:#1d4ed8; }
        .ts-search-sku input { background:#eff6ff; border-color:#93c5fd; }
        .ts-search-channel { color:#0369a1; }
        .ts-search-channel select {
            height:32px; border-radius:8px; border:1px solid #7dd3fc; padding:0 .5rem;
            font-size:.84rem; font-weight:500; text-transform:none; letter-spacing:0;
            background:#f0f9ff; color:#0f172a;
        }
        .ts-search-channel select:focus { outline:none; box-shadow:0 0 0 3px rgba(14,165,233,.18); }
        .ts-search-row .select2-container { width: 100% !important; }
        .ts-search-row .select2-container--bootstrap-5 .select2-selection--single {
            height: 32px; min-height: 32px; border-radius: 8px; font-size: .84rem; font-weight: 500;
        }
        .ts-search-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 30px; color: #0f172a; padding-left: .15rem;
        }
        .ts-search-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow { height: 30px; }
        .ts-search-channel .select2-container--bootstrap-5 .select2-selection--single { background:#f0f9ff; border-color:#7dd3fc; }
        .ts-search-category .select2-container--bootstrap-5 .select2-selection--single { background:#fffbeb; border-color:#fcd34d; }
        .select2-container--open { z-index: 1080; }
        .select2-search--dropdown .select2-search__field { border-radius: 8px; }
        .ts-search-category { color:#b45309; min-width: 280px; flex: 1.6; }
        .ts-search-category select {
            height:32px; border-radius:8px; border:1px solid #fcd34d; padding:0 .5rem;
            font-size:.84rem; font-weight:500; text-transform:none; letter-spacing:0;
            background:#fffbeb; color:#0f172a;
        }
        .ts-search-category select:focus { outline:none; box-shadow:0 0 0 3px rgba(245,158,11,.18); }
        .ts-search-quick { color:#334155; }
        .ts-search-quick input { background:#f8fafc; border-color:#cbd5e1; }
        .ts-search-any { color:#0f766e; }
        .ts-search-any input { background:#f0fdfa; border-color:#5eead4; }
        #ts-hscroll {
            overflow-x: auto;
            overflow-y: hidden;
            height: 16px;
            margin: 0 0 6px;
        }
        #ts-hscroll-inner { height: 1px; }
        #technical-specs-table { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        #technical-specs-table .tabulator-header {
            position: sticky;
            top: 0;
            z-index: 40;
            background: linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%);
            color: #fff;
        }
        #technical-specs-table .tabulator-col { background:transparent !important; border-right:1px solid rgba(255,255,255,.15); }
        #technical-specs-table .tabulator-header .tabulator-col.tabulator-frozen {
            background: #2c6ed5 !important;
            z-index: 30;
        }
        #technical-specs-table .tabulator-row .tabulator-cell.tabulator-frozen {
            background: #fff;
            z-index: 12;
        }
        #technical-specs-table .tabulator-row.tabulator-row-even .tabulator-cell.tabulator-frozen {
            background: #f8fafc;
        }
        #technical-specs-table .tabulator-row:hover:not([class*="parent-row"]) .tabulator-cell.tabulator-frozen {
            background: #e8f0fe !important;
        }
        #technical-specs-table .tabulator-row.tabulator-selected .tabulator-cell.tabulator-frozen {
            background: #d6e6ff;
        }
        #technical-specs-table .tabulator-header .tabulator-col[tabulator-field="Parent"],
        #technical-specs-table .tabulator-header .tabulator-col[tabulator-field="SKU"] {
            z-index: 32;
            background: #2c6ed5 !important;
        }
        #technical-specs-table .tabulator-header .tabulator-col[tabulator-field="SKU"] {
            box-shadow: 3px 0 6px rgba(15, 23, 42, .18);
        }
        #technical-specs-table .tabulator-row .tabulator-cell[tabulator-field="Parent"],
        #technical-specs-table .tabulator-row .tabulator-cell[tabulator-field="SKU"] {
            z-index: 13;
            background: #fff;
        }
        #technical-specs-table .tabulator-row .tabulator-cell[tabulator-field="SKU"] {
            box-shadow: 3px 0 6px rgba(15, 23, 42, .12);
        }
        #technical-specs-table .tabulator-row.tabulator-row-even .tabulator-cell[tabulator-field="Parent"],
        #technical-specs-table .tabulator-row.tabulator-row-even .tabulator-cell[tabulator-field="SKU"] {
            background: #f8fafc;
        }
        #technical-specs-table .tabulator-row:hover:not([class*="parent-row"]) .tabulator-cell[tabulator-field="Parent"],
        #technical-specs-table .tabulator-row:hover:not([class*="parent-row"]) .tabulator-cell[tabulator-field="SKU"] {
            background: #e8f0fe !important;
        }
        #technical-specs-table .tabulator-row.tabulator-selected .tabulator-cell[tabulator-field="Parent"],
        #technical-specs-table .tabulator-row.tabulator-selected .tabulator-cell[tabulator-field="SKU"] {
            background: #d6e6ff;
        }
        #technical-specs-table .tabulator-header,
        #technical-specs-table .tabulator-header .tabulator-headers,
        #technical-specs-table .tabulator-col,
        #technical-specs-table .tabulator-col-content,
        #technical-specs-table .tabulator-col-title-holder {
            height: 200px !important;
        }
        #technical-specs-table .tabulator-col-content {
            display: flex !important;
            align-items: flex-end;
            justify-content: center;
            padding: 8px 2px !important;
        }
        #technical-specs-table .tabulator-col-title-holder {
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        #technical-specs-table .tabulator-col-title {
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-height: 184px;
            line-height: 1.15;
            letter-spacing: .03em;
        }
        #technical-specs-table .tabulator-cell {
            text-align: center;
        }
        #technical-specs-table .tabulator-col-title .ts-col-icon {
            display: inline-block;
            transform: rotate(90deg);
            font-size: 14px;
        }
        #technical-specs-table .ts-cat-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            cursor: help;
            vertical-align: middle;
            transition: transform .12s ease, box-shadow .12s ease;
        }
        #technical-specs-table .ts-cat-dot--ok { background: #16a34a; box-shadow: 0 0 0 2px rgba(22,163,74,.18); }
        #technical-specs-table .ts-cat-dot--none { background: #dc2626; box-shadow: 0 0 0 2px rgba(220,38,38,.18); }
        #technical-specs-table .ts-cat-dot:hover { transform: scale(1.35); }
        #technical-specs-table .tabulator-cell[tabulator-field^="category_"] { overflow: visible; }
        #technical-specs-table .tabulator-row:hover .tabulator-cell[tabulator-field^="category_"] { z-index: 6; }
        #technical-specs-table .ts-cat-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        #technical-specs-table .ts-sku-cell {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        #technical-specs-table .ts-sku-copy {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border: 0;
            border-radius: 5px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            color: #475569;
            box-shadow: 0 1px 6px rgba(15, 23, 42, .25);
            font-size: 11px;
            line-height: 1;
            cursor: pointer;
            opacity: 0;
            pointer-events: none;
        }
        #technical-specs-table .tabulator-cell:hover .ts-sku-copy,
        #technical-specs-table .ts-sku-copy:focus,
        #technical-specs-table .ts-sku-copy.is-copied {
            opacity: 1;
            pointer-events: auto;
            color: #1d4ed8;
        }
        #technical-specs-table .ts-cat-copy {
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            border: 0;
            border-radius: 6px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            color: #475569;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .22);
            font-size: 11px;
            line-height: 1;
            cursor: pointer;
            opacity: 0;
            pointer-events: none;
            z-index: 7;
        }
        #technical-specs-table .ts-cat-wrap:hover .ts-cat-copy,
        #technical-specs-table .ts-cat-copy:focus,
        #technical-specs-table .ts-cat-copy.is-copied {
            opacity: 1;
            pointer-events: auto;
            color: #1d4ed8;
        }
        .ts-cat-tip {
            position: fixed;
            z-index: 2000;
            max-width: 420px;
            padding: 6px 10px;
            border-radius: 6px;
            background: #0f172a;
            color: #fff;
            font-size: 12px;
            line-height: 1.35;
            pointer-events: none;
            box-shadow: 0 6px 18px rgba(15,23,42,.25);
        }
        #technical-specs-table .ts-missing {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-weight: 700;
            color: #b91c1c;
            white-space: nowrap;
        }
        #technical-specs-table .tabulator-header-filter input {
            background:rgba(255,255,255,.95); border:none; border-radius:4px; color:#333;
            padding:4px 6px; width:100%; font-size:12px;
        }
        #technical-specs-table .tabulator-tableholder::-webkit-scrollbar { width: 10px; height: 0; }
        #technical-specs-table .tabulator-tableholder { scrollbar-width: thin; }
        #technical-specs-table .tabulator-row:nth-child(even) { background:#f8fafc; }
        #technical-specs-table .tabulator-row:hover { background:#e8f0fe !important; }
        #technical-specs-table .tabulator-cell { font-size:13.2px; color:#475569; }
        .toast-container { position:fixed; top:20px; right:20px; z-index:9999; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        #ts-column-dropdown.show {
            position: fixed;
            left: 0;
            right: 0;
            width: 100vw;
            max-width: none;
            min-width: 0;
            height: 33vh;
            max-height: 33vh;
            overflow: hidden;
            padding: 8px 10px;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, .18);
            display: flex;
            flex-direction: column;
            z-index: 1080;
        }
        #ts-column-dropdown .col-vis-groups {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin: 0;
            flex: 1;
            min-height: 0;
            height: 100%;
        }
        #ts-column-dropdown .col-vis-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 6px;
            min-width: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }
        #ts-column-dropdown .col-vis-group-title,
        #ts-column-dropdown .col-vis-cat-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            margin: 0;
            padding: 2px 4px;
            user-select: none;
            cursor: pointer;
        }
        #ts-column-dropdown .col-vis-group-title {
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 6px;
            flex: 0 0 auto;
        }
        #ts-column-dropdown .ts-ch-icon {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 13px;
            flex: 0 0 auto;
        }
        #ts-column-dropdown .ts-ch-icon.ebay { background: #e53238; }
        #ts-column-dropdown .ts-ch-icon.amazon { background: #ff9900; color: #111; }
        #ts-column-dropdown .ts-ch-icon.temu { background: #f97316; font-weight: 800; font-size: 12px; font-family: Arial, sans-serif; }
        #ts-column-dropdown .col-vis-cats {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            overflow-y: hidden;
            flex: 1;
            min-height: 0;
            padding-bottom: 2px;
        }
        #ts-column-dropdown .col-vis-cat {
            flex: 0 0 168px;
            min-width: 148px;
            max-width: 220px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        #ts-column-dropdown .col-vis-cat-title {
            border-bottom: 1px solid #eef2f7;
            background: #f1f5f9;
            border-radius: 6px 6px 0 0;
            text-transform: none;
            letter-spacing: 0;
            font-size: 0.75rem;
        }
        #ts-column-dropdown .col-vis-cat-title span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #ts-column-dropdown .col-vis-group-list {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            margin: 0;
            padding: 2px 0;
            list-style: none;
        }
        #ts-column-dropdown .col-vis-item { list-style: none; margin: 0; padding: 0; }
        #ts-column-dropdown .col-vis-item > label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 3px 6px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
            font-size: 0.78rem;
            user-select: none;
        }
        #ts-column-dropdown input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            width: 14px;
            height: 14px;
            accent-color: #0d6efd;
            cursor: pointer;
        }
        #ts-column-dropdown .col-vis-item > label:hover,
        #ts-column-dropdown .col-vis-cat-title:hover,
        #ts-column-dropdown .col-vis-group-title:hover {
            background: rgba(37, 99, 235, .06);
        }
        @media (max-width: 900px) {
            #ts-column-dropdown .col-vis-groups { grid-template-columns: 1fr; overflow-y: auto; }
        }
        #editSpecsModal .ts-edit-dialog {
            width: min(1440px, 96vw);
            max-width: min(1440px, 96vw);
            margin: 8px auto;
        }
        #editSpecsModal .modal-content {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .28);
        }
        #editSpecsModal .modal-header-gradient {
            background: linear-gradient(120deg, #1d4ed8 0%, #6d28d9 55%, #db2777 100%);
            border: 0;
            padding: .45rem 1rem;
        }
        #editSpecsModal .modal-body {
            background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 72px);
            padding: .55rem .85rem .45rem;
        }
        #editSpecsModal .ts-edit-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            margin-bottom: .4rem;
        }
        #editSpecsModal .ts-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            border-radius: 999px;
            padding: .22rem .65rem;
            font-size: .78rem;
            font-weight: 600;
            line-height: 1.3;
        }
        #editSpecsModal .ts-chip-sku { background: #dbeafe; color: #1e40af; }
        #editSpecsModal .ts-chip-product { background: #ede9fe; color: #5b21b6; }
        #editSpecsModal .ts-chip-filled { background: #dcfce7; color: #166534; }
        #editSpecsModal .ts-chip-blank { background: #ffedd5; color: #c2410c; }
        #editSpecsModal #bulkEditBanner {
            border: 0;
            border-radius: 10px;
            background: #e0f2fe;
            color: #0c4a6e;
        }
        #editSpecsModal #bulkSkuList {
            background: #fff;
            border-color: #bfdbfe !important;
            border-radius: 8px;
        }
        #editSpecsModal .ts-spec-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
        }
        #editSpecsModal .ts-spec-grid.ts-spec-grid-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        #editSpecsModal .ts-spec-card {
            border-radius: 8px;
            padding: .22rem .45rem .28rem;
            border: 1px solid transparent;
            min-width: 0;
        }
        #editSpecsModal .ts-spec-card.is-filled {
            background: #f0fdf4;
            border-color: #86efac;
        }
        #editSpecsModal .ts-spec-card.is-blank {
            background: #fff7ed;
            border-color: #fdba74;
        }
        #editSpecsModal .ts-spec-card .form-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .01em;
            margin-bottom: .15rem;
            text-transform: uppercase;
        }
        #editSpecsModal .ts-spec-card.is-filled .form-label { color: #166534; }
        #editSpecsModal .ts-spec-card.is-blank .form-label { color: #c2410c; }
        #editSpecsModal .ts-spec-card .form-control {
            height: 28px;
            padding: .1rem .45rem;
            font-size: .82rem;
            border-radius: 6px;
            background: #fff;
        }
        #editSpecsModal .ts-spec-card.is-filled .form-control { border-color: #86efac; }
        #editSpecsModal .ts-spec-card.is-blank .form-control { border-color: #fb923c; }
        #editSpecsModal .modal-footer {
            background: #fff;
            border-top: 1px solid #e2e8f0;
            padding: .4rem .85rem;
        }
        #editSpecsModal #modalSaveBtn {
            background: linear-gradient(120deg, #16a34a, #0d9488);
            border: 0;
        }
        #ebaySyncOutcome:not(.d-none) {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: .35rem;
            padding: .35rem 0 .85rem;
        }
        #ebaySyncOutcome .ts-sync-mark {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dcfce7;
            color: #16a34a;
        }
        #ebaySyncOutcome .ts-sync-mark i { font-size: 3.4rem; }
        #ebaySyncOutcome.is-failed .ts-sync-mark {
            width: auto;
            height: auto;
            border-radius: 0;
            background: transparent;
            color: #dc2626;
        }
        #ebaySyncOutcome.is-failed .ts-sync-mark i { font-size: 4.6rem; }
        #ebaySyncOutcome .ts-sync-outcome-title { font-size: 1.2rem; font-weight: 700; }
        #ebaySyncOutcome.is-success .ts-sync-outcome-title { color: #166534; }
        #ebaySyncOutcome.is-failed .ts-sync-outcome-title { color: #b91c1c; }
        #ebaySyncSkuList { list-style: none; padding-left: 0; }
        #ebaySyncSkuList .ts-sync-row {
            display: flex;
            align-items: flex-start;
            gap: .45rem;
            margin: .2rem 0;
        }
        #ebaySyncSkuList .ts-sync-row i { margin-top: .15rem; }
        @media (max-width: 1100px) {
            #editSpecsModal .ts-spec-grid,
            #editSpecsModal .ts-spec-grid.ts-spec-grid-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 700px) {
            #editSpecsModal .ts-spec-grid,
            #editSpecsModal .ts-spec-grid.ts-spec-grid-2,
            #editSpecsModal .ts-spec-grid.ts-spec-grid-3 { grid-template-columns: 1fr; }
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Technical Specifications',
        'sub_title' => 'Spec key/value pairs by SKU (same product set as Description Master)',
    ])

    <div class="toast-container" id="toastContainer"></div>

    <div class="row">
        <div class="col-12">
            <div class="card ts-master-card">
                <div class="card-body">
                    <div class="mb-3 ts-master-toolbar">
                        @include('partials.parent-playback-controls')
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-outline-primary" type="button"
                                id="tsColumnVisibility" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                aria-expanded="false" title="Columns">
                                <i class="fas fa-columns"></i>
                            </button>
                            <div class="dropdown-menu" id="ts-column-dropdown" data-bs-popper="static" aria-labelledby="tsColumnVisibility"></div>
                        </div>
                        <button type="button" id="pullEbayBtn" class="btn btn-sm btn-outline-primary" title="Pull item specifics from eBay for the selected rows">
                            <i class="fas fa-cloud-download-alt"></i> Pull from eBay 1
                        </button>
                        <button type="button" id="syncCategoriesBtn" class="btn btn-sm btn-outline-success" title="Fetch the live eBay 1 category of the selected rows (or of every SKU when none are selected) and update it here">
                            <i class="fas fa-sync-alt"></i> Sync categories
                        </button>
                        <button type="button" id="pushEbayBtn" class="btn btn-sm btn-outline-danger" title="Push technical specifications to eBay 1 for the selected rows">
                            <i class="fas fa-cloud-upload-alt"></i> Push to eBay 1
                        </button>
                        <button type="button" id="clearSpecsBtn" class="btn btn-sm btn-outline-secondary" title="Clear the saved specifications of the selected rows in this app. eBay is not changed.">
                            <i class="fas fa-eraser"></i> Clear
                        </button>
                        <button type="button" id="exportBtn" class="btn btn-primary btn-sm">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button type="button" id="importBtn" class="btn btn-info btn-sm">
                            <i class="fas fa-upload"></i> Import
                        </button>
                        <button type="button" id="maxAlertBtn" class="btn btn-sm btn-outline-danger" title="Show only SKUs with the highest missing count">
                            <i class="fas fa-exclamation-triangle"></i> Max alerts
                        </button>
                        <span class="badge bg-danger d-none" id="maxAlertBatch">0 rows</span>
                        <select id="rowTypeFilter" class="form-select form-select-sm" style="width:auto; min-width:110px;" title="Show SKU rows, parent rows, or both">
                            <option value="sku" selected>SKU</option>
                            <option value="parent">Parent</option>
                            <option value="all">All</option>
                        </select>
                        <select id="statusFilter" class="form-select form-select-sm" style="width:auto; min-width:200px;">
                            <option value="all">All rows</option>
                            <option value="has">Has Specifications</option>
                            <option value="missing">Missing Specifications</option>
                        </select>
                        <span class="text-muted small" id="rowCountBadge">0 products</span>
                        <span class="text-muted small" id="selectedCountBadge">0 selected</span>
                        <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
                    </div>
                    <div class="ts-search-row">
                        <label class="ts-search-channel">Channel
                            <select id="channelFilter">
                                <option value="">All channels</option>
                            </select>
                        </label>
                        <label class="ts-search-category">Category
                            <select id="categoryFilter">
                                <option value="">All categories</option>
                            </select>
                        </label>
                        <label class="ts-search-quick">Quick search
                            <input type="search" id="quickSearch" placeholder="SKU, parent, category, specification…" autocomplete="off">
                        </label>
                        <label class="ts-search-parent">Parent
                            <input type="search" id="parentSearch" placeholder="Search parent column" autocomplete="off">
                        </label>
                        <label class="ts-search-sku">SKU
                            <input type="search" id="skuSearch" placeholder="Search SKU column" autocomplete="off">
                        </label>
                        <label class="ts-search-any">Anything
                            <input type="search" id="anythingSearch" placeholder="Search every other column" autocomplete="off">
                        </label>
                    </div>

                    <div id="ts-hscroll" title="Scroll columns"><div id="ts-hscroll-inner"></div></div>
                    <div id="technical-specs-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editSpecsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog ts-edit-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="editModalTitle"><i class="fas fa-edit me-2"></i>Edit Technical Specifications</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalSku">
                    <div id="bulkEditBanner" class="alert alert-info py-2 px-3 mb-2" style="display:none;">
                        <i class="fas fa-layer-group me-1"></i>
                        <strong>Bulk Edit:</strong> Saving updates only the fields you change on
                        <strong id="bulkEditCount">0</strong> selected SKU(s). Other values stay as they are.
                    </div>
                    <div class="ts-edit-meta">
                        <span class="ts-chip ts-chip-sku"><i class="fas fa-barcode"></i> <span id="modalSkuLabel"></span></span>
                        <span class="ts-chip ts-chip-product"><i class="fas fa-box"></i> <span id="modalProductLabel"></span></span>
                        <span class="ts-chip ts-chip-filled" id="specFilledChip">0 filled</span>
                        <span class="ts-chip ts-chip-blank" id="specBlankChip">0 blank</span>
                    </div>
                    <div id="bulkSkuListWrap" class="mb-2" style="display:none;">
                        <div class="small text-muted mb-1">Applying to SKUs:</div>
                        <div id="bulkSkuList" class="small border rounded p-2" style="max-height:72px; overflow:auto;"></div>
                    </div>
                    <div id="specFields" class="ts-spec-grid"></div>
                    <p id="specFieldsEmpty" class="text-muted small mb-0 d-none">No specifications for this category yet.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="modalSaveBtn">
                        <i class="fas fa-cloud-upload-alt"></i> <span id="modalSaveBtnLabel">Save and push</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewSpecsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="viewSpecsTitle"><i class="fas fa-eye me-2"></i>Technical Specifications</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewSpecsSubtitle" class="small text-muted mb-2"></div>
                    <div id="viewSpecsContent" class="p-3 bg-light border rounded" style="white-space:pre-wrap; word-break:break-word; min-height:4rem; font-family:ui-monospace,Consolas,monospace; font-size:12px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="viewCopyBtn"><i class="fas fa-copy me-1"></i> Copy</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ebaySyncModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ebaySyncTitle">eBay</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="ebaySyncOutcome" class="d-none"></div>
                    <div id="ebaySyncAlert" class="alert alert-danger d-none">
                        <div class="fw-semibold"><i class="fas fa-exclamation-triangle me-2"></i><span id="ebaySyncAlertText"></span></div>
                        <ul id="ebaySyncAlertList" class="mb-0 mt-2"></ul>
                    </div>
                    <p id="ebaySyncBody" class="mb-2"></p>
                    <ul id="ebaySyncSkuList" class="small mb-0" style="max-height:240px; overflow:auto;"></ul>
                    <div id="ebaySyncStatus" class="text-muted small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" id="ebaySyncCancel">Cancel</button>
                    <button type="button" class="btn btn-primary" id="ebaySyncConfirm">Continue</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        (function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            let table = null;
            let allRows = [];
            let editModal = null;
            let viewModal = null;
            let bulkEditSkus = [];
            let navParent = null;
            let maxAlertOnly = false;
            let parentPlayback = null;
            let ebayAspectNames = [];
            const aspectFields = {};
            const TS_COL_VIS_KEY = 'technical_specs_col_vis_v1';
            const TS_COL_GROUPS = [
                { key: 'ebay', label: 'eBay', icon: 'ebay', mark: '<i class="fab fa-ebay"></i>' },
                { key: 'amz', label: 'Amz', icon: 'amazon', mark: '<i class="fab fa-amazon"></i>' },
                { key: 'temu', label: 'Temu', icon: 'temu', mark: 'T' },
            ];

            function toast(message, type) {
                const wrap = document.getElementById('toastContainer');
                if (!wrap) return;
                const el = document.createElement('div');
                el.className = 'alert alert-' + (type === 'error' ? 'danger' : 'success') + ' alert-dismissible fade show shadow-sm py-2 px-3 mb-2';
                el.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                wrap.appendChild(el);
                setTimeout(() => el.remove(), 3200);
            }

            function copyCellText(btn, value, noun) {
                if (!value) return;
                const done = function () {
                    btn.classList.add('is-copied');
                    btn.innerHTML = '<i class="fas fa-check"></i>';
                    toast('Copied ' + value, 'success');
                    setTimeout(function () {
                        btn.classList.remove('is-copied');
                        btn.innerHTML = '<i class="fas fa-copy"></i>';
                    }, 1200);
                };
                const fallback = function () {
                    const ta = document.createElement('textarea');
                    ta.value = value;
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    try { document.execCommand('copy'); done(); }
                    catch (err) { toast('Could not copy ' + noun + '.', 'error'); }
                    ta.remove();
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value).then(done).catch(fallback);
                } else {
                    fallback();
                }
            }

            function categoryColumn(title, field) {
                return {
                    title: title,
                    field: field,
                    minWidth: 52,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    headerTooltip: title,
                    width: 52,
                    formatter: function (cell) {
                        const v = String(cell.getValue() || '').trim();
                        const tip = v ? v : 'No ' + title.replace(/^Category\s*/i, '') + ' category';
                        const copy = v
                            ? '<button type="button" class="ts-cat-copy" title="Copy category"><i class="fas fa-copy"></i></button>'
                            : '';
                        return '<span class="ts-cat-wrap"><span class="ts-cat-dot ' + (v ? 'ts-cat-dot--ok' : 'ts-cat-dot--none') + '" data-tip="' + esc(tip) + '" aria-label="' + esc(tip) + '"></span>' + copy + '</span>';
                    },
                    cellClick: function (e, cell) {
                        const btn = e.target.closest && e.target.closest('.ts-cat-copy');
                        if (!btn) return;
                        e.stopPropagation();
                        const value = String(cell.getValue() || '').trim();
                        if (!value) return;
                        const done = function () {
                            btn.classList.add('is-copied');
                            btn.innerHTML = '<i class="fas fa-check"></i>';
                            toast('Copied ' + value, 'success');
                            setTimeout(function () {
                                btn.classList.remove('is-copied');
                                btn.innerHTML = '<i class="fas fa-copy"></i>';
                            }, 1200);
                        };
                        const fallback = function () {
                            const ta = document.createElement('textarea');
                            ta.value = value;
                            ta.style.position = 'fixed';
                            ta.style.left = '-9999px';
                            document.body.appendChild(ta);
                            ta.select();
                            try { document.execCommand('copy'); done(); }
                            catch (err) { toast('Could not copy category.', 'error'); }
                            ta.remove();
                        };
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(value).then(done).catch(fallback);
                        } else {
                            fallback();
                        }
                    },
                };
            }

            function esc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function specsText(row) {
                return String(row.technical_specifications || row.specifications_text || '').trim();
            }

            function updateRowCount(n) {
                const badge = document.getElementById('rowCountBadge');
                if (badge) badge.textContent = n + ' product' + (n === 1 ? '' : 's');
            }

            function updateSelectedCount() {
                const badge = document.getElementById('selectedCountBadge');
                if (!badge || !table) return;
                badge.textContent = table.getSelectedData().length + ' selected';
            }

            function searchValue(id) {
                const el = document.getElementById(id);
                return String(el && el.value || '').trim().toLowerCase();
            }

            function containsText(value, query) {
                return String(value || '').toLowerCase().indexOf(query) !== -1;
            }

            function channelCategoryField(name) {
                const n = String(name || '').toLowerCase();
                if (n.indexOf('temu') !== -1) return 'category_temu';
                if (n.indexOf('amazon') !== -1 || n.indexOf('amz') !== -1) return 'category_amz';
                if (n.indexOf('ebay') !== -1 || n.indexOf('e bay') !== -1) return 'category_ebay';
                return '';
            }

            let categorySearchQuery = '';

            function unmountSearchSelect(sel) {
                if (window.jQuery && jQuery.fn.select2 && jQuery(sel).hasClass('select2-hidden-accessible')) {
                    jQuery(sel).select2('destroy');
                }
            }

            function applyCategorySearch() {
                applyStatusFilter();
                if (table) buildTsColumnPicker();
            }

            function mountSearchSelect(sel, placeholder) {
                if (!window.jQuery || !jQuery.fn.select2) return;
                const $sel = jQuery(sel);
                if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
                $sel.select2({
                    theme: 'bootstrap-5',
                    placeholder: placeholder,
                    allowClear: true,
                    width: '100%',
                    minimumResultsForSearch: 0,
                    dropdownParent: jQuery(document.body),
                });
                $sel.off('.tsQuick');
                $sel.on('select2:open.tsQuick', function () {
                    const field = document.querySelector('.select2-container--open .select2-search__field');
                    if (!field) return;
                    field.placeholder = 'Quick search';
                    field.focus();
                    if (sel.id !== 'categoryFilter') return;
                    field.oninput = function () {
                        categorySearchQuery = field.value || '';
                        applyStatusFilter();
                    };
                });
                $sel.on('select2:close.tsQuick', function () {
                    if (sel.id !== 'categoryFilter' || !categorySearchQuery) return;
                    categorySearchQuery = '';
                    applyStatusFilter();
                });
                $sel.on('select2:select.tsQuick select2:clear.tsQuick', function () {
                    categorySearchQuery = '';
                    if (sel.id === 'channelFilter') fillCategoryFilter();
                    applyCategorySearch();
                });
            }

            function fillChannelFilter(names) {
                const sel = document.getElementById('channelFilter');
                if (!sel) return;
                const current = sel.value;
                unmountSearchSelect(sel);
                sel.innerHTML = '<option value="">All channels</option>';
                (names || []).forEach(function (name) {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    sel.appendChild(option);
                });
                if (current && Array.from(sel.options).some(function (option) { return option.value === current; })) {
                    sel.value = current;
                }
                mountSearchSelect(sel, 'All channels');
            }

            function fillCategoryFilter() {
                const sel = document.getElementById('categoryFilter');
                if (!sel) return;
                const current = sel.value;
                unmountSearchSelect(sel);
                const field = channelCategoryField(document.getElementById('channelFilter')?.value || '');
                const fields = field ? [field] : ['category_ebay', 'category_amz', 'category_temu'];
                const seen = new Map();
                allRows.forEach(function (row) {
                    fields.forEach(function (key) {
                        const value = String(row[key] || '').trim();
                        if (!value) return;
                        const id = value.toLowerCase();
                        if (!seen.has(id)) seen.set(id, value);
                    });
                });
                const names = Array.from(seen.values()).sort(function (a, b) { return a.localeCompare(b); });
                sel.innerHTML = '<option value="">All categories</option>';
                names.forEach(function (name) {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    sel.appendChild(option);
                });
                sel.value = current && names.indexOf(current) !== -1 ? current : '';
                mountSearchSelect(sel, 'All categories');
            }

            function categoryText(data) {
                return [data.category_ebay, data.category_amz, data.category_temu].join('\n');
            }

            function anythingText(data) {
                const parts = [
                    data.category_ebay,
                    data.category_amz,
                    data.category_temu,
                    specsText(data),
                ];
                ebayAspectNames.forEach(function (name) {
                    parts.push(data[aspectKey(name)]);
                });
                (data.description_v2_specifications || []).forEach(function (spec) {
                    parts.push(spec && spec.key, spec && spec.value);
                });
                return parts.join('\n');
            }

            function applyStatusFilter() {
                if (!table) return;
                const mode = document.getElementById('statusFilter')?.value || 'all';
                const rowType = document.getElementById('rowTypeFilter')?.value || 'sku';
                const parentQ = searchValue('parentSearch');
                const skuQ = searchValue('skuSearch');
                const quickQ = searchValue('quickSearch');
                const anyQ = searchValue('anythingSearch');
                const channelName = document.getElementById('channelFilter')?.value || '';
                const channelField = channelCategoryField(channelName);
                const categoryPick = document.getElementById('categoryFilter')?.value || '';
                const typedCategory = String(categorySearchQuery || '').trim().toLowerCase();
                const categoryFields = channelField ? [channelField] : ['category_ebay', 'category_amz', 'category_temu'];
                function categoryCell(data, key) {
                    return String(data[key] || '').replace(/\s+/g, ' ').trim();
                }
                function rowMatchesCategory(data, query, exact) {
                    const needle = String(query || '').replace(/\s+/g, ' ').trim().toLowerCase();
                    if (!needle) return true;
                    return categoryFields.some(function (key) {
                        const value = categoryCell(data, key).toLowerCase();
                        if (!value) return false;
                        return exact ? value === needle : value.indexOf(needle) !== -1;
                    });
                }
                function passesBase(data) {
                    const isParent = window.isPmParentRowData
                        ? window.isPmParentRowData(data)
                        : String(data.SKU || '').toUpperCase().includes('PARENT');
                    if (rowType === 'sku' && isParent) return false;
                    if (rowType === 'parent' && !isParent) return false;
                    if (navParent != null && String(data.Parent || '') !== String(navParent)) return false;
                    if (parentQ && !containsText(data.Parent || data.title150 || '', parentQ)) return false;
                    if (skuQ && !containsText(data.SKU, skuQ)) return false;
                    if (typedCategory) {
                        if (!rowMatchesCategory(data, typedCategory, false)) return false;
                    } else if (categoryPick) {
                        if (!rowMatchesCategory(data, categoryPick, true)) return false;
                    } else if (channelField && !categoryCell(data, channelField)) {
                        return false;
                    }
                    if (quickQ && !containsText([data.SKU, data.Parent, data.title150, categoryText(data), anythingText(data)].join('\n'), quickQ)) return false;
                    if (anyQ && !containsText(anythingText(data), anyQ)) return false;
                    const text = specsText(data);
                    if (mode === 'has') return text !== '';
                    if (mode === 'missing') return text === '';
                    return true;
                }
                let maxMissing = 0;
                if (maxAlertOnly) {
                    table.getData().forEach(function (data) {
                        if (!passesBase(data)) return;
                        const n = missingSpecs(data).length;
                        if (n > maxMissing) maxMissing = n;
                    });
                }
                table.setFilter(function (data) {
                    if (!passesBase(data)) return false;
                    if (maxAlertOnly && missingSpecs(data).length !== maxMissing) return false;
                    return true;
                });
                const shown = table.getDataCount('active');
                updateRowCount(shown);
                updateSelectedCount();
                const batch = document.getElementById('maxAlertBatch');
                const btn = document.getElementById('maxAlertBtn');
                if (batch) {
                    if (maxAlertOnly) {
                        batch.textContent = shown + ' row' + (shown === 1 ? '' : 's') + ' · max ' + maxMissing;
                        batch.classList.remove('d-none');
                    } else {
                        batch.classList.add('d-none');
                    }
                }
                if (btn) {
                    btn.classList.toggle('btn-danger', maxAlertOnly);
                    btn.classList.toggle('btn-outline-danger', !maxAlertOnly);
                }
            }

            function aspectKey(name) {
                if (aspectFields[name]) return aspectFields[name];
                let base = 'ebay_aspect_' + String(name).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
                if (!base || base === 'ebay_aspect_') base = 'ebay_aspect';
                let key = base;
                const taken = Object.values(aspectFields);
                let n = 2;
                while (taken.indexOf(key) !== -1) {
                    key = base + '_' + n;
                    n++;
                }
                aspectFields[name] = key;
                return key;
            }

            function rowRequiresAspect(row, name) {
                return (row.ebay_required || []).some(function (n) {
                    return String(n).toLowerCase() === String(name).toLowerCase();
                });
            }

            function missingSpecs(row) {
                const names = [];
                const source = Array.isArray(row.ebay_required_only) ? row.ebay_required_only : (row.ebay_required || []);
                source.forEach(function (name) {
                    if (!isRealAspect(name)) return;
                    const value = (row.ebay_values || {})[name];
                    if (!String(value == null ? row[aspectKey(name)] : value).trim()) names.push(name);
                });
                return names;
            }

            // Fixed values for every row. MPN always equals the row's own SKU (parent rows are not listed, so they keep what is saved).
            const AUTOFILL_FIXED = { brand: '5 Core', condition: 'New', countryoforigin: 'China' };

            function specNameKey(name) {
                return String(name || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
            }

            function autofillValue(row, name) {
                const key = specNameKey(name);
                if (Object.prototype.hasOwnProperty.call(AUTOFILL_FIXED, key)) return AUTOFILL_FIXED[key];
                if (key === 'mpn') {
                    const sku = String(row.SKU || '').trim();
                    if (!sku || /^parent\s/i.test(sku)) return null;
                    return sku;
                }
                return null;
            }

            function stampAspectFields(row) {
                const values = row.ebay_values || {};
                row.ebay_values = values;
                ebayAspectNames.forEach(function (name) {
                    const required = rowRequiresAspect(row, name);
                    const auto = required ? autofillValue(row, name) : null;
                    if (auto !== null) values[name] = auto;
                    row[aspectKey(name)] = required ? (values[name] || '') : '';
                });
            }

            function ebayAspectColumns() {
                return ebayAspectNames.map(function (name) {
                    return {
                        title: name,
                        field: aspectKey(name),
                        minWidth: 52,
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerTooltip: name,
                        editor: 'input',
                        editable: function (cell) {
                            const d = cell.getRow().getData();
                            return rowRequiresAspect(d, name) && autofillValue(d, name) === null;
                        },
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            if (!rowRequiresAspect(d, name)) return '<span class="text-muted">—</span>';
                            const v = String(cell.getValue() || '').trim();
                            if (!v) return '<i class="fas fa-exclamation-triangle text-danger" title="Blank"></i>';
                            return esc(v);
                        },
                        cellEdited: async function (cell) {
                            const d = cell.getRow().getData();
                            const value = String(cell.getValue() || '').trim();
                            const specs = (d.description_v2_specifications || []).map(function (s) {
                                return { key: s.key, value: s.value };
                            });
                            const idx = specs.findIndex(function (s) {
                                return String(s.key || '').trim().toLowerCase() === name.toLowerCase();
                            });
                            if (value === '') {
                                if (idx >= 0) specs.splice(idx, 1);
                            } else if (idx >= 0) {
                                specs[idx] = { key: name, value: value };
                            } else {
                                specs.push({ key: name, value: value });
                            }
                            try {
                                const json = await saveSpecs(d.SKU, '', specs);
                                applySaveToRow(d.SKU, json, json.technical_specifications || '');
                                toast('Saved ' + name + ' for ' + d.SKU, 'success');
                            } catch (e) {
                                toast(e.message || 'Save failed', 'error');
                                cell.restoreOldValue();
                            }
                        },
                    };
                });
            }

            async function saveSpecs(sku, text, specs) {
                const body = {
                    sku: sku,
                    technical_specifications: text,
                    specifications_text: text,
                };
                if (Array.isArray(specs)) body.description_v2_specifications = specs;
                const res = await fetch('/technical-specifications/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(body),
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    throw new Error(json.message || 'Save failed');
                }
                return json;
            }

            function applySaveToRow(sku, json, fallbackText) {
                const text = json.technical_specifications != null ? json.technical_specifications : fallbackText;
                const specs = json.description_v2_specifications || [];
                const tRow = table?.getRows().find(r => r.getData().SKU === sku);
                if (tRow) {
                    const data = tRow.getData();
                    const byKey = {};
                    specs.forEach(function (s) {
                        const k = String(s.key || '').trim().toLowerCase();
                        if (k) byKey[k] = String(s.value || '');
                    });
                    const patch = {
                        technical_specifications: text,
                        specifications_text: text,
                        description_v2_specifications: specs,
                        ebay_values: Object.assign({}, data.ebay_values || {}),
                    };
                    ebayAspectNames.forEach(function (name) {
                        const auto = autofillValue(data, name);
                        const value = rowRequiresAspect(data, name)
                            ? (auto !== null ? auto : (byKey[String(name).toLowerCase()] || ''))
                            : '';
                        patch[aspectKey(name)] = value;
                        if (rowRequiresAspect(data, name)) patch.ebay_values[name] = value;
                    });
                    tRow.update(patch);
                }
            }

            function resolveBulkEditSkus(clickedRow) {
                const clickedSku = String(clickedRow.SKU || '').trim();
                const selected = (table ? table.getSelectedData() : [])
                    .map(r => String(r.SKU || '').trim())
                    .filter(Boolean);
                const set = new Set(selected);
                if (clickedSku) set.add(clickedSku);
                if (selected.length === 0) return clickedSku ? [clickedSku] : [];
                return Array.from(set);
            }

            function openEdit(rowData) {
                if (table) {
                    const row = table.getRows().find(r => r.getData().SKU === rowData.SKU);
                    if (row && !row.isSelected()) row.select();
                }
                bulkEditSkus = resolveBulkEditSkus(rowData);
                const isBulk = bulkEditSkus.length > 1;

                document.getElementById('modalSku').value = rowData.SKU || '';
                document.getElementById('modalSkuLabel').textContent = isBulk
                    ? (bulkEditSkus.length + ' SKUs selected')
                    : (rowData.SKU || '');
                document.getElementById('modalProductLabel').textContent = isBulk
                    ? 'Bulk edit — only the fields you change are saved to every selected SKU'
                    : (rowData.Parent || rowData.title150 || rowData.SKU || '');

                const banner = document.getElementById('bulkEditBanner');
                const listWrap = document.getElementById('bulkSkuListWrap');
                const listEl = document.getElementById('bulkSkuList');
                const titleEl = document.getElementById('editModalTitle');
                const saveLabel = document.getElementById('modalSaveBtnLabel');
                if (isBulk) {
                    banner.style.display = '';
                    document.getElementById('bulkEditCount').textContent = String(bulkEditSkus.length);
                    listWrap.style.display = '';
                    listEl.textContent = bulkEditSkus.join(', ');
                    titleEl.innerHTML = '<i class="fas fa-layer-group me-2"></i>Bulk Edit Technical Specifications';
                    saveLabel.textContent = 'Save and push to ' + bulkEditSkus.length + ' SKUs';
                } else {
                    banner.style.display = 'none';
                    listWrap.style.display = 'none';
                    listEl.textContent = '';
                    titleEl.innerHTML = '<i class="fas fa-edit me-2"></i>Edit Technical Specifications';
                    saveLabel.textContent = 'Save and push';
                }

                renderSpecFields(rowsForEdit(rowData));
                editModal.show();
            }

            function rowsForEdit(rowData) {
                const wanted = new Set((bulkEditSkus && bulkEditSkus.length) ? bulkEditSkus : [rowData.SKU]);
                const rows = (table ? table.getData() : []).filter(function (row) {
                    return wanted.has(String(row.SKU || '').trim());
                });
                const clicked = rows.find(function (row) { return row.SKU === rowData.SKU; }) || rowData;
                const rest = rows.filter(function (row) { return row !== clicked; });
                return [clicked].concat(rest);
            }

            // "Max Power", "MAX POWER :" and "Max  Power" are the same specification.
            function specIdentity(name) {
                const label = String(name || '').trim();
                return specNameKey(label) || label.toLowerCase();
            }

            function valueForSpec(row, name) {
                const auto = autofillValue(row, name);
                if (auto !== null) return auto;
                const want = specIdentity(name);
                const specs = row.description_v2_specifications || [];
                for (let i = 0; i < specs.length; i++) {
                    if (specIdentity(specs[i].key) === want && String(specs[i].value || '').trim() !== '') {
                        return String(specs[i].value || '');
                    }
                }
                return String(row[aspectKey(name)] || '');
            }

            function specFieldNames(rows) {
                const names = [];
                const seen = {};
                function add(name) {
                    const label = String(name || '').trim();
                    const key = specIdentity(label);
                    if (!label || seen[key]) return;
                    seen[key] = true;
                    names.push(label);
                }
                ebayAspectNames.forEach(function (name) {
                    if (rows.some(function (row) { return rowRequiresAspect(row, name); })) add(name);
                });
                rows.forEach(function (row) {
                    (row.description_v2_specifications || []).forEach(function (spec) { add(spec.key); });
                });
                return names;
            }

            function renderSpecFields(rows) {
                const wrap = document.getElementById('specFields');
                const empty = document.getElementById('specFieldsEmpty');
                wrap.innerHTML = '';
                const names = specFieldNames(rows);
                const source = rows[0] || {};
                if (!names.length) {
                    empty.classList.remove('d-none');
                    refreshSpecCounts();
                    return;
                }
                empty.classList.add('d-none');
                wrap.classList.toggle('ts-spec-grid-3', names.length > 14);
                wrap.classList.toggle('ts-spec-grid-2', names.length <= 14);
                names.forEach(function (name) {
                    const group = document.createElement('div');
                    group.className = 'ts-spec-card';
                    const label = document.createElement('label');
                    label.className = 'form-label';
                    label.textContent = name;
                    const alert = document.createElement('i');
                    alert.className = 'fas fa-exclamation-triangle spec-blank-alert ms-1';
                    alert.title = 'Blank';
                    label.appendChild(alert);
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'form-control spec-field';
                    input.dataset.name = name;
                    input.value = valueForSpec(source, name);
                    input.dataset.initial = input.value;
                    input.addEventListener('input', function () { markBlankSpec(group, input); });
                    group.appendChild(label);
                    group.appendChild(input);
                    wrap.appendChild(group);
                    markBlankSpec(group, input);
                });
                refreshSpecCounts();
            }

            function refreshSpecCounts() {
                const inputs = document.querySelectorAll('#specFields .spec-field');
                let blank = 0;
                inputs.forEach(function (input) {
                    if (!String(input.value || '').trim()) blank++;
                });
                const filledEl = document.getElementById('specFilledChip');
                const blankEl = document.getElementById('specBlankChip');
                if (filledEl) filledEl.textContent = (inputs.length - blank) + ' filled';
                if (blankEl) blankEl.textContent = blank + ' blank';
            }

            function markBlankSpec(group, input) {
                const blank = !String(input.value || '').trim();
                const alert = group.querySelector('.spec-blank-alert');
                if (alert) alert.style.display = blank ? '' : 'none';
                group.classList.toggle('is-blank', blank);
                group.classList.toggle('is-filled', !blank);
                refreshSpecCounts();
            }

            // Fields the user actually changed. Blank edits are ignored so a bulk save never wipes values.
            function collectEditedSpecFields() {
                return Array.from(document.querySelectorAll('#specFields .spec-field')).filter(function (input) {
                    return String(input.value || '').trim() !== String(input.dataset.initial || '').trim();
                }).map(function (input) {
                    return { key: input.dataset.name, value: String(input.value || '').trim() };
                }).filter(function (row) { return row.value !== ''; });
            }

            function mergeEditedSpecs(sku, edited) {
                const row = table ? table.getData().find(function (r) { return String(r.SKU || '').trim() === sku; }) : null;
                const merged = ((row && row.description_v2_specifications) || []).map(function (spec) {
                    return { key: spec.key, value: spec.value };
                });
                edited.forEach(function (change) {
                    const want = specIdentity(change.key);
                    const idx = merged.findIndex(function (spec) {
                        return specIdentity(spec.key) === want;
                    });
                    if (idx >= 0) {
                        merged[idx] = { key: merged[idx].key, value: change.value };
                        // Drop the other spellings of the same specification so none keep the old value.
                        for (let i = merged.length - 1; i > idx; i--) {
                            if (specIdentity(merged[i].key) === want) merged.splice(i, 1);
                        }
                    } else {
                        merged.push({ key: change.key, value: change.value });
                    }
                });
                return merged;
            }

            function collectSpecFields() {
                return Array.from(document.querySelectorAll('#specFields .spec-field')).map(function (input) {
                    return { key: input.dataset.name, value: String(input.value || '').trim() };
                }).filter(function (row) { return row.value !== ''; });
            }

            function openView(rowData) {
                const text = specsText(rowData);
                document.getElementById('viewSpecsSubtitle').textContent =
                    (rowData.SKU || '') + ' — ' + (rowData.Parent || rowData.title150 || '');
                document.getElementById('viewSpecsContent').textContent = text || '(empty)';
                viewModal.show();
            }

            function isRealAspect(name) {
                const s = String(name || '').trim();
                if (!s || s.length > 120) return false;
                if (/[{};]|text-decoration|^\s*\./.test(s)) return false;
                return true;
            }

            function tsColumnGroup(field) {
                if (field === 'category_ebay' || String(field).indexOf('ebay_aspect_') === 0) return 'ebay';
                if (field === 'category_amz') return 'amz';
                if (field === 'category_temu') return 'temu';
                return '';
            }

            function loadTsColumnVisibility() {
                try {
                    const parsed = JSON.parse(localStorage.getItem(TS_COL_VIS_KEY) || '{}');
                    return parsed && typeof parsed === 'object' ? parsed : {};
                } catch (e) {
                    return {};
                }
            }

            let columnFocusActive = false;

            function normCat(value) {
                return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
            }

            function focusedSpecFields() {
                const channelName = document.getElementById('channelFilter')?.value || '';
                const category = document.getElementById('categoryFilter')?.value || '';
                const field = channelCategoryField(channelName);
                if (!channelName || !category || !field) return null;
                const keep = {};
                keep[field] = true;
                if (field === 'category_ebay') {
                    const want = normCat(category);
                    allRows.forEach(function (row) {
                        if (normCat(row.category_ebay) !== want) return;
                        const names = Array.isArray(row.ebay_required_only) ? row.ebay_required_only : (row.ebay_required || []);
                        names.forEach(function (name) {
                            if (isRealAspect(name)) keep[aspectKey(name)] = true;
                        });
                        // Imported or saved values belong to the category too, so they stay visible.
                        (row.ebay_required || []).forEach(function (name) {
                            if (!isRealAspect(name)) return;
                            if (String((row.ebay_values || {})[name] || '').trim() !== '') keep[aspectKey(name)] = true;
                        });
                    });
                }
                return keep;
            }

            function prepareColumnVisibility() {
                if (!table) return;
                const keep = focusedSpecFields();
                if (!keep) {
                    columnFocusActive = false;
                    const saved = loadTsColumnVisibility();
                    table.getColumns().forEach(function (col) {
                        const field = col.getDefinition().field;
                        if (!tsColumnGroup(field)) return;
                        if (!Object.prototype.hasOwnProperty.call(saved, field) || saved[field] !== false) col.show();
                        else col.hide();
                    });
                    return;
                }
                columnFocusActive = true;
                table.getColumns().forEach(function (col) {
                    const field = col.getDefinition().field;
                    if (!tsColumnGroup(field)) return;
                    if (keep[field]) col.show();
                    else col.hide();
                });
            }

            function saveTsColumnVisibility() {
                if (!table || columnFocusActive) return;
                const visibility = loadTsColumnVisibility();
                table.getColumns().forEach(function (col) {
                    const field = col.getDefinition().field;
                    if (!tsColumnGroup(field)) return;
                    visibility[field] = col.isVisible();
                });
                try { localStorage.setItem(TS_COL_VIS_KEY, JSON.stringify(visibility)); } catch (e) {}
            }

            function applyTsColumnVisibility() {
                if (!table) return;
                const saved = loadTsColumnVisibility();
                table.getColumns().forEach(function (col) {
                    const field = col.getDefinition().field;
                    if (!tsColumnGroup(field) || !Object.prototype.hasOwnProperty.call(saved, field)) return;
                    if (saved[field] === false) col.hide();
                    else col.show();
                });
            }

            function syncTsHeader(box, selector) {
                const itemCbs = box.querySelectorAll(selector);
                const cb = box.querySelector(':scope > .col-vis-group-title input, :scope > .col-vis-cat-title input');
                if (!cb) return;
                if (!itemCbs.length) {
                    cb.checked = false;
                    cb.indeterminate = false;
                    cb.disabled = true;
                    return;
                }
                cb.disabled = false;
                let checked = 0;
                itemCbs.forEach(function (item) { if (item.checked) checked++; });
                cb.checked = checked === itemCbs.length;
                cb.indeterminate = checked > 0 && checked < itemCbs.length;
            }

            function syncTsGroupHeader(groupEl) {
                groupEl.querySelectorAll('.col-vis-cat').forEach(function (cat) {
                    syncTsHeader(cat, ':scope > .col-vis-group-list .col-vis-field-toggle');
                });
                syncTsHeader(groupEl, '.col-vis-field-toggle');
            }

            function setTsFieldVisible(field, visible) {
                document.querySelectorAll('#ts-column-dropdown .col-vis-field-toggle').forEach(function (cb) {
                    if (cb.value === field) cb.checked = visible;
                });
                const col = table.getColumn(field);
                if (col) visible ? col.show() : col.hide();
            }

            function ebayCategoryColumns() {
                const map = new Map();
                allRows.forEach(function (row) {
                    const cat = String(row.category_ebay || '').trim() || 'No category';
                    if (!map.has(cat)) map.set(cat, []);
                    const list = map.get(cat);
                    (row.ebay_required || []).forEach(function (name) {
                        if (!isRealAspect(name)) return;
                        if (list.indexOf(name) === -1) list.push(name);
                    });
                });
                const seen = {};
                map.forEach(function (names) { names.forEach(function (name) { seen[name] = true; }); });
                const other = ebayAspectNames.filter(function (name) { return isRealAspect(name) && !seen[name]; });
                if (other.length) map.set('Other', (map.get('Other') || []).concat(other.filter(function (name) { return (map.get('Other') || []).indexOf(name) === -1; })));
                const cats = [{ title: 'Category', fields: [{ field: 'category_ebay', title: 'Category eBay' }] }];
                Array.from(map.keys()).sort(function (a, b) { return a.localeCompare(b); }).forEach(function (title) {
                    const fields = map.get(title).filter(isRealAspect).sort(function (a, b) { return a.localeCompare(b); }).map(function (name) {
                        return { field: aspectKey(name), title: name };
                    });
                    if (fields.length) cats.push({ title: title, fields: fields });
                });
                return cats;
            }

            function channelCategories(key) {
                if (key === 'ebay') return ebayCategoryColumns();
                if (key === 'amz') return [{ title: 'Category', fields: [{ field: 'category_amz', title: 'Category Amz' }] }];
                return [{ title: 'Category', fields: [{ field: 'category_temu', title: 'Category Temu' }] }];
            }

            function appendFieldToggle(list, field, titleText, groupEl) {
                const col = table.getColumn(field);
                if (!col) return;
                const li = document.createElement('li');
                li.className = 'col-vis-item';
                const label = document.createElement('label');
                label.title = titleText;
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'col-vis-field-toggle';
                cb.value = field;
                cb.checked = col.isVisible();
                label.appendChild(cb);
                label.appendChild(document.createTextNode(titleText));
                li.appendChild(label);
                list.appendChild(li);
                cb.addEventListener('change', function () {
                    setTsFieldVisible(field, cb.checked);
                    syncTsGroupHeader(groupEl);
                    saveTsColumnVisibility();
                });
            }

            function placeTsColumnPanel() {
                const menu = document.getElementById('ts-column-dropdown');
                const bar = document.querySelector('.ts-master-toolbar');
                if (!menu || !bar) return;
                const card = document.querySelector('.ts-master-card') || bar;
                const rect = card.getBoundingClientRect();
                menu.style.top = Math.max(0, bar.getBoundingClientRect().bottom) + 'px';
                menu.style.left = rect.left + 'px';
                menu.style.width = rect.width + 'px';
                menu.style.right = 'auto';
                menu.style.transform = 'none';
            }

            function buildTsColumnPicker() {
                const menu = document.getElementById('ts-column-dropdown');
                if (!menu || !table) return;
                prepareColumnVisibility();
                menu.innerHTML = '';
                const wrap = document.createElement('div');
                wrap.className = 'col-vis-groups';
                TS_COL_GROUPS.forEach(function (spec) {
                    const group = document.createElement('div');
                    group.className = 'col-vis-group';
                    group.dataset.group = spec.key;
                    const title = document.createElement('label');
                    title.className = 'col-vis-group-title';
                    const groupCb = document.createElement('input');
                    groupCb.type = 'checkbox';
                    groupCb.className = 'col-vis-group-toggle';
                    groupCb.title = 'Select / deselect all in ' + spec.label;
                    const icon = document.createElement('span');
                    icon.className = 'ts-ch-icon ' + spec.icon;
                    icon.innerHTML = spec.mark;
                    title.appendChild(groupCb);
                    title.appendChild(icon);
                    title.appendChild(document.createTextNode(spec.label));
                    const cats = document.createElement('div');
                    cats.className = 'col-vis-cats';
                    group.appendChild(title);
                    group.appendChild(cats);
                    channelCategories(spec.key).forEach(function (catSpec) {
                        const cat = document.createElement('div');
                        cat.className = 'col-vis-cat';
                        const catTitle = document.createElement('label');
                        catTitle.className = 'col-vis-cat-title';
                        const catCb = document.createElement('input');
                        catCb.type = 'checkbox';
                        catCb.title = 'Select / deselect ' + catSpec.title;
                        const catName = document.createElement('span');
                        catName.textContent = catSpec.title;
                        catName.title = catSpec.title;
                        catTitle.appendChild(catCb);
                        catTitle.appendChild(catName);
                        const list = document.createElement('ul');
                        list.className = 'col-vis-group-list';
                        cat.appendChild(catTitle);
                        cat.appendChild(list);
                        catSpec.fields.forEach(function (field) {
                            appendFieldToggle(list, field.field, field.title, group);
                        });
                        if (!list.children.length) {
                            const empty = document.createElement('li');
                            empty.className = 'text-muted small px-2';
                            empty.textContent = 'No columns';
                            list.appendChild(empty);
                        }
                        cats.appendChild(cat);
                        catCb.addEventListener('change', function () {
                            list.querySelectorAll('.col-vis-field-toggle').forEach(function (cb) {
                                setTsFieldVisible(cb.value, catCb.checked);
                            });
                            syncTsGroupHeader(group);
                            saveTsColumnVisibility();
                        });
                    });
                    groupCb.addEventListener('change', function () {
                        group.querySelectorAll('.col-vis-field-toggle').forEach(function (cb) {
                            setTsFieldVisible(cb.value, groupCb.checked);
                        });
                        syncTsGroupHeader(group);
                        saveTsColumnVisibility();
                    });
                    syncTsGroupHeader(group);
                    wrap.appendChild(group);
                });
                menu.appendChild(wrap);
                placeTsColumnPanel();
            }

            function bindTopHScroll() {
                const holder = document.querySelector('#technical-specs-table .tabulator-tableholder');
                const bar = document.getElementById('ts-hscroll');
                const inner = document.getElementById('ts-hscroll-inner');
                if (!holder || !bar || !inner) return;
                const tableEl = holder.querySelector('.tabulator-table');
                inner.style.width = ((tableEl ? tableEl.scrollWidth : holder.scrollWidth) || 0) + 'px';
                if (bar.dataset.bound === '1') return;
                bar.dataset.bound = '1';
                let lock = false;
                bar.addEventListener('scroll', function () {
                    if (lock) return;
                    lock = true;
                    holder.scrollLeft = bar.scrollLeft;
                    lock = false;
                });
                holder.addEventListener('scroll', function () {
                    if (lock) return;
                    lock = true;
                    bar.scrollLeft = holder.scrollLeft;
                    lock = false;
                });
            }

            function autofitColumns() {
                if (!table) return;
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                if (!ctx) return;
                ctx.font = '600 13.2px Arial, sans-serif';
                const data = table.getData();
                table.getColumns().forEach(function (col) {
                    const field = col.getField();
                    if (!field || field === '_action' || field === '_missing' || field === '_edit' || field.indexOf('category_') === 0) return;
                    let max = 52;
                    data.forEach(function (row) {
                        const text = String(field === 'Parent'
                            ? (row.Parent || row.title150 || row.SKU || '')
                            : (row[field] == null ? '' : row[field])).trim();
                        if (!text) return;
                        const width = Math.ceil(ctx.measureText(text).width) + 22;
                        if (width > max) max = width;
                    });
                    let width = Math.min(Math.max(max, 52), 340);
                    if (field === 'Parent' || field === 'SKU') width = Math.max(58, Math.round(width * 0.8));
                    col.setWidth(width);
                });
            }

            function initTable(rows, aspectNames) {
                const nextNames = Array.isArray(aspectNames) ? aspectNames : ebayAspectNames;
                if (table && nextNames.join('\n') !== ebayAspectNames.join('\n')) {
                    table.destroy();
                    table = null;
                }
                ebayAspectNames = nextNames.filter(isRealAspect);
                rows.forEach(stampAspectFields);
                allRows = rows;
                if (table) {
                    table.replaceData(rows);
                    applyStatusFilter();
                    autofitColumns();
                    buildTsColumnPicker();
                    bindTopHScroll();
                    return;
                }

                table = new Tabulator('#technical-specs-table', {
                    data: rows,
                    layout: 'fitData',
                    height: 'calc(100vh - 250px)',
                    pagination: true,
                    paginationSize: 50,
                    paginationSizeSelector: [25, 50, 100, 200, true],
                    placeholder: 'No products found',
                    selectableRows: true,
                    rowFormatter: window.pmParentRowFormatter,
                    columns: [
                        {
                            formatter: 'rowSelection',
                            titleFormatter: 'rowSelection',
                            titleFormatterParams: { rowRange: 'active' },
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            headerSort: false,
                            width: 44,
                            minWidth: 44,
                            resizable: false,
                            frozen: true,
                            cellClick: function (e, cell) {
                                e.stopPropagation();
                                cell.getRow().toggleSelect();
                            },
                        },
                        {
                            title: 'Parent',
                            field: 'Parent',
                            minWidth: 58,
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            headerTooltip: 'Parent',
                            frozen: true,
                            formatter: function (cell) {
                                const d = cell.getRow().getData();
                                return esc(d.Parent || d.title150 || d.SKU || '');
                            },
                        },
                        {
                            title: 'SKU',
                            field: 'SKU',
                            minWidth: 58,
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            headerTooltip: 'SKU',
                            frozen: true,
                            formatter: function (cell) {
                                const sku = String(cell.getValue() || '').trim();
                                if (!sku) return '';
                                return '<span class="ts-sku-cell"><span class="ts-sku-text">' + esc(sku) + '</span>'
                                    + '<button type="button" class="ts-sku-copy" title="Copy SKU"><i class="fas fa-copy"></i></button></span>';
                            },
                            cellClick: function (e, cell) {
                                const btn = e.target.closest && e.target.closest('.ts-sku-copy');
                                if (!btn) return;
                                e.stopPropagation();
                                copyCellText(btn, String(cell.getValue() || '').trim(), 'SKU');
                            },
                        },
                        {
                            title: 'Missing',
                            field: '_missing',
                            width: 70,
                            minWidth: 70,
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            headerTooltip: 'Blank required specifications for this SKU',
                            frozen: true,
                            sorter: function (a, b, aRow, bRow) {
                                return missingSpecs(aRow.getData()).length - missingSpecs(bRow.getData()).length;
                            },
                            formatter: function (cell) {
                                const names = missingSpecs(cell.getRow().getData());
                                if (!names.length) return '<span class="text-muted">0</span>';
                                return '<span class="ts-missing" title="' + esc(names.join(', ')) + '"><i class="fas fa-exclamation-triangle"></i>' + names.length + '</span>';
                            },
                        },
                        {
                            title: 'Edit',
                            field: '_edit',
                            width: 52,
                            minWidth: 52,
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            headerTooltip: 'Edit',
                            headerSort: false,
                            resizable: false,
                            frozen: true,
                            titleFormatter: function () {
                                return '<i class="fas fa-pen ts-col-icon" title="Edit"></i>';
                            },
                            formatter: function () {
                                return '<button type="button" class="btn btn-sm btn-primary ts-edit-btn" title="Edit"><i class="fas fa-edit"></i></button>';
                            },
                            cellClick: function (e, cell) {
                                if (e.target.closest('.ts-edit-btn')) openEdit(cell.getRow().getData());
                            },
                        },
                        categoryColumn('Category eBay', 'category_ebay'),
                        categoryColumn('Category Amz', 'category_amz'),
                        categoryColumn('Category Temu', 'category_temu'),
                        ...ebayAspectColumns(),
                        {
                            title: 'Action',
                            field: '_action',
                            width: 60,
                            minWidth: 60,
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            headerTooltip: 'Action',
                            headerSort: false,
                            formatter: function () {
                                return '<button type="button" class="btn btn-sm btn-info text-white ts-view-btn" title="View"><i class="fas fa-eye"></i></button>';
                            },
                            cellClick: function (e, cell) {
                                if (e.target.closest('.ts-view-btn')) openView(cell.getRow().getData());
                            },
                        },
                    ],
                });

                table.on('tableBuilt', function () {
                    autofitColumns();
                    buildTsColumnPicker();
                    bindTopHScroll();
                });
                table.on('renderComplete', bindTopHScroll);
                table.on('columnResized', bindTopHScroll);
                table.on('columnVisibilityChanged', bindTopHScroll);
                table.on('dataFiltered', function () {
                    updateRowCount(table.getDataCount('active'));
                    updateSelectedCount();
                });
                table.on('rowSelectionChanged', function () {
                    updateSelectedCount();
                });
                updateRowCount(rows.length);
                updateSelectedCount();
                buildTsColumnPicker();
            }

            async function loadData() {
                updateRowCount(0);
                try {
                    const res = await fetch('/technical-specifications-data', { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    if (!res.ok || json.status === 500) {
                        throw new Error(json.message || 'Failed to load data');
                    }
                    fillChannelFilter(json.channels || []);
                    initTable(json.data || [], json.ebay_aspects || []);
                    fillCategoryFilter();
                    applyStatusFilter();
                    if (table) buildTsColumnPicker();
                } catch (e) {
                    toast(e.message || 'Failed to load Technical Specifications data', 'error');
                    initTable([]);
                }
            }

            function exportExcel() {
                if (!table) return;
                const rows = table.getData('active').map(function (r) {
                    const row = {
                        SKU: r.SKU || '',
                        'Category eBay': r.category_ebay || '',
                        'Category Amz': r.category_amz || '',
                        'Category Temu': r.category_temu || '',
                        Parent: r.Parent || '',
                        title150: r.title150 || '',
                        technical_specifications: specsText(r),
                    };
                    ebayAspectNames.forEach(function (name) {
                        row[name] = rowRequiresAspect(r, name) ? (r[aspectKey(name)] || '') : '';
                    });
                    row.description_1500 = r.description_1500 || '';
                    row.description_1000 = r.description_1000 || '';
                    row.description_800 = r.description_800 || '';
                    row.description_600 = r.description_600 || '';
                    return row;
                });
                const ws = XLSX.utils.json_to_sheet(rows);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Technical Specs');
                XLSX.writeFile(wb, 'technical_specifications_export.xlsx');
            }

            async function importExcel(file) {
                const buf = await file.arrayBuffer();
                const wb = XLSX.read(buf, { type: 'array' });
                const sheet = wb.Sheets[wb.SheetNames[0]];
                const rows = XLSX.utils.sheet_to_json(sheet, { defval: '' });
                let ok = 0, fail = 0;
                for (const row of rows) {
                    const sku = String(row.SKU || row.sku || '').trim();
                    if (!sku) continue;
                    const text = String(
                        row.technical_specifications
                        || row.Technical_Specifications
                        || row['Technical Specifications']
                        || row.specifications_text
                        || ''
                    );
                    try {
                        const json = await saveSpecs(sku, text);
                        ok++;
                        applySaveToRow(sku, json, text);
                    } catch (_) {
                        fail++;
                    }
                }
                toast('Import done: ' + ok + ' saved' + (fail ? ', ' + fail + ' failed' : ''), fail ? 'error' : 'success');
                applyStatusFilter();
            }

            let ebaySyncModal = null;
            let ebaySyncAction = null;

            function selectedRows() {
                return table ? table.getSelectedData() : [];
            }

            function fillSkuList(rows) {
                const list = document.getElementById('ebaySyncSkuList');
                list.innerHTML = '';
                const shown = rows.slice(0, 40);
                shown.forEach(function (row) {
                    const li = document.createElement('li');
                    li.textContent = row.SKU || '';
                    list.appendChild(li);
                });
                if (rows.length > shown.length) {
                    const li = document.createElement('li');
                    li.textContent = 'and ' + (rows.length - shown.length) + ' more';
                    list.appendChild(li);
                }
            }

            function hideSyncOutcome() {
                const box = document.getElementById('ebaySyncOutcome');
                box.className = 'd-none';
                box.innerHTML = '';
            }

            function showSyncOutcome(success, title, detail) {
                const box = document.getElementById('ebaySyncOutcome');
                box.className = success ? 'is-success' : 'is-failed';
                const mark = document.createElement('div');
                mark.className = 'ts-sync-mark';
                const icon = document.createElement('i');
                icon.className = success ? 'fas fa-check' : 'fas fa-exclamation-triangle';
                mark.appendChild(icon);
                const heading = document.createElement('div');
                heading.className = 'ts-sync-outcome-title';
                heading.textContent = title;
                box.appendChild(mark);
                box.appendChild(heading);
                if (detail) {
                    const note = document.createElement('div');
                    note.className = 'small text-muted';
                    note.textContent = detail;
                    box.appendChild(note);
                }
            }

            function openEbayPrompt(opts) {
                hideSyncOutcome();
                document.getElementById('ebaySyncTitle').textContent = opts.title;
                document.getElementById('ebaySyncBody').textContent = opts.body;
                document.getElementById('ebaySyncStatus').textContent = '';
                const alertBox = document.getElementById('ebaySyncAlert');
                const alertList = document.getElementById('ebaySyncAlertList');
                alertList.innerHTML = '';
                if (opts.blanks && opts.blanks.length) {
                    alertBox.classList.remove('d-none');
                    document.getElementById('ebaySyncAlertText').textContent = opts.alertText;
                    opts.blanks.forEach(function (blank) {
                        const li = document.createElement('li');
                        li.textContent = blank.sku + ' — ' + blank.missing.join(', ');
                        alertList.appendChild(li);
                    });
                } else {
                    alertBox.classList.add('d-none');
                }
                fillSkuList(opts.rows || []);
                const confirmBtn = document.getElementById('ebaySyncConfirm');
                const cancelBtn = document.getElementById('ebaySyncCancel');
                cancelBtn.textContent = opts.confirmText ? 'Cancel' : 'OK';
                cancelBtn.disabled = false;
                if (!opts.confirmText) {
                    confirmBtn.classList.add('d-none');
                    ebaySyncAction = null;
                } else {
                    confirmBtn.classList.remove('d-none');
                    confirmBtn.disabled = false;
                    confirmBtn.className = 'btn ' + (opts.confirmClass || 'btn-primary');
                    confirmBtn.textContent = opts.confirmText;
                    ebaySyncAction = opts.onConfirm;
                }
                ebaySyncModal.show();
            }

            function rowsBySku(skus) {
                const wanted = new Set(skus);
                const rows = (table ? table.getData() : []).filter(function (row) {
                    return wanted.has(String(row.SKU || '').trim());
                });
                skus.forEach(function (sku) {
                    if (!rows.some(function (row) { return row.SKU === sku; })) rows.push({ SKU: sku });
                });
                return rows;
            }

            function pushSavedSpecs(skus, failedSaves) {
                const rows = rowsBySku(skus);
                const failedNote = failedSaves
                    ? failedSaves + ' SKU' + (failedSaves === 1 ? '' : 's') + ' could not be saved and will not be pushed. '
                    : '';
                openEbayPrompt({
                    title: 'Save and push to eBay 1',
                    body: failedNote + 'Saved. Pushing changed specifications to eBay 1. Blank values are not sent.',
                    rows: rows,
                });
                runEbaySync('push', skus);
            }

            function promptClearSpecs() {
                const rows = selectedRows();
                if (!rows.length) {
                    openEbayPrompt({
                        title: 'Select rows',
                        body: 'Select the rows to clear before continuing. Use the checkboxes in the first column.',
                        rows: [],
                    });
                    return;
                }
                if (rows.length > 500) {
                    openEbayPrompt({
                        title: 'Too many rows',
                        body: 'Select 500 rows or fewer at a time, then clear again.',
                        rows: rows,
                    });
                    return;
                }
                openEbayPrompt({
                    title: 'Clear specifications',
                    body: 'Clear all saved technical specifications for these ' + rows.length + ' row' + (rows.length === 1 ? '' : 's') + '? This only changes this app. eBay is not changed.',
                    rows: rows,
                    confirmText: 'Clear',
                    confirmClass: 'btn-danger',
                    onConfirm: function () { runClearSpecs(rows.map(function (row) { return row.SKU; })); },
                });
            }

            async function runClearSpecs(skus) {
                const confirmBtn = document.getElementById('ebaySyncConfirm');
                const cancelBtn = document.getElementById('ebaySyncCancel');
                const status = document.getElementById('ebaySyncStatus');
                const list = document.getElementById('ebaySyncSkuList');
                confirmBtn.disabled = true;
                cancelBtn.disabled = true;
                hideSyncOutcome();
                list.innerHTML = '';
                let done = 0;
                let failed = 0;
                for (const sku of skus) {
                    status.textContent = 'Clearing ' + (done + failed + 1) + ' of ' + skus.length + '…';
                    try {
                        const json = await saveSpecs(sku, '', []);
                        applySaveToRow(sku, json, '');
                        done++;
                    } catch (e) {
                        failed++;
                        const li = document.createElement('li');
                        li.className = 'ts-sync-row text-danger';
                        li.textContent = sku + ' — ' + (e.message || 'Clear failed');
                        list.appendChild(li);
                    }
                }
                applyStatusFilter();
                updateSelectedCount();
                const summary = 'Cleared ' + done + ' row' + (done === 1 ? '' : 's') + (failed ? ', ' + failed + ' failed' : '') + '.';
                status.textContent = summary;
                document.getElementById('ebaySyncBody').textContent = '';
                showSyncOutcome(failed === 0, failed === 0 ? 'Specifications cleared' : 'Clear finished with errors', summary);
                confirmBtn.classList.add('d-none');
                cancelBtn.disabled = false;
                cancelBtn.textContent = 'Close';
                ebaySyncAction = null;
            }

            function promptEbaySync(mode) {
                const rows = selectedRows();
                const verb = mode === 'pull' ? 'pull from eBay 1' : 'push to eBay 1';
                if (!rows.length) {
                    openEbayPrompt({
                        title: 'Select rows',
                        body: 'Select the rows to ' + verb + ' before continuing. Use the checkboxes in the first column.',
                        rows: [],
                    });
                    return;
                }
                if (rows.length > 100) {
                    openEbayPrompt({
                        title: 'Too many rows',
                        body: 'Select 100 rows or fewer at a time, then ' + verb + ' again.',
                        rows: rows,
                    });
                    return;
                }
                openEbayPrompt({
                    title: mode === 'pull' ? 'Pull from eBay 1' : 'Push to eBay 1',
                    body: mode === 'pull'
                        ? 'Pull eBay 1 specifications for these ' + rows.length + ' rows? Matching columns are filled, and any specification that does not have a column yet is added for that category. A blank cell is one eBay 1 did not have.'
                        : 'Push changed specifications for these ' + rows.length + ' rows to eBay 1? Only values that differ from the live listing are sent. Blank values are never sent.',
                    rows: rows,
                    confirmText: mode === 'pull' ? 'Pull from eBay 1' : 'Push to eBay 1',
                    confirmClass: 'btn-primary',
                    onConfirm: function () { runEbaySync(mode, rows.map(function (row) { return row.SKU; })); },
                });
            }

            function isParentRow(row) {
                return window.isPmParentRowData
                    ? window.isPmParentRowData(row)
                    : String(row.SKU || '').toUpperCase().includes('PARENT');
            }

            // Selected rows, or every SKU row when nothing is selected. Parent rows are never listed on eBay.
            function promptCategorySync() {
                const picked = selectedRows();
                const source = picked.length ? picked : (table ? table.getData() : []);
                const skus = [];
                const seen = new Set();
                source.forEach(function (row) {
                    const sku = String(row.SKU || '').trim();
                    if (!sku || seen.has(sku) || isParentRow(row)) return;
                    seen.add(sku);
                    skus.push(sku);
                });
                if (!skus.length) {
                    openEbayPrompt({ title: 'Sync categories', body: 'There are no SKU rows to sync.', rows: [] });
                    return;
                }
                const scope = picked.length ? 'the ' + skus.length + ' selected SKU' + (skus.length === 1 ? '' : 's') : 'all ' + skus.length + ' SKUs';
                openEbayPrompt({
                    title: 'Sync categories from eBay 1',
                    body: 'Fetch the live eBay 1 category of ' + scope + ' and update it here? Specifications are not changed.'
                        + (picked.length ? '' : ' This can take a few minutes. Keep this window open.'),
                    rows: rowsBySku(skus),
                    confirmText: 'Sync categories',
                    confirmClass: 'btn-success',
                    onConfirm: function () { runCategorySync(skus); },
                });
            }

            async function runCategorySync(skus) {
                const confirmBtn = document.getElementById('ebaySyncConfirm');
                const cancelBtn = document.getElementById('ebaySyncCancel');
                const status = document.getElementById('ebaySyncStatus');
                const list = document.getElementById('ebaySyncSkuList');
                confirmBtn.disabled = true;
                cancelBtn.disabled = true;
                hideSyncOutcome();
                list.innerHTML = '';
                const counts = { changed: 0, unchanged: 0, skipped: 0, failed: 0 };
                let processed = 0;
                try {
                    for (let i = 0; i < skus.length; i += 8) {
                        const chunk = skus.slice(i, i + 8);
                        status.textContent = 'Syncing ' + (processed + 1) + '–' + (processed + chunk.length) + ' of ' + skus.length
                            + ' — ' + counts.changed + ' changed so far…';
                        const res = await fetch('/technical-specifications/sync-ebay-categories', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ skus: chunk }),
                        });
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'eBay request failed');
                        (json.results || []).forEach(function (result) {
                            const kind = counts[result.status] === undefined ? 'failed' : result.status;
                            counts[kind]++;
                            if (kind !== 'changed' && kind !== 'failed') return;
                            const li = document.createElement('li');
                            li.className = 'ts-sync-row ' + (kind === 'changed' ? 'text-success' : 'text-danger');
                            const icon = document.createElement('i');
                            icon.className = kind === 'changed' ? 'fas fa-check' : 'fas fa-exclamation-triangle';
                            const text = document.createElement('span');
                            text.textContent = kind === 'changed'
                                ? (result.sku || '') + ' — ' + (result.previous ? result.previous + ' → ' : 'New: ') + result.category
                                : (result.sku || '') + ' — ' + (result.message || 'Failed');
                            li.appendChild(icon);
                            li.appendChild(text);
                            list.appendChild(li);
                        });
                        processed += chunk.length;
                    }
                    if (counts.changed > 0) {
                        status.textContent = 'Refreshing the table…';
                        await loadData();
                    }
                    const summary = counts.changed + ' changed, ' + counts.unchanged + ' already up to date'
                        + (counts.skipped ? ', ' + counts.skipped + ' not listed on eBay 1' : '')
                        + (counts.failed ? ', ' + counts.failed + ' failed' : '') + '.';
                    status.textContent = summary;
                    document.getElementById('ebaySyncBody').textContent = '';
                    showSyncOutcome(counts.failed === 0, counts.failed === 0 ? 'Categories synced' : 'Sync finished with errors', summary);
                    applyStatusFilter();
                } catch (e) {
                    const message = e.message || 'eBay request failed';
                    status.textContent = message;
                    document.getElementById('ebaySyncBody').textContent = '';
                    showSyncOutcome(false, 'Sync failed', message + (processed ? ' ' + processed + ' of ' + skus.length + ' SKUs were processed before it stopped.' : ''));
                    if (counts.changed > 0) await loadData();
                }
                confirmBtn.classList.add('d-none');
                cancelBtn.disabled = false;
                cancelBtn.textContent = 'Close';
                ebaySyncAction = null;
            }

            // Save MPN = SKU for rows whose category uses MPN, so the push sends the right value.
            async function ensureAutofillSaved(skus) {
                const wanted = new Set(skus);
                const rows = (table ? table.getData() : []).filter(function (row) {
                    return wanted.has(String(row.SKU || '').trim());
                });
                for (const row of rows) {
                    const sku = String(row.SKU || '').trim();
                    const changes = [];
                    (row.ebay_required || []).forEach(function (name) {
                        const value = autofillValue(row, name);
                        if (value === null) return;
                        if (valueForSpecSaved(row, name) !== value) changes.push({ key: name, value: value });
                    });
                    if (!changes.length) continue;
                    try {
                        const json = await saveSpecs(sku, '', mergeEditedSpecs(sku, changes));
                        applySaveToRow(sku, json, json.technical_specifications || '');
                    } catch (_) {}
                }
            }

            function valueForSpecSaved(row, name) {
                const want = String(name || '').trim().toLowerCase();
                const spec = (row.description_v2_specifications || []).find(function (s) {
                    return String(s.key || '').trim().toLowerCase() === want;
                });
                return spec ? String(spec.value || '').trim() : '';
            }

            async function runEbaySync(mode, skus) {
                const confirmBtn = document.getElementById('ebaySyncConfirm');
                const cancelBtn = document.getElementById('ebaySyncCancel');
                const status = document.getElementById('ebaySyncStatus');
                const list = document.getElementById('ebaySyncSkuList');
                confirmBtn.disabled = true;
                cancelBtn.disabled = true;
                hideSyncOutcome();
                list.innerHTML = '';
                const url = mode === 'pull' ? '/technical-specifications/pull-ebay' : '/technical-specifications/push-ebay';
                let done = 0;
                let failed = 0;
                try {
                    if (mode === 'push') {
                        status.textContent = 'Filling Brand and MPN…';
                        await ensureAutofillSaved(skus);
                    }
                    for (let i = 0; i < skus.length; i += 8) {
                        const chunk = skus.slice(i, i + 8);
                        status.textContent = (mode === 'pull' ? 'Pulling ' : 'Pushing ') + (done + 1) + '–' + (done + chunk.length) + ' of ' + skus.length + '…';
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ skus: chunk }),
                        });
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'eBay request failed');
                        (json.results || []).forEach(function (result) {
                            const li = document.createElement('li');
                            li.className = 'ts-sync-row ' + (result.success ? 'text-success' : 'text-danger');
                            const icon = document.createElement('i');
                            icon.className = result.success ? 'fas fa-check' : 'fas fa-exclamation-triangle';
                            const text = document.createElement('span');
                            text.textContent = (result.sku || '') + ' — ' + (result.message || '');
                            li.appendChild(icon);
                            li.appendChild(text);
                            list.appendChild(li);
                            if (!result.success) failed++;
                            if (mode === 'pull' && result.success) {
                                applySaveToRow(result.sku, result, result.technical_specifications || '');
                            }
                        });
                        done += chunk.length;
                    }
                    if (mode === 'pull' && done > failed) {
                        status.textContent = 'Refreshing columns…';
                        await loadData();
                    }
                    const verb = mode === 'pull' ? 'Pulled from eBay 1' : 'Pushed to eBay 1';
                    const summary = 'Finished ' + done + ' row' + (done === 1 ? '' : 's') + (failed ? ', ' + failed + ' failed' : '') + '.';
                    status.textContent = summary;
                    document.getElementById('ebaySyncBody').textContent = '';
                    showSyncOutcome(failed === 0, failed === 0 ? verb : (mode === 'pull' ? 'Pull failed' : 'Push failed'), summary);
                    applyStatusFilter();
                } catch (e) {
                    const message = e.message || 'eBay request failed';
                    status.textContent = message;
                    document.getElementById('ebaySyncBody').textContent = '';
                    showSyncOutcome(false, mode === 'pull' ? 'Pull failed' : 'Push failed', message);
                }
                confirmBtn.classList.add('d-none');
                cancelBtn.disabled = false;
                cancelBtn.textContent = 'Close';
                ebaySyncAction = null;
            }

            function bindCategoryTips() {
                let tip = null;
                function hide() {
                    if (tip) tip.style.display = 'none';
                }
                document.addEventListener('mouseover', function (e) {
                    const dot = e.target.closest && e.target.closest('.ts-cat-dot');
                    if (!dot) return;
                    if (!tip) {
                        tip = document.createElement('div');
                        tip.className = 'ts-cat-tip';
                        document.body.appendChild(tip);
                    }
                    tip.textContent = dot.getAttribute('data-tip') || '';
                    tip.style.display = 'block';
                    const r = dot.getBoundingClientRect();
                    const w = tip.offsetWidth;
                    const h = tip.offsetHeight;
                    let left = r.left + r.width / 2 - w / 2;
                    left = Math.max(8, Math.min(left, window.innerWidth - w - 8));
                    let top = r.top - h - 8;
                    if (top < 8) top = r.bottom + 8;
                    tip.style.left = left + 'px';
                    tip.style.top = top + 'px';
                });
                document.addEventListener('mouseout', function (e) {
                    const dot = e.target.closest && e.target.closest('.ts-cat-dot');
                    if (dot && !dot.contains(e.relatedTarget)) hide();
                });
                document.addEventListener('scroll', hide, true);
            }

            document.addEventListener('DOMContentLoaded', function () {
                bindCategoryTips();
                editModal = new bootstrap.Modal(document.getElementById('editSpecsModal'));
                viewModal = new bootstrap.Modal(document.getElementById('viewSpecsModal'));
                ebaySyncModal = new bootstrap.Modal(document.getElementById('ebaySyncModal'));
                document.getElementById('pullEbayBtn').addEventListener('click', function () { promptEbaySync('pull'); });
                const colMenuParent = document.getElementById('tsColumnVisibility').closest('.dropdown');
                if (colMenuParent) colMenuParent.addEventListener('shown.bs.dropdown', placeTsColumnPanel);
                window.addEventListener('resize', placeTsColumnPanel);
                document.getElementById('pushEbayBtn').addEventListener('click', function () { promptEbaySync('push'); });
                document.getElementById('syncCategoriesBtn').addEventListener('click', promptCategorySync);
                document.getElementById('clearSpecsBtn').addEventListener('click', promptClearSpecs);
                document.getElementById('ebaySyncConfirm').addEventListener('click', function () {
                    if (typeof ebaySyncAction === 'function') ebaySyncAction();
                });

                parentPlayback = window.ParentPlayback.create({
                    getAllData: function () { return allRows; },
                    applyFilter: function (parent) {
                        navParent = parent;
                        applyStatusFilter();
                    },
                });

                document.getElementById('exportBtn').addEventListener('click', exportExcel);
                document.getElementById('importBtn').addEventListener('click', () => document.getElementById('importFile').click());
                document.getElementById('importFile').addEventListener('change', function (e) {
                    const file = e.target.files?.[0];
                    if (file) importExcel(file);
                    e.target.value = '';
                });
                document.getElementById('channelFilter').addEventListener('change', function () {
                    fillCategoryFilter();
                    applyStatusFilter();
                    if (table) buildTsColumnPicker();
                });
                document.getElementById('categoryFilter').addEventListener('change', function () {
                    applyStatusFilter();
                    if (table) buildTsColumnPicker();
                });
                document.getElementById('statusFilter').addEventListener('change', applyStatusFilter);
                document.getElementById('rowTypeFilter').addEventListener('change', applyStatusFilter);
                document.getElementById('maxAlertBtn').addEventListener('click', function () {
                    maxAlertOnly = !maxAlertOnly;
                    applyStatusFilter();
                });
                let searchTimer = null;
                ['quickSearch', 'parentSearch', 'skuSearch', 'anythingSearch'].forEach(function (id) {
                    document.getElementById(id).addEventListener('input', function () {
                        clearTimeout(searchTimer);
                        searchTimer = setTimeout(applyStatusFilter, 150);
                    });
                });

                document.getElementById('modalSaveBtn').addEventListener('click', async function () {
                    const skus = bulkEditSkus.length
                        ? bulkEditSkus.slice()
                        : [document.getElementById('modalSku').value].filter(Boolean);
                    if (!skus.length) {
                        toast('No SKUs to save', 'error');
                        return;
                    }
                    const isBulkSave = skus.length > 1;
                    const specs = isBulkSave ? null : collectSpecFields();
                    const edited = isBulkSave ? collectEditedSpecFields() : [];
                    if (isBulkSave && !edited.length) {
                        toast('Change at least one field to save it to the selected SKUs', 'error');
                        return;
                    }
                    const btn = this;
                    const label = document.getElementById('modalSaveBtnLabel');
                    const previousLabel = label.textContent;
                    btn.disabled = true;
                    label.textContent = 'Saving…';
                    const saved = [];
                    let fail = 0;
                    try {
                        for (const sku of skus) {
                            try {
                                const json = await saveSpecs(sku, '', isBulkSave ? mergeEditedSpecs(sku, edited) : specs);
                                applySaveToRow(sku, json, json.technical_specifications || '');
                                saved.push(sku);
                            } catch (_) {
                                fail++;
                            }
                        }
                        if (!saved.length) {
                            toast('Save failed', 'error');
                            return;
                        }
                        applyStatusFilter();
                        updateSelectedCount();
                        const editEl = document.getElementById('editSpecsModal');
                        const afterHide = function () {
                            editEl.removeEventListener('hidden.bs.modal', afterHide);
                            pushSavedSpecs(saved, fail);
                        };
                        editEl.addEventListener('hidden.bs.modal', afterHide);
                        editModal.hide();
                    } finally {
                        btn.disabled = false;
                        label.textContent = previousLabel;
                    }
                });

                document.getElementById('viewCopyBtn').addEventListener('click', async function () {
                    const text = document.getElementById('viewSpecsContent').textContent || '';
                    try {
                        await navigator.clipboard.writeText(text === '(empty)' ? '' : text);
                        toast('Copied to clipboard', 'success');
                    } catch (_) {
                        toast('Copy failed', 'error');
                    }
                });

                loadData();
            });
        })();
    </script>
@endsection
