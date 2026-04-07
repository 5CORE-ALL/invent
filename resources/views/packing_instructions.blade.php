@extends('layouts.vertical', ['title' => 'Packing Instructions', 'mode' => $mode ?? '', 'demo' => $demo ?? '', 'sidenav' => 'condensed'])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link href="https://unpkg.com/filepond@4.32.7/dist/filepond.min.css" rel="stylesheet">
    <link href="https://unpkg.com/filepond-plugin-image-preview@4.6.12/dist/filepond-plugin-image-preview.min.css" rel="stylesheet">

    <style>
        #packing-table-wrapper {
            height: calc(100vh - 188px);
            min-height: 280px;
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        #packing-tabulator.pi-tabulator-host {
            flex: 1;
            min-height: 0;
            width: 100%;
            border-top: 1px solid #dee2e6;
        }

        #packing-tabulator .tabulator {
            font-size: 11px;
            width: 100% !important;
            max-width: 100%;
            border: 1px solid #d1d5db;
        }

        #packing-tabulator .tabulator-tableholder {
            overflow-x: auto;
        }

        #packing-tabulator .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        #packing-tabulator .tabulator-header {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 55%, #115e59 100%);
            color: #fff;
            font-weight: 600;
            border-bottom: 2px solid #0f766e;
        }

        #packing-tabulator .tabulator-header .tabulator-col {
            border-right: 1px solid rgba(255, 255, 255, 0.22);
            height: 76px !important;
            min-height: 76px !important;
        }

        #packing-tabulator .tabulator-header .tabulator-col .tabulator-col-resize-handle {
            border-right: 2px solid rgba(255, 255, 255, 0.35);
        }

        #packing-tabulator .tabulator-header .tabulator-col .tabulator-col-resize-handle:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        #packing-tabulator .tabulator-header .tabulator-col-content {
            padding: 2px 1px;
            height: 100%;
            box-sizing: border-box;
        }

        #packing-tabulator .tabulator-header .tabulator-col .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 68px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.15;
            color: #f0fdfa;
            padding: 0;
            margin: 0 auto;
            text-shadow: 0 1px 1px rgba(0, 0, 0, 0.15);
        }

        #packing-tabulator .tabulator-header .tabulator-col.pi-tabulator-cb-header .tabulator-col-title,
        #packing-tabulator .tabulator-header .tabulator-col[tabulator-field="_cb"] .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            transform: none !important;
            height: auto !important;
            width: 100%;
            min-height: 0;
        }

        #packing-tabulator .tabulator-header .tabulator-col.pi-tabulator-cb-header .tabulator-col-content,
        #packing-tabulator .tabulator-header .tabulator-col[tabulator-field="_cb"] .tabulator-col-content {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2px !important;
        }

        #packing-tabulator .tabulator-header .tabulator-col.pi-tabulator-cb-header {
            height: 76px !important;
        }

        #packing-tabulator .tabulator-cell.pi-packing-field-col {
            min-width: 0;
            padding-left: 2px;
            padding-right: 2px;
            max-width: 14rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #packing-tabulator .tabulator-cell.pi-packing-instructions-col {
            max-width: none;
            min-width: 6rem;
        }

        #packing-tabulator .tabulator-cell.pi-packing-field-col.pi-packing-cell--has-fulltext {
            cursor: help;
        }

        #pi-field-text-popup {
            position: fixed;
            z-index: 10060;
            display: none;
            min-width: 220px;
            max-width: min(92vw, 440px);
            max-height: min(78vh, 360px);
            padding: 8px 10px;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
            pointer-events: auto;
        }

        #pi-field-text-popup.pi-field-text-popup--visible {
            display: block;
        }

        #pi-field-text-popup .pi-field-text-popup-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            margin-bottom: 6px;
        }

        #pi-field-text-popup textarea.pi-field-text-popup-ta {
            display: block;
            width: 100%;
            min-height: 120px;
            max-height: min(62vh, 300px);
            font-size: 12px;
            line-height: 1.45;
            resize: vertical;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px;
            background: #f8fafc;
            color: #0f172a;
            cursor: text;
        }

        #pi-field-text-popup textarea.pi-field-text-popup-ta:focus {
            outline: 2px solid #94a3b8;
            outline-offset: 1px;
            background: #fff;
        }

        #packing-tabulator .pi-thumb-wrap img,
        #packing-tabulator .pi-thumb-img {
            max-width: 32px;
            max-height: 32px;
            width: auto;
            height: auto;
            object-fit: cover;
        }

        #packing-tabulator .tabulator-row .tabulator-cell {
            padding: 3px 5px;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb;
            border-right: 1px solid #f1f5f9;
        }

        #packing-tabulator .tabulator-row .tabulator-cell:last-child {
            border-right: none;
        }

        #packing-tabulator .cm-status-marble {
            width: 11px;
            height: 11px;
        }

        #packing-tabulator .tabulator-cell.pi-status-col {
            background-color: #f8f9fa !important;
            color: #4a5568;
            font-weight: 500;
            text-align: center;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }

        #packing-tabulator .tabulator-cell.pi-status-col .cm-status-cell-inner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        #packing-tabulator .tabulator-row:hover .tabulator-cell.pi-status-col {
            background-color: #f1f5f9 !important;
        }

        #packing-tabulator .tabulator-row.tabulator-row-even .tabulator-cell.pi-status-col {
            background-color: #fafafa !important;
        }

        #packing-tabulator .tabulator-cell.pi-checkbox-cell {
            width: 36px;
            max-width: 36px;
            text-align: center;
            vertical-align: middle;
            padding-left: 4px;
            padding-right: 4px;
        }

        #packing-tabulator .edit-btn,
        #packing-tabulator .delete-btn {
            padding: 1px 5px;
            border-radius: 3px;
            line-height: 1.2;
        }

        #packing-tabulator .tabulator-row.tabulator-row-even .tabulator-cell {
            background-color: #fafafa;
        }

        #packing-tabulator .tabulator-row:hover .tabulator-cell {
            background-color: #f1f5f9;
        }

        .cm-toolbar-search-strip .form-control-sm,
        .cm-compliance-filters-toolbar .form-control-sm,
        .cm-compliance-filters-toolbar .form-select-sm {
            font-size: 11px;
            padding-top: 0.2rem;
            padding-bottom: 0.2rem;
        }

        .cm-compliance-filters-toolbar .form-label.small {
            font-size: 10px;
            line-height: 1.2;
        }

        #packing-table-wrapper .rainbow-loader {
            flex-shrink: 0;
            padding: 16px;
            background: #fafbfc;
            border-top: 1px solid #dee2e6;
        }

        /* Parent summary rows: light band + bottom rule (group separator like inventory grid) */
        #packing-tabulator .tabulator-row.tabulator-pi-parent-keyword .tabulator-cell {
            background-color: #d1e9ff !important;
            border-bottom: 3px solid #38bdf8 !important;
            padding-top: 8px !important;
            padding-bottom: 8px !important;
            font-weight: 600;
        }

        #packing-tabulator .tabulator-row.tabulator-pi-parent-keyword:hover .tabulator-cell {
            background-color: #bfdbfe !important;
        }

        #packing-tabulator .tabulator-row.tabulator-pi-parent-keyword .tabulator-cell.pi-status-col {
            background-color: #d1e9ff !important;
        }

        #packing-tabulator .tabulator-row.tabulator-pi-parent-keyword:hover .tabulator-cell.pi-status-col {
            background-color: #bfdbfe !important;
        }

        /* Extra separation before first SKU under a parent block */
        #packing-tabulator .tabulator-row.pi-first-sku-after-parent .tabulator-cell {
            border-top: 2px solid #cbd5e1 !important;
        }

        #packing-tabulator .tabulator-cell.pi-parent-col {
            min-width: 0;
            box-sizing: border-box;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap !important;
            text-align: left;
        }

        #packing-tabulator .tabulator-cell.pi-parent-col:hover {
            white-space: normal !important;
            word-break: break-word;
            overflow: visible;
            max-width: min(28rem, 78vw);
            width: max-content;
            min-width: 3.5rem;
            position: relative;
            z-index: 25;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
            background-color: #fff;
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
        }

        .cm-status-filter-menu {
            display: none;
            list-style: none;
            margin: 0;
            padding: 8px;
            background: rgba(30, 34, 42, 0.88);
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
        }

        .cm-status-filter-item:hover,
        .cm-status-filter-item.is-selected {
            background: #2563eb;
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

        .cm-compliance-filters-toolbar .cm-status-filter-wrap--toolbar .cm-status-filter-trigger {
            color: #1e293b;
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 3px 6px;
            border-radius: 5px;
            gap: 4px;
            font-size: 11px;
        }

        .cm-compliance-filters-toolbar .cm-status-filter-wrap--toolbar .cm-status-filter-trigger-label {
            color: #334155;
        }

        #pi-summary-stats {
            padding: 0.5rem 0.65rem !important;
        }

        #pi-summary-stats h6 {
            font-size: 0.8rem;
            margin-bottom: 0.35rem !important;
        }

        #pi-summary-stats .badge {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 0.2rem 0.4rem;
        }

        .rainbow-loader {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .custom-toast {
            z-index: 2000;
            max-width: 400px;
            width: auto;
            min-width: 300px;
            font-size: 16px;
        }

        .pi-thumb-wrap {
            display: inline-block;
            line-height: 0;
            cursor: zoom-in;
        }

        #packing-tabulator .pi-group-parent-link {
            color: #0b5ed7;
            font-weight: 700;
        }

        #packing-tabulator .pi-sku-child-link {
            color: #0d6efd;
            font-weight: 500;
        }

        #piPackingImagesModal .filepond--root {
            font-family: inherit;
            margin-bottom: 0;
        }

        #piPackingImagesModal .filepond--panel-root {
            background: #f8fafc;
            border: 1px dashed #94a3b8;
        }

        .pi-packing-gallery-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }

        .pi-packing-gallery-card .pi-gal-thumb {
            width: 100%;
            height: 96px;
            object-fit: cover;
            cursor: pointer;
            background: #f1f5f9;
        }

        .pi-packing-gallery-card .pi-gal-meta {
            font-size: 10px;
            padding: 6px;
            border-top: 1px solid #f1f5f9;
        }

        .pi-ai-result-box {
            font-size: 12px;
            max-height: 220px;
            overflow-y: auto;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
        }

        .pi-ai-severity-high { color: #b91c1c; font-weight: 700; }
        .pi-ai-severity-medium { color: #b45309; font-weight: 700; }
        .pi-ai-severity-low { color: #1d4ed8; font-weight: 600; }
        .pi-ai-severity-none { color: #15803d; font-weight: 600; }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $__piFields = [
            'packing_box_spec' => 'Box / carton',
            'packing_units_ctn' => 'Units/CTN',
            'packing_fragile' => 'Fragile',
            'packing_seal_method' => 'Seal',
            'packing_instructions' => 'Instructions',
            'packing_sheet_url' => 'Sheet URL',
        ];
        $__piFilterIds = [
            'packing_box_spec' => 'filterPackingBoxSpec',
            'packing_units_ctn' => 'filterPackingUnitsCtn',
            'packing_fragile' => 'filterPackingFragile',
            'packing_seal_method' => 'filterPackingSeal',
            'packing_instructions' => 'filterPackingInstructions',
            'packing_sheet_url' => 'filterPackingSheetUrl',
        ];
    @endphp

    @include('layouts.shared.page-title', [
        'page_title' => 'Packing Instructions',
        'sub_title' => 'Product packing notes per SKU',
    ])

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 11000;"></div>
    <span id="pi-openai-enabled-marker" class="d-none" data-enabled="{{ config('services.openai.key') ? '1' : '0' }}" aria-hidden="true"></span>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2">
                    <h4 class="mb-1 fs-5">Packing Instructions</h4>
                    <p class="text-muted small mb-2">Parent summary rows use a light blue band with a rule under each group (like the inventory grid). <strong>Drag column edges</strong> to resize spacing; <strong>drag headers</strong> to reorder columns. Packing text and links live in product Values JSON.</p>
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-primary" id="addPackingBtn">
                            <i class="fas fa-plus me-1"></i> Add packing data
                        </button>
                        <button type="button" class="btn btn-sm btn-success" id="downloadPackingExcel">
                            <i class="fas fa-file-excel me-1"></i> Download Excel
                        </button>
                    </div>
                    <div id="pi-summary-stats" class="mt-1 p-2 bg-light rounded border border-light">
                        <h6 class="mb-2 text-secondary fw-semibold">Summary (missing field counts)</h6>
                        <div class="d-flex flex-wrap gap-1">
                            <span class="badge bg-primary">Parents <span id="pi-summary-parent">(0)</span></span>
                            <span class="badge bg-success">SKUs <span id="pi-summary-sku">(0)</span></span>
                            @foreach ($__piFields as $fkey => $flabel)
                                <span class="badge bg-secondary">{{ $flabel }} <span id="pi-summary-{{ $fkey }}">(0)</span></span>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="packing-table-wrapper">
                        <div class="cm-toolbar-search-strip px-2 py-1 bg-light border-bottom">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                                <input type="text" id="piCustomSearch" class="form-control form-control-sm" placeholder="Search Parent, SKU, Status, or packing columns...">
                                <button class="btn btn-outline-secondary btn-sm" type="button" id="piClearSearch">Clear</button>
                            </div>
                        </div>
                        <div id="pi-packing-filters-toolbar" class="cm-compliance-filters-toolbar px-2 py-1 bg-white border-bottom">
                            <div class="d-flex flex-wrap align-items-end gap-1 gap-md-2">
                                <div class="flex-grow-1" style="min-width: 9rem; max-width: 14rem;">
                                    <label class="form-label small mb-0 text-secondary" for="piParentSearch">Parent <span id="piParentCount" class="text-danger fw-bold">(0)</span></label>
                                    <input type="text" id="piParentSearch" class="form-control form-control-sm" placeholder="Search Parent" autocomplete="off">
                                </div>
                                <div class="flex-grow-1" style="min-width: 9rem; max-width: 14rem;">
                                    <label class="form-label small mb-0 text-secondary" for="piSkuSearch">SKU <span id="piSkuCount" class="text-danger fw-bold">(0)</span></label>
                                    <input type="text" id="piSkuSearch" class="form-control form-control-sm" placeholder="Search SKU" autocomplete="off">
                                </div>
                                <div style="min-width: 10rem; max-width: 14rem;">
                                    <span class="form-label small mb-0 text-secondary d-block">Status</span>
                                    <div class="cm-status-filter-wrap cm-status-filter-wrap--toolbar mt-0">
                                        <button type="button" class="cm-status-filter-trigger" aria-expanded="false" aria-haspopup="listbox" id="piStatusFilterTrigger">
                                            <span class="cm-status-filter-trigger-label">All</span>
                                            <span style="font-size:9px;opacity:0.75;" aria-hidden="true">▼</span>
                                        </button>
                                        <input type="hidden" id="piFilterPackingStatus" value="all" autocomplete="off">
                                        <div class="cm-status-filter-menu" role="listbox" id="piStatusFilterMenu">
                                            <button type="button" class="cm-status-filter-item" data-value="all" role="option">
                                                <span class="cm-status-filter-check" aria-hidden="true">✓</span>
                                                <span>All</span>
                                            </button>
                                            <button type="button" class="cm-status-filter-item" data-value="missing" role="option">
                                                <span style="width:18px;display:inline-block;"></span>
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
                                </div>
                                @foreach ($__piFields as $fkey => $flabel)
                                    <div style="min-width: 6.5rem; max-width: 9rem;">
                                        <label class="form-label small mb-0 text-secondary" for="{{ $__piFilterIds[$fkey] }}">{{ $flabel }} <span id="{{ $fkey }}MissingCount" class="text-danger fw-bold">(0)</span></label>
                                        <select id="{{ $__piFilterIds[$fkey] }}" class="form-select form-select-sm">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="has">Has data</option>
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div id="packing-tabulator" class="pi-tabulator-host" aria-label="Packing instructions grid"></div>

                        <div id="pi-rainbow-loader" class="rainbow-loader">
                            <div class="wave"></div>
                            <div class="wave"></div>
                            <div class="wave"></div>
                            <div class="wave"></div>
                            <div class="wave"></div>
                            <div class="loading-text">Loading packing instructions…</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="piPackingImagesModal" tabindex="-1" aria-labelledby="piPackingImagesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <div>
                        <h5 class="modal-title fs-6 mb-0" id="piPackingImagesModalLabel">Packing photos &amp; QC</h5>
                        <div class="small text-muted">SKU: <strong id="piPackingImagesSkuLabel"></strong></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1">Drag &amp; drop uploads (FilePond)</label>
                        <input type="file" id="piFilepondInput" name="image" accept="image/*" multiple>
                        <div class="form-text small">Images save to this SKU on <code>product_master.Values.packing_images</code> (public disk).</div>
                    </div>
                    <hr class="my-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <h6 class="mb-0 fs-6">File manager</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="piGalleryRefreshBtn">
                            <i class="fas fa-rotate me-1"></i> Refresh
                        </button>
                    </div>
                    <div id="piPackingGallery" class="row g-2"></div>
                    <p class="text-muted small mb-0 mt-2 d-none" id="piPackingGalleryEmpty">No images yet — upload above.</p>
                    <hr class="my-3">
                    <h6 class="fs-6 mb-2"><i class="fas fa-robot me-1 text-primary"></i> AI defect detection</h6>
                    <p class="small text-muted mb-2">Uses OpenAI vision on the selected packing photo. Configure <code>OPENAI_API_KEY</code> in <code>.env</code>. Optional: <code>OPENAI_PACKING_VISION_MODEL</code> (default <code>gpt-4o-mini</code>).</p>
                    <div id="piAiDisabledNotice" class="alert alert-warning py-2 small d-none mb-2">OpenAI API key is not set — AI scan is disabled.</div>
                    <div id="piAiResultPanel" class="d-none">
                        <div class="small fw-semibold text-secondary mb-1">Latest scan</div>
                        <div id="piAiResultBody" class="pi-ai-result-box"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="piImagePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header border-secondary py-2">
                    <h6 class="modal-title text-white small mb-0" id="piImagePreviewTitle">Preview</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 text-center">
                    <img id="piImagePreviewImg" src="" alt="Packing image preview" class="w-100" style="max-height: 85vh; object-fit: contain;">
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addPackingModal" tabindex="-1" aria-labelledby="addPackingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPackingModalLabel">Add packing data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addPackingForm">
                        <div class="mb-3">
                            <label for="addPackingSku" class="form-label">SKU <span class="text-danger">*</span></label>
                            <select class="form-control" id="addPackingSku" name="sku" required>
                                <option value="">Select SKU</option>
                            </select>
                        </div>
                        <div class="row g-2">
                            @foreach ($__piFields as $fkey => $flabel)
                                @if ($fkey === 'packing_fragile')
                                    <div class="col-md-6">
                                        <label class="form-label" for="add_{{ $fkey }}">{{ $flabel }}</label>
                                        <select class="form-select" id="add_{{ $fkey }}" name="{{ $fkey }}">
                                            <option value="">—</option>
                                            <option value="Y">Y</option>
                                            <option value="N">N</option>
                                        </select>
                                    </div>
                                @else
                                    <div class="col-md-6">
                                        <label class="form-label" for="add_{{ $fkey }}">{{ $flabel }}</label>
                                        @if ($fkey === 'packing_instructions')
                                            <textarea class="form-control" id="add_{{ $fkey }}" name="{{ $fkey }}" rows="3" placeholder="Handling / placement notes"></textarea>
                                        @else
                                            <input type="text" class="form-control" id="add_{{ $fkey }}" name="{{ $fkey }}" placeholder="" autocomplete="off">
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveAddPackingBtn">
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
    <script src="https://unpkg.com/filepond@4.32.7/dist/filepond.min.js"></script>
    <script src="https://unpkg.com/filepond-plugin-image-preview@4.6.12/dist/filepond-plugin-image-preview.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let tableData = [];
            let filteredData = [];
            let packingTable = null;
            let packingSearchSetupDone = false;
            let packingFormMode = 'add';
            let packingEditSku = '';

            const PACKING_FIELD_KEYS = @json(array_keys($__piFields));

            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            window.piImagesModalSku = '';
            window.piFilePondInstance = null;

            function isPiOpenAiEnabled() {
                const m = document.getElementById('pi-openai-enabled-marker');
                return m && m.getAttribute('data-enabled') === '1';
            }

            function packingImageCount(item) {
                if (!item || piRowHasParentKeyword(item)) return 0;
                const v = item.packing_images;
                return Array.isArray(v) ? v.length : 0;
            }

            document.getElementById('pi-rainbow-loader').style.display = 'block';

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

            function escapeHtml(text) {
                if (text == null) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function resolveProductMasterStatus(item) {
                if (!item) return '';
                let v = item.status;
                if (v === undefined || v === null || v === '') {
                    v = item.Status;
                }
                return v != null ? String(v).trim() : '';
            }

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

            function getStatusMarbleModifier(raw) {
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

            function getPackingStatusCellHtml(item) {
                const raw = resolveProductMasterStatus(item);
                const trimmed = String(raw || '').trim();
                if (!trimmed) {
                    return '<span class="cm-status-cell-inner"><span class="cm-status-marble cm-status-marble--muted" title="No status"></span></span>';
                }
                const mod = getStatusMarbleModifier(trimmed);
                const label = formatProductMasterStatusLabel(trimmed);
                const titleAttr = escapeHtml(label === '—' ? trimmed : label);
                return '<span class="cm-status-cell-inner"><span class="cm-status-marble cm-status-marble--' + mod + '" title="' + titleAttr + '"></span></span>';
            }

            function isMissing(value) {
                return value === null || value === undefined || value === '' || (typeof value === 'string' && value.trim() === '');
            }

            function packingFieldRaw(item, key) {
                const v = item[key];
                return v != null ? String(v).trim() : '';
            }

            function isMissingPackingField(item, key) {
                return isMissing(packingFieldRaw(item, key));
            }

            function packingInstructionsHaystack(item) {
                return PACKING_FIELD_KEYS.map(k => packingFieldRaw(item, k).toLowerCase()).join(' ');
            }

            function piRowHasParentKeyword(item) {
                const sku = String(item.SKU || '').toUpperCase();
                const par = String(item.Parent || '').toUpperCase();
                return sku.includes('PARENT') || par.includes('PARENT');
            }

            function truncateCell(s, maxLen) {
                const t = String(s || '').trim();
                if (!t) return '-';
                if (t.length <= maxLen) return escapeHtml(t);
                return escapeHtml(t.slice(0, maxLen)) + '…';
            }

            function formatPackingCellHtml(item, key) {
                if (piRowHasParentKeyword(item)) {
                    return '<span class="text-muted user-select-none">—</span>';
                }
                const raw = packingFieldRaw(item, key);
                if (!raw) {
                    return '-';
                }
                const label = @json($__piFields)[key] || key;
                let inner;
                if (key === 'packing_sheet_url' && /^https?:\/\//i.test(raw)) {
                    const u = escapeHtml(raw);
                    inner = '<a href="' + u + '" target="_blank" rel="noopener" class="small pi-packing-url-link"><i class="fas fa-link"></i></a>';
                } else if (key === 'packing_instructions') {
                    inner = truncateCell(raw, 48);
                } else {
                    inner = truncateCell(raw, 64);
                }
                return '<span class="pi-packing-cell-preview" data-pi-field="' + escapeAttr(key) + '" data-pi-field-label="' + escapeAttr(label) + '" title="Hover to show full text">' + inner + '</span>';
            }

            function escapeAttr(text) {
                if (text == null) return '';
                return String(text)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;')
                    .replace(/</g, '&lt;');
            }

            let piFieldPopupHideTimer = null;
            let piFieldPopupBound = false;

            function hidePiFieldTextPopup() {
                const el = document.getElementById('pi-field-text-popup');
                if (el) {
                    el.classList.remove('pi-field-text-popup--visible');
                }
            }

            function cancelPiFieldPopupHide() {
                if (piFieldPopupHideTimer) {
                    clearTimeout(piFieldPopupHideTimer);
                    piFieldPopupHideTimer = null;
                }
            }

            function scheduleHidePiFieldTextPopup() {
                cancelPiFieldPopupHide();
                piFieldPopupHideTimer = setTimeout(function() {
                    hidePiFieldTextPopup();
                    piFieldPopupHideTimer = null;
                }, 140);
            }

            function getPiFieldTextPopupEl() {
                let el = document.getElementById('pi-field-text-popup');
                if (!el) {
                    el = document.createElement('div');
                    el.id = 'pi-field-text-popup';
                    el.setAttribute('role', 'tooltip');
                    el.innerHTML = '<div class="pi-field-text-popup-label"></div>' +
                        '<textarea class="pi-field-text-popup-ta" readonly rows="8" spellcheck="false" aria-label="Full field text"></textarea>';
                    document.body.appendChild(el);
                    el.addEventListener('mouseenter', cancelPiFieldPopupHide);
                    el.addEventListener('mouseleave', scheduleHidePiFieldTextPopup);
                }
                return el;
            }

            function positionPiFieldTextPopup(clientX, clientY) {
                const el = getPiFieldTextPopupEl();
                requestAnimationFrame(function() {
                    const w = el.offsetWidth || 280;
                    const h = el.offsetHeight || 180;
                    let left = clientX + 14;
                    let top = clientY + 14;
                    const pad = 10;
                    if (left + w > window.innerWidth - pad) {
                        left = Math.max(pad, window.innerWidth - w - pad);
                    }
                    if (top + h > window.innerHeight - pad) {
                        top = Math.max(pad, window.innerHeight - h - pad);
                    }
                    el.style.left = left + 'px';
                    el.style.top = top + 'px';
                });
            }

            function showPiFieldTextPopup(label, fullText, clientX, clientY) {
                const wrap = getPiFieldTextPopupEl();
                const lab = wrap.querySelector('.pi-field-text-popup-label');
                const ta = wrap.querySelector('textarea');
                if (lab) lab.textContent = label || 'Full text';
                if (ta) ta.value = fullText;
                wrap.classList.add('pi-field-text-popup--visible');
                positionPiFieldTextPopup(clientX, clientY);
            }

            function getRowDataFromPackingCellEl(cellEl) {
                if (!packingTable || !cellEl) return null;
                const rowEl = cellEl.closest('.tabulator-row');
                if (!rowEl) return null;
                if (typeof packingTable.getRowFromElement === 'function') {
                    try {
                        const row = packingTable.getRowFromElement(rowEl);
                        if (row) return row.getData();
                    } catch (e) { /* ignore */ }
                }
                const rows = packingTable.getRows();
                for (let i = 0; i < rows.length; i++) {
                    if (rows[i].getElement() === rowEl) {
                        return rows[i].getData();
                    }
                }
                return null;
            }

            function setupPackingFieldHoverPopup() {
                if (piFieldPopupBound) return;
                const host = document.getElementById('packing-tabulator');
                if (!host) return;
                piFieldPopupBound = true;

                host.addEventListener('mouseover', function(e) {
                    const prev = e.target.closest('.pi-packing-cell-preview');
                    if (!prev || !host.contains(prev)) return;
                    const cell = prev.closest('.tabulator-cell.pi-packing-field-col');
                    if (!cell) return;
                    const field = prev.getAttribute('data-pi-field');
                    if (!field || PACKING_FIELD_KEYS.indexOf(field) === -1) return;
                    const data = getRowDataFromPackingCellEl(cell);
                    if (!data || piRowHasParentKeyword(data)) return;
                    const full = packingFieldRaw(data, field);
                    if (!full) return;
                    cancelPiFieldPopupHide();
                    const label = prev.getAttribute('data-pi-field-label') || field;
                    showPiFieldTextPopup(label, full, e.clientX, e.clientY);
                });

                host.addEventListener('mousemove', function(e) {
                    const wrap = document.getElementById('pi-field-text-popup');
                    if (!wrap || !wrap.classList.contains('pi-field-text-popup--visible')) return;
                    const prev = e.target.closest('.pi-packing-cell-preview');
                    if (prev && host.contains(prev)) {
                        positionPiFieldTextPopup(e.clientX, e.clientY);
                    }
                });

                host.addEventListener('mouseout', function(e) {
                    const prev = e.target.closest('.pi-packing-cell-preview');
                    if (!prev || !host.contains(prev)) return;
                    const rt = e.relatedTarget;
                    const popupEl = document.getElementById('pi-field-text-popup');
                    if (rt && (prev.contains(rt) || (popupEl && popupEl.contains(rt)))) return;
                    scheduleHidePiFieldTextPopup();
                });
            }

            function getPackingTabulatorColumnDefinitions() {
                const cols = [
                    {
                        title: 'Image',
                        field: 'image_path',
                        headerSort: false,
                        width: 48,
                        widthShrink: 1,
                        hozAlign: 'center',
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (!v) return '-';
                            return '<span class="pi-thumb-wrap"><img class="pi-thumb-img" src="' + escapeHtml(String(v)) + '" alt=""></span>';
                        }
                    },
                    {
                        title: 'Parent',
                        field: 'Parent',
                        headerSort: false,
                        cssClass: 'pi-parent-col',
                        minWidth: 56,
                        widthGrow: 2,
                        formatter: function(cell) {
                            const item = cell.getRow().getData();
                            const raw = item.Parent != null && item.Parent !== '' ? String(item.Parent) : '';
                            if (!raw) return '-';
                            const esc = escapeHtml(raw);
                            if (piRowHasParentKeyword(item)) {
                                return '<span class="pi-group-parent-link" title="' + esc + '">' + esc + '</span>';
                            }
                            return '<span class="pi-sku-child-link" title="' + esc + '">' + esc + '</span>';
                        }
                    },
                    {
                        title: 'SKU',
                        field: 'SKU',
                        headerSort: false,
                        minWidth: 56,
                        widthGrow: 2,
                        formatter: function(cell) {
                            const item = cell.getRow().getData();
                            const v = item.SKU != null && String(item.SKU) !== '' ? String(item.SKU) : '';
                            if (!v) return '-';
                            const esc = escapeHtml(v);
                            if (piRowHasParentKeyword(item)) {
                                return '<span class="pi-group-parent-link" title="' + esc + '">' + esc + '</span>';
                            }
                            return '<span class="pi-sku-child-link" title="' + esc + '">' + esc + '</span>';
                        }
                    },
                    {
                        title: 'STATUS',
                        field: 'status',
                        headerSort: false,
                        cssClass: 'pi-status-col',
                        hozAlign: 'center',
                        minWidth: 56,
                        widthGrow: 1,
                        formatter: function(cell) {
                            return getPackingStatusCellHtml(cell.getRow().getData());
                        }
                    },
                    {
                        title: 'INV',
                        field: 'shopify_inv',
                        headerSort: false,
                        hozAlign: 'center',
                        width: 48,
                        widthShrink: 1,
                        formatter: function(cell) {
                            const item = cell.getRow().getData();
                            const v = item.shopify_inv;
                            if (v === 0 || v === '0') return '0';
                            if (v === null || v === undefined || v === '') return '-';
                            return escapeHtml(String(v));
                        }
                    },
                    {
                        title: 'Photos',
                        field: 'packing_images',
                        headerSort: false,
                        hozAlign: 'center',
                        width: 52,
                        widthShrink: 1,
                        formatter: function(cell) {
                            const item = cell.getRow().getData();
                            if (piRowHasParentKeyword(item)) {
                                return '<span class="text-muted">—</span>';
                            }
                            const n = packingImageCount(item);
                            return n
                                ? '<span class="badge bg-info text-dark">' + n + '</span>'
                                : '<span class="text-muted">0</span>';
                        }
                    }
                ];

                PACKING_FIELD_KEYS.forEach(function(fk) {
                    const label = @json($__piFields)[fk] || fk;
                    const isInstructions = fk === 'packing_instructions';
                    cols.push({
                        title: label,
                        field: fk,
                        headerSort: false,
                        hozAlign: 'left',
                        minWidth: isInstructions ? 100 : 52,
                        widthGrow: isInstructions ? 3 : 1,
                        cssClass: 'pi-packing-field-col' + (isInstructions ? ' pi-packing-instructions-col' : ''),
                        formatter: function(cell) {
                            return formatPackingCellHtml(cell.getRow().getData(), fk);
                        }
                    });
                });

                cols.push({
                    title: 'Action',
                    field: '_actions',
                    headerSort: false,
                    hozAlign: 'center',
                    width: 118,
                    widthShrink: 1,
                    formatter: function(cell) {
                        const item = cell.getRow().getData();
                        if (piRowHasParentKeyword(item)) {
                            return '<span class="text-muted">—</span>';
                        }
                        const sku = escapeHtml(String(item.SKU ?? ''));
                        return '<div class="d-inline-flex flex-wrap justify-content-center gap-1">' +
                            '<button type="button" class="btn btn-sm btn-outline-info pi-photos-btn" data-sku="' + sku + '" title="Photos, upload &amp; AI QC">' +
                            '<i class="fas fa-camera"></i></button>' +
                            '<button type="button" class="btn btn-sm btn-outline-warning edit-btn" data-sku="' + sku + '">' +
                            '<i class="bi bi-pencil-square"></i></button>' +
                            '<button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-sku="' + sku + '">' +
                            '<i class="bi bi-archive"></i></button></div>';
                    }
                });

                return cols;
            }

            function applyPackingGroupSpacingRows() {
                if (!packingTable) return;
                packingTable.getRows().forEach(function(row) {
                    const el = row.getElement();
                    if (el) el.classList.remove('pi-first-sku-after-parent');
                });
                const rows = packingTable.getRows();
                let prevWasParent = false;
                for (let i = 0; i < rows.length; i++) {
                    const row = rows[i];
                    const el = row.getElement();
                    if (!el) continue;
                    const isParent = piRowHasParentKeyword(row.getData());
                    if (!isParent && prevWasParent) {
                        el.classList.add('pi-first-sku-after-parent');
                    }
                    prevWasParent = isParent;
                }
            }

            function renderTable(data) {
                const d = Array.isArray(data) ? data : [];
                if (typeof Tabulator === 'undefined') {
                    console.error('Tabulator is not loaded');
                    return;
                }
                if (!packingTable) {
                    packingTable = new Tabulator('#packing-tabulator', {
                        data: d,
                        layout: 'fitColumns',
                        layoutColumnsOnNewData: false,
                        height: '100%',
                        placeholder: 'No product rows found',
                        movableColumns: true,
                        resizableColumns: true,
                        columnDefaults: { headerSort: false },
                        columns: getPackingTabulatorColumnDefinitions(),
                        rowFormatter: function(row) {
                            const el = row.getElement();
                            if (piRowHasParentKeyword(row.getData())) {
                                el.classList.add('tabulator-pi-parent-keyword');
                            } else {
                                el.classList.remove('tabulator-pi-parent-keyword');
                            }
                        },
                        dataLoaded: function() {
                            applyPackingGroupSpacingRows();
                        },
                        tableBuilt: function() {
                            setupPackingFieldHoverPopup();
                            applyPackingGroupSpacingRows();
                            const h = document.getElementById('packing-tabulator');
                            const tableScroll = h && h.querySelector('.tabulator-tableholder');
                            if (tableScroll && !tableScroll.dataset.piHoverScrollBound) {
                                tableScroll.dataset.piHoverScrollBound = '1';
                                tableScroll.addEventListener('scroll', hidePiFieldTextPopup, { passive: true });
                            }
                        }
                    });
                } else {
                    packingTable.replaceData(d).then(function() {
                        applyPackingGroupSpacingRows();
                    });
                }
            }

            function loadData() {
                const cacheParam = '?ts=' + new Date().getTime();
                fetch('/packing-instructions-master-data-view' + cacheParam, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                })
                    .then(r => {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(response => {
                        if (response && response.data && Array.isArray(response.data)) {
                            tableData = response.data;
                            filteredData = [...tableData];
                            renderTable(filteredData);
                            updateCounts();
                        }
                        document.getElementById('pi-rainbow-loader').style.display = 'none';
                    })
                    .catch(err => {
                        console.error(err);
                        document.getElementById('pi-rainbow-loader').style.display = 'none';
                    });
            }

            function updateCounts() {
                const parentSet = new Set();
                let skuCount = 0;
                const missingByKey = {};
                PACKING_FIELD_KEYS.forEach(k => { missingByKey[k] = 0; });

                tableData.forEach(item => {
                    if (item.Parent) parentSet.add(item.Parent);
                    if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT')) {
                        skuCount++;
                    }
                    PACKING_FIELD_KEYS.forEach(k => {
                        if (isMissingPackingField(item, k)) missingByKey[k]++;
                    });
                });

                const setText = (id, n) => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = '(' + n + ')';
                };
                setText('piParentCount', parentSet.size);
                setText('piSkuCount', skuCount);
                setText('pi-summary-parent', parentSet.size);
                setText('pi-summary-sku', skuCount);
                PACKING_FIELD_KEYS.forEach(k => {
                    setText(k + 'MissingCount', missingByKey[k]);
                    const sumEl = document.getElementById('pi-summary-' + k);
                    if (sumEl) sumEl.textContent = '(' + missingByKey[k] + ')';
                });
            }

            function applyFilters() {
                filteredData = tableData.filter(item => {
                    const parentSearch = document.getElementById('piParentSearch').value.toLowerCase();
                    if (parentSearch && !(String(item.Parent || '').toLowerCase().includes(parentSearch))) {
                        return false;
                    }
                    const skuSearch = document.getElementById('piSkuSearch').value.toLowerCase();
                    if (skuSearch && !(String(item.SKU || '').toLowerCase().includes(skuSearch))) {
                        return false;
                    }
                    const customSearch = document.getElementById('piCustomSearch').value.toLowerCase();
                    if (customSearch) {
                        const parent = (item.Parent || '').toLowerCase();
                        const sku = (item.SKU || '').toLowerCase();
                        const st = resolveProductMasterStatus(item).toLowerCase();
                        const stLabel = formatProductMasterStatusLabel(resolveProductMasterStatus(item)).toLowerCase();
                        const pack = packingInstructionsHaystack(item);
                        if (!parent.includes(customSearch) && !sku.includes(customSearch) &&
                            !st.includes(customSearch) && !stLabel.includes(customSearch) && !pack.includes(customSearch)) {
                            return false;
                        }
                    }

                    const filterStatus = document.getElementById('piFilterPackingStatus').value;
                    if (filterStatus === 'missing') {
                        if (!isMissing(resolveProductMasterStatus(item))) return false;
                    } else if (filterStatus !== 'all') {
                        const st = resolveProductMasterStatus(item);
                        if (!st || String(st).toLowerCase() !== String(filterStatus).toLowerCase()) return false;
                    }

                    const filterMap = {
                        packing_box_spec: 'filterPackingBoxSpec',
                        packing_units_ctn: 'filterPackingUnitsCtn',
                        packing_fragile: 'filterPackingFragile',
                        packing_seal_method: 'filterPackingSeal',
                        packing_instructions: 'filterPackingInstructions',
                        packing_sheet_url: 'filterPackingSheetUrl'
                    };
                    for (const k of PACKING_FIELD_KEYS) {
                        const sel = document.getElementById(filterMap[k]);
                        if (!sel) continue;
                        const v = sel.value;
                        if (v === 'missing' && !isMissingPackingField(item, k)) return false;
                        if (v === 'has' && isMissingPackingField(item, k)) return false;
                    }
                    return true;
                });
                renderTable(filteredData);
            }

            function setupSearch() {
                if (packingSearchSetupDone) return;
                const parentSearch = document.getElementById('piParentSearch');
                const skuSearch = document.getElementById('piSkuSearch');
                const customSearch = document.getElementById('piCustomSearch');
                const clearBtn = document.getElementById('piClearSearch');
                if (!parentSearch || !skuSearch || !customSearch || !clearBtn) return;
                packingSearchSetupDone = true;

                ['piParentSearch', 'piSkuSearch', 'piCustomSearch'].forEach(id => {
                    document.getElementById(id).addEventListener('input', applyFilters);
                });
                clearBtn.addEventListener('click', function() {
                    customSearch.value = '';
                    parentSearch.value = '';
                    skuSearch.value = '';
                    document.getElementById('piFilterPackingStatus').value = 'all';
                    document.querySelectorAll('#pi-packing-filters-toolbar .cm-status-filter-wrap.is-open').forEach(x => {
                        x.classList.remove('is-open');
                        const t = x.querySelector('.cm-status-filter-trigger');
                        if (t) t.setAttribute('aria-expanded', 'false');
                    });
                    refreshPiStatusFilterUI();
                    ['filterPackingBoxSpec', 'filterPackingUnitsCtn', 'filterPackingFragile', 'filterPackingSeal', 'filterPackingInstructions', 'filterPackingSheetUrl'].forEach(fid => {
                        const el = document.getElementById(fid);
                        if (el) el.value = 'all';
                    });
                    applyFilters();
                });

                ['filterPackingBoxSpec', 'filterPackingUnitsCtn', 'filterPackingFragile', 'filterPackingSeal', 'filterPackingInstructions', 'filterPackingSheetUrl'].forEach(fid => {
                    const el = document.getElementById(fid);
                    if (el) el.addEventListener('change', applyFilters);
                });
            }

            function piStatusFilterOptionLabels() {
                return { all: 'All', missing: 'Missing', active: 'Active', inactive: 'Inactive', DC: 'DC', upcoming: 'Upcoming', '2BDC': '2BDC' };
            }

            function refreshPiStatusFilterUI() {
                const hidden = document.getElementById('piFilterPackingStatus');
                const wrap = document.querySelector('#pi-packing-filters-toolbar .cm-status-filter-wrap');
                if (!hidden || !wrap) return;
                const trigger = wrap.querySelector('.cm-status-filter-trigger');
                const labelEl = trigger && trigger.querySelector('.cm-status-filter-trigger-label');
                const v = hidden.value || 'all';
                const map = piStatusFilterOptionLabels();
                if (labelEl) {
                    labelEl.textContent = Object.prototype.hasOwnProperty.call(map, v) ? map[v] : v;
                }
                wrap.querySelectorAll('.cm-status-filter-item').forEach(btn => {
                    btn.classList.toggle('is-selected', btn.getAttribute('data-value') === v);
                });
            }

            function positionPiStatusFilterMenu(wrap) {
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

            let piStatusFilterDocClickBound = false;
            function setupPiStatusFilter() {
                if (piStatusFilterDocClickBound) return;
                if (!document.getElementById('piFilterPackingStatus')) return;
                piStatusFilterDocClickBound = true;
                document.addEventListener('click', function(e) {
                    const wrap = e.target.closest('.cm-status-filter-wrap');
                    const toolbar = document.getElementById('pi-packing-filters-toolbar');
                    const item = e.target.closest('.cm-status-filter-item');
                    const trigger = e.target.closest('.cm-status-filter-trigger');
                    if (item && wrap && toolbar && toolbar.contains(wrap)) {
                        e.preventDefault();
                        e.stopPropagation();
                        const val = item.getAttribute('data-value');
                        const hidden = document.getElementById('piFilterPackingStatus');
                        if (!hidden) return;
                        hidden.value = val;
                        wrap.classList.remove('is-open');
                        const trg = wrap.querySelector('.cm-status-filter-trigger');
                        if (trg) trg.setAttribute('aria-expanded', 'false');
                        refreshPiStatusFilterUI();
                        applyFilters();
                        return;
                    }
                    if (trigger && wrap && toolbar && toolbar.contains(wrap)) {
                        e.preventDefault();
                        e.stopPropagation();
                        const wasOpen = wrap.classList.contains('is-open');
                        document.querySelectorAll('#pi-packing-filters-toolbar .cm-status-filter-wrap.is-open').forEach(x => {
                            x.classList.remove('is-open');
                            const t = x.querySelector('.cm-status-filter-trigger');
                            if (t) t.setAttribute('aria-expanded', 'false');
                        });
                        if (!wasOpen) {
                            wrap.classList.add('is-open');
                            trigger.setAttribute('aria-expanded', 'true');
                            positionPiStatusFilterMenu(wrap);
                        }
                        return;
                    }
                    if (!wrap) {
                        document.querySelectorAll('#pi-packing-filters-toolbar .cm-status-filter-wrap.is-open').forEach(x => {
                            x.classList.remove('is-open');
                            const t = x.querySelector('.cm-status-filter-trigger');
                            if (t) t.setAttribute('aria-expanded', 'false');
                        });
                    }
                });
            }

            function showToast(type, message) {
                document.querySelectorAll('.custom-toast').forEach(t => t.remove());
                const toast = document.createElement('div');
                const toastContainer = document.querySelector('.toast-container');
                const useContainer = !!toastContainer;
                toast.className = useContainer
                    ? `custom-toast toast align-items-center text-bg-${type} border-0 show mb-2`
                    : `custom-toast toast align-items-center text-bg-${type} border-0 show position-fixed top-0 end-0 m-4`;
                if (!useContainer) toast.style.zIndex = '2000';
                toast.setAttribute('role', 'alert');
                toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + escapeHtml(message) + '</div>' +
                    '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
                (toastContainer || document.body).appendChild(toast);
                setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 500); }, 3000);
            }

            function findPackingRowBySku(sku) {
                const s = String(sku || '');
                let row = tableData.find(i => String(i.SKU) === s);
                if (row) return row;
                return filteredData.find(i => String(i.SKU) === s);
            }

            function setPackingFormFromItem(item) {
                PACKING_FIELD_KEYS.forEach(k => {
                    const el = document.getElementById('add_' + k);
                    if (!el) return;
                    const v = packingFieldRaw(item, k);
                    el.value = v;
                });
            }

            function collectPackingPayload(sku) {
                const o = { sku: String(sku || '').trim() };
                PACKING_FIELD_KEYS.forEach(k => {
                    const el = document.getElementById('add_' + k);
                    o[k] = el ? String(el.value || '').trim() : '';
                });
                return o;
            }

            async function loadSkusIntoDropdown() {
                const response = await fetch('/general-specific-master/skus', {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });
                const data = await response.json();
                const skuSelect = document.getElementById('addPackingSku');
                if (!data.success || !data.data || !skuSelect) return;
                if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                    $(skuSelect).select2('destroy');
                }
                skuSelect.innerHTML = '<option value="">Select SKU</option>';
                data.data.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.sku;
                    opt.textContent = item.sku;
                    skuSelect.appendChild(opt);
                });
                $(skuSelect).select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Select SKU',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#addPackingModal')
                });
            }

            async function openPackingModal(mode, editSku) {
                const modalEl = document.getElementById('addPackingModal');
                const title = document.getElementById('addPackingModalLabel');
                const skuSelect = document.getElementById('addPackingSku');
                document.getElementById('addPackingForm').reset();
                PACKING_FIELD_KEYS.forEach(k => {
                    const el = document.getElementById('add_' + k);
                    if (el) el.value = '';
                });

                if (mode === 'edit') {
                    const skuStr = String(editSku || '').trim();
                    if (!skuStr || skuStr.toUpperCase().includes('PARENT')) {
                        showToast('warning', 'Invalid SKU for edit.');
                        return;
                    }
                    const item = findPackingRowBySku(skuStr);
                    if (!item) {
                        showToast('warning', 'Row not found. Refresh the page.');
                        return;
                    }
                    packingFormMode = 'edit';
                    packingEditSku = skuStr;
                    title.textContent = 'Edit packing data';
                } else {
                    packingFormMode = 'add';
                    packingEditSku = '';
                    title.textContent = 'Add packing data';
                }

                if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                    $(skuSelect).select2('destroy');
                }
                $(skuSelect).prop('disabled', false);
                await loadSkusIntoDropdown();

                if (mode === 'edit') {
                    const item = findPackingRowBySku(packingEditSku);
                    $(skuSelect).val(packingEditSku).trigger('change');
                    $(skuSelect).prop('disabled', true);
                    setPackingFormFromItem(item);
                }

                const saveBtn = document.getElementById('saveAddPackingBtn');
                const newSave = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSave, saveBtn);
                newSave.addEventListener('click', savePacking);

                modalEl.addEventListener('hidden.bs.modal', function cleanup() {
                    $(skuSelect).prop('disabled', false);
                    if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                        $(skuSelect).select2('destroy');
                    }
                    packingFormMode = 'add';
                    packingEditSku = '';
                }, { once: true });

                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }

            async function savePacking() {
                const saveBtn = document.getElementById('saveAddPackingBtn');
                const skuSelect = document.getElementById('addPackingSku');
                let sku = '';
                if (packingFormMode === 'edit') {
                    sku = (packingEditSku || '').trim();
                } else {
                    sku = $(skuSelect).val() ? String($(skuSelect).val()).trim() : '';
                }
                if (!sku) {
                    showToast('warning', 'Please select a SKU.');
                    if (packingFormMode !== 'edit' && $(skuSelect).hasClass('select2-hidden-accessible')) {
                        $(skuSelect).select2('open');
                    }
                    return;
                }
                const url = packingFormMode === 'edit'
                    ? '/packing-instructions-master/update'
                    : '/packing-instructions-master/store';
                const payload = collectPackingPayload(sku);
                saveBtn.disabled = true;
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.success === false) {
                        throw new Error(data.message || 'Save failed');
                    }
                    showToast('success', data.message || 'Saved.');
                    bootstrap.Modal.getInstance(document.getElementById('addPackingModal'))?.hide();
                    loadData();
                } catch (e) {
                    showToast('danger', e.message || 'Save failed');
                } finally {
                    saveBtn.disabled = false;
                }
            }

            let piFilePondPluginRegistered = false;

            function destroyPiFilePond() {
                if (window.piFilePondInstance) {
                    window.piFilePondInstance.destroy();
                    window.piFilePondInstance = null;
                }
            }

            function initPiFilePond() {
                destroyPiFilePond();
                if (typeof FilePond === 'undefined' || typeof FilePondPluginImagePreview === 'undefined') {
                    showToast('warning', 'File upload library failed to load. Check your network.');
                    return;
                }
                if (!piFilePondPluginRegistered) {
                    FilePond.registerPlugin(FilePondPluginImagePreview);
                    piFilePondPluginRegistered = true;
                }
                const input = document.getElementById('piFilepondInput');
                if (!input) return;
                window.piFilePondInstance = FilePond.create(input, {
                    name: 'image',
                    allowMultiple: true,
                    maxFiles: 24,
                    credits: false,
                    labelIdle: 'Drag & drop packing photos or <span class="filepond--label-action">Browse</span>',
                    server: {
                        process: {
                            url: '/packing-instructions-master/upload-image',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            ondata: (formData) => {
                                formData.append('sku', window.piImagesModalSku || '');
                                return formData;
                            },
                            onload: (response) => {
                                let r;
                                try {
                                    r = JSON.parse(response);
                                } catch (err) {
                                    throw new Error('Invalid server response');
                                }
                                if (!r.success) {
                                    throw new Error(r.message || 'Upload failed');
                                }
                                refreshPackingImageGallery();
                                loadData();
                                return r.id;
                            },
                            onerror: (response) => {
                                try {
                                    const r = JSON.parse(response);
                                    return r.message || r.error || 'Upload failed';
                                } catch (e) {
                                    return response || 'Upload failed';
                                }
                            }
                        }
                    }
                });
            }

            function formatAiAnalysisHtml(analysis) {
                if (!analysis || typeof analysis !== 'object') {
                    return '<p class="text-muted mb-0">No structured result.</p>';
                }
                const sev = String(analysis.severity || 'none').toLowerCase();
                const okSev = ['high', 'medium', 'low', 'none'].indexOf(sev) >= 0 ? sev : 'none';
                const cls = 'pi-ai-severity-' + okSev;
                let html = '<div class="mb-2">Severity: <span class="' + cls + '">' + escapeHtml(sev) + '</span></div>';
                if (analysis.summary) {
                    html += '<p class="mb-2">' + escapeHtml(String(analysis.summary)) + '</p>';
                }
                if (Array.isArray(analysis.defects) && analysis.defects.length) {
                    html += '<ul class="mb-2 ps-3">';
                    analysis.defects.forEach(function(d) {
                        html += '<li><strong>' + escapeHtml(d.type || 'Defect') + '</strong>';
                        if (d.detail) html += ' — ' + escapeHtml(String(d.detail));
                        if (d.confidence) html += ' <span class="text-muted">(' + escapeHtml(String(d.confidence)) + ')</span>';
                        html += '</li>';
                    });
                    html += '</ul>';
                }
                if (Array.isArray(analysis.recommendations) && analysis.recommendations.length) {
                    html += '<div class="fw-semibold mb-1">Recommendations</div><ul class="ps-3 mb-0">';
                    analysis.recommendations.forEach(function(rec) {
                        html += '<li>' + escapeHtml(String(rec)) + '</li>';
                    });
                    html += '</ul>';
                }
                return html;
            }

            async function refreshPackingImageGallery() {
                const sku = window.piImagesModalSku;
                const box = document.getElementById('piPackingGallery');
                const empty = document.getElementById('piPackingGalleryEmpty');
                if (!box) return;
                box.innerHTML = '';
                if (!sku) {
                    if (empty) empty.classList.remove('d-none');
                    return;
                }
                try {
                    const r = await fetch('/packing-instructions-master/images?sku=' + encodeURIComponent(sku), {
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                    });
                    const data = await r.json();
                    if (!data.success || !Array.isArray(data.images)) {
                        if (empty) empty.classList.remove('d-none');
                        return;
                    }
                    if (data.images.length === 0) {
                        if (empty) empty.classList.remove('d-none');
                        return;
                    }
                    if (empty) empty.classList.add('d-none');
                    const openAi = isPiOpenAiEnabled();
                    data.images.forEach(function(img) {
                        const col = document.createElement('div');
                        col.className = 'col-6 col-md-4 col-lg-3';
                        col.innerHTML =
                            '<div class="pi-packing-gallery-card h-100">' +
                            '<img class="pi-gal-thumb" src="' + escapeHtml(img.url) + '" alt="">' +
                            '<div class="pi-gal-meta text-truncate" title="' + escapeHtml(img.name) + '">' + escapeHtml(img.name) + '</div>' +
                            '<div class="d-flex flex-wrap gap-1 px-2 pb-2">' +
                            '<button type="button" class="btn btn-sm btn-outline-primary pi-gal-preview" data-url="' + escapeHtml(img.url) + '" data-name="' + escapeHtml(img.name) + '">View</button>' +
                            '<button type="button" class="btn btn-sm btn-outline-secondary pi-gal-ai" data-id="' + escapeHtml(img.id) + '"' +
                            (openAi ? '' : ' disabled title="Set OPENAI_API_KEY in .env"') + '>AI scan</button>' +
                            '<button type="button" class="btn btn-sm btn-outline-danger pi-gal-del" data-id="' + escapeHtml(img.id) + '">Delete</button>' +
                            '</div></div>';
                        box.appendChild(col);
                    });
                } catch (err) {
                    console.error(err);
                    if (empty) empty.classList.remove('d-none');
                }
            }

            function openPackingImagesModal(sku) {
                const s = String(sku || '').trim();
                if (!s || s.toUpperCase().includes('PARENT')) {
                    showToast('warning', 'Use a product SKU row (not parent summary).');
                    return;
                }
                window.piImagesModalSku = s;
                const lbl = document.getElementById('piPackingImagesSkuLabel');
                if (lbl) lbl.textContent = s;
                const panel = document.getElementById('piAiResultPanel');
                const body = document.getElementById('piAiResultBody');
                if (panel) panel.classList.add('d-none');
                if (body) body.innerHTML = '';
                const warn = document.getElementById('piAiDisabledNotice');
                if (warn) warn.classList.toggle('d-none', isPiOpenAiEnabled());
                bootstrap.Modal.getOrCreateInstance(document.getElementById('piPackingImagesModal')).show();
            }

            function setupPackingImagesUi() {
                const gal = document.getElementById('piPackingGallery');
                if (gal) {
                    gal.addEventListener('click', async function(e) {
                        const sku = window.piImagesModalSku;
                        if (!sku) return;
                        const previewBtn = e.target.closest('.pi-gal-preview');
                        const thumb = e.target.closest('.pi-gal-thumb');
                        if (previewBtn || thumb) {
                            let url = '';
                            let name = 'Preview';
                            if (thumb) {
                                url = thumb.getAttribute('src') || '';
                                const meta = thumb.closest('.pi-packing-gallery-card')?.querySelector('.pi-gal-meta');
                                if (meta && meta.textContent) name = meta.textContent.trim();
                            } else {
                                url = previewBtn.getAttribute('data-url') || '';
                                name = previewBtn.getAttribute('data-name') || name;
                            }
                            const tEl = document.getElementById('piImagePreviewTitle');
                            const iEl = document.getElementById('piImagePreviewImg');
                            if (tEl) tEl.textContent = name;
                            if (iEl) iEl.setAttribute('src', url);
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('piImagePreviewModal')).show();
                            return;
                        }
                        const del = e.target.closest('.pi-gal-del');
                        if (del) {
                            const id = del.getAttribute('data-id');
                            if (!id || !confirm('Remove this image from the SKU?')) return;
                            try {
                                const res = await fetch('/packing-instructions-master/delete-image', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                                    body: JSON.stringify({ sku: sku, id: id })
                                });
                                const data = await res.json();
                                if (!res.ok || data.success === false) throw new Error(data.message || 'Delete failed');
                                showToast('success', 'Image removed.');
                                refreshPackingImageGallery();
                                loadData();
                            } catch (err) {
                                showToast('danger', err.message);
                            }
                            return;
                        }
                        const ai = e.target.closest('.pi-gal-ai');
                        if (ai) {
                            const id = ai.getAttribute('data-id');
                            if (!id || ai.disabled) return;
                            ai.disabled = true;
                            const panel = document.getElementById('piAiResultPanel');
                            const bodyEl = document.getElementById('piAiResultBody');
                            if (panel) panel.classList.remove('d-none');
                            if (bodyEl) bodyEl.innerHTML = '<div class="small text-muted">Analyzing image…</div>';
                            try {
                                const res = await fetch('/packing-instructions-master/ai-defect-scan', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                                    body: JSON.stringify({ sku: sku, id: id })
                                });
                                const data = await res.json();
                                if (res.status === 503) {
                                    if (bodyEl) {
                                        bodyEl.innerHTML = '<p class="text-warning mb-0">' + escapeHtml(data.message || 'AI unavailable') + '</p>';
                                    }
                                    return;
                                }
                                if (!res.ok || data.success === false) throw new Error(data.message || 'Scan failed');
                                if (data.analysis && bodyEl) {
                                    bodyEl.innerHTML = formatAiAnalysisHtml(data.analysis);
                                } else if (data.raw && bodyEl) {
                                    bodyEl.innerHTML = '<pre class="small mb-0" style="white-space:pre-wrap">' + escapeHtml(String(data.raw)) + '</pre>';
                                }
                            } catch (err) {
                                if (bodyEl) bodyEl.innerHTML = '<p class="text-danger mb-0">' + escapeHtml(err.message) + '</p>';
                            } finally {
                                ai.disabled = false;
                            }
                        }
                    });
                }
                const refBtn = document.getElementById('piGalleryRefreshBtn');
                if (refBtn) refBtn.addEventListener('click', refreshPackingImageGallery);
                const piImgModalEl = document.getElementById('piPackingImagesModal');
                if (piImgModalEl) {
                    piImgModalEl.addEventListener('shown.bs.modal', function() {
                        initPiFilePond();
                        refreshPackingImageGallery();
                    });
                    piImgModalEl.addEventListener('hidden.bs.modal', function() {
                        destroyPiFilePond();
                        window.piImagesModalSku = '';
                    });
                }
            }

            function setupExcelExport() {
                document.getElementById('downloadPackingExcel').addEventListener('click', function() {
                    const columns = ['Parent', 'SKU', 'Status', 'INV', 'Photos'].concat(PACKING_FIELD_KEYS);
                    const btn = this;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> …';
                    setTimeout(() => {
                        try {
                            const dataToExport = filteredData.length ? filteredData : tableData;
                            const wsData = [columns];
                            dataToExport.forEach(item => {
                                const row = [];
                                columns.forEach(col => {
                                    if (col === 'Status') {
                                        const lbl = formatProductMasterStatusLabel(resolveProductMasterStatus(item));
                                        row.push(lbl === '—' ? '' : lbl);
                                    } else if (col === 'INV') {
                                        let v = item.shopify_inv;
                                        if (v === 0 || v === '0') row.push(0);
                                        else if (v === null || v === undefined || v === '') row.push('');
                                        else row.push(parseFloat(v) || 0);
                                    } else if (col === 'Photos') {
                                        row.push(packingImageCount(item));
                                    } else {
                                        const v = item[col];
                                        row.push(v !== undefined && v !== null ? v : '');
                                    }
                                });
                                wsData.push(row);
                            });
                            const wb = XLSX.utils.book_new();
                            const ws = XLSX.utils.aoa_to_sheet(wsData);
                            ws['!cols'] = columns.map(() => ({ wch: 18 }));
                            XLSX.utils.book_append_sheet(wb, ws, 'Packing Instructions');
                            XLSX.writeFile(wb, 'packing_instructions_export.xlsx');
                            showToast('success', 'Excel downloaded.');
                        } catch (e) {
                            console.error(e);
                            showToast('danger', 'Export failed.');
                        } finally {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-file-excel me-1"></i> Download Excel';
                        }
                    }, 50);
                });
            }

            document.getElementById('addPackingBtn').addEventListener('click', () => openPackingModal('add'));

            const gridHost = document.getElementById('packing-tabulator');
            if (gridHost) {
                gridHost.addEventListener('click', function(e) {
                    const photosBtn = e.target.closest('.pi-photos-btn');
                    if (photosBtn && this.contains(photosBtn)) {
                        e.preventDefault();
                        const sku = photosBtn.getAttribute('data-sku');
                        if (sku) openPackingImagesModal(sku);
                        return;
                    }
                    const editBtn = e.target.closest('.edit-btn');
                    if (editBtn && this.contains(editBtn)) {
                        e.preventDefault();
                        const sku = editBtn.getAttribute('data-sku');
                        if (sku) openPackingModal('edit', sku);
                    }
                    const delBtn = e.target.closest('.delete-btn');
                    if (delBtn && this.contains(delBtn)) {
                        e.preventDefault();
                        const sku = delBtn.getAttribute('data-sku');
                        if (!sku) return;
                        if (!confirm('Clear all packing instruction fields for this SKU?')) return;
                        fetch('/packing-instructions-master/clear', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                            body: JSON.stringify({ sku: sku })
                        })
                            .then(r => r.json().then(d => ({ ok: r.ok, d })))
                            .then(({ ok, d }) => {
                                if (!ok || d.success === false) throw new Error(d.message || 'Clear failed');
                                showToast('success', d.message || 'Cleared.');
                                loadData();
                            })
                            .catch(err => showToast('danger', err.message));
                    }
                });
            }

            setupSearch();
            setupPiStatusFilter();
            refreshPiStatusFilterUI();
            setupPackingImagesUi();
            setupExcelExport();
            loadData();
        });
    </script>
@endsection
