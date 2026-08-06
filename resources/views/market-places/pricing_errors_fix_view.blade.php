@extends('layouts.vertical', ['title' => 'Pricing Errors Fix', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Hide sort arrows on all headers (sorting still works via click) */
        .tabulator-col .tabulator-col-sorter,
        .tabulator-col.pef-sortable .tabulator-col-sorter,
        .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter {
            display: none !important;
            visibility: hidden !important;
            width: 0 !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
        }
        .tabulator-col .tabulator-col-sorter-element,
        .tabulator-col .tabulator-arrow {
            display: none !important;
        }
        /* Horizontal headers — readable, compact */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed;
            white-space: nowrap;
            transform: none !important;
            height: auto !important;
            min-height: 0 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.01em;
            line-height: 1.2;
            padding: 0 2px;
        }
        .tabulator-col.pef-sortable {
            cursor: pointer;
        }
        .tabulator .tabulator-header {
            border-bottom: 1px solid #0aa2c0;
        }
        .tabulator .tabulator-header .tabulator-col {
            background: #0dcaf0 !important;
            color: #fff !important;
            border-right: 1px solid rgba(255,255,255,.25) !important;
        }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 6px 4px !important;
        }
        .tabulator .tabulator-header .tabulator-col .tabulator-header-filter {
            padding: 2px 3px 4px !important;
        }
        .tabulator .tabulator-header .tabulator-col .tabulator-header-filter input,
        .tabulator .tabulator-header .tabulator-col .tabulator-header-filter select {
            height: 26px !important;
            font-size: 11px !important;
            border-radius: 4px;
        }
        .tabulator .tabulator-header .tabulator-col-resize-handle { display: none !important; }
        .tabulator .tabulator-cell {
            padding: 4px 6px !important;
            font-size: 12px;
            line-height: 1.25;
        }
        .tabulator .tabulator-row .tabulator-cell {
            border-right: 1px solid #eef2f7;
        }
        .pef-sku-cell { font-weight: 700; color: #0d6efd; }
        .pef-channel-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            font-size: 11px;
            font-weight: 600;
            color: #334155;
            white-space: nowrap;
        }
        .pef-metric-red { color: #dc3545; font-weight: 600; }
        .pef-metric-yellow { color: #b78103; font-weight: 600; }
        .pef-metric-green { color: #198754; font-weight: 600; }
        .pef-metric-hot { color: #e83e8c; font-weight: 700; background: transparent; padding: 0; border-radius: 0; }
        .pef-success-dot {
            display: inline-block; width: 12px; height: 12px; border-radius: 50%;
            border: 1px solid #94a3b8; background: #e2e8f0;
        }
        .pef-success-dot.ok { background: #22c55e; border-color: #15803d; }
        .pef-success-dot.err { background: #ef4444; border-color: #b91c1c; }
        .pef-success-dot.pending { background: #f59e0b; border-color: #b45309; }
        .pef-success-dot.saved { background: #3b82f6; border-color: #1d4ed8; }
        .status-circle {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 4px;
            vertical-align: middle;
            border: 1px solid rgba(0,0,0,.15);
        }
        .status-circle.default { background: #94a3b8; }
        .status-circle.red { background: #dc3545; }
        .status-circle.yellow { background: #ffc107; }
        .status-circle.orange { background: #fd7e14; }
        .status-circle.green { background: #28a745; }
        .status-circle.blue { background: #0d6efd; }
        .status-circle.pink { background: #e83e8c; }
        .status-circle.magenta-bg { background: #d63384; }
        .pef-dil-red { color: #dc3545; font-weight: 700; }
        .pef-dil-green { color: #28a745; font-weight: 700; }
        .pef-dil-pink { color: #e83e8c; font-weight: 700; }
        #pef-dil-filter-btn .status-circle,
        #pef-gpft-filter-btn .status-circle,
        #pef-groi-filter-btn .status-circle { margin-right: 6px; }
        #pef-table-wrapper { height: calc(100vh - 200px); display: flex; flex-direction: column; }
        #pef-loading {
            position: absolute; inset: 0; background: rgba(255,255,255,.75);
            display: flex; align-items: center; justify-content: center; z-index: 20;
            font-weight: 600; color: #334155;
        }
        /* Exactly 2 toolbar lines — overflow visible so dropdown menus are not clipped */
        .pef-toolbar {
            display: flex;
            flex-direction: column;
            gap: 6px;
            width: 100%;
            max-width: 100%;
            overflow: visible;
            position: relative;
            z-index: 30;
        }
        .pef-toolbar-row {
            --pef-ctrl-h: 30px;
            --pef-ctrl-fs: 12px;
            --pef-ctrl-radius: 0.375rem;
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 4px;
            min-height: var(--pef-ctrl-h);
            width: 100%;
            max-width: 100%;
            overflow: visible;
            white-space: nowrap;
            position: relative;
        }
        /* Line 1 above line 2 so Dil%/GPFT%/GROI% menus paint over Prc Mode row */
        .pef-toolbar-row:first-child { z-index: 40; }
        .pef-toolbar-row:last-child { z-index: 20; }
        .pef-toolbar-row:has(.dropdown.show) { z-index: 1080; }
        .pef-toolbar-row > * { flex-shrink: 0; }
        /* Hidden filter values must not participate in flex / stacking */
        .pef-toolbar-row > input[type="hidden"] { display: none !important; }

        /* Unified control height across both toolbar rows */
        .pef-toolbar-row .form-select,
        .pef-toolbar-row .form-control,
        .pef-toolbar-row .btn,
        .pef-toolbar-row .badge,
        .pef-toolbar-row .input-group,
        .pef-toolbar-row .input-group-text,
        .pef-toolbar-row .pef-target-box,
        .pef-toolbar-row .btn-group {
            height: var(--pef-ctrl-h) !important;
            min-height: var(--pef-ctrl-h) !important;
            max-height: var(--pef-ctrl-h) !important;
            font-size: var(--pef-ctrl-fs) !important;
            line-height: 1.2 !important;
            box-sizing: border-box;
        }
        .pef-toolbar-row .form-select,
        .pef-toolbar-row .form-control {
            padding: 0 0.5rem !important;
            border-radius: var(--pef-ctrl-radius);
        }
        .pef-toolbar-row .btn {
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 0.55rem !important;
            border-radius: var(--pef-ctrl-radius);
            gap: 4px;
        }
        .pef-toolbar-row .btn-group > .btn {
            border-radius: var(--pef-ctrl-radius);
        }
        .pef-toolbar-row .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 0.55rem !important;
            border-radius: var(--pef-ctrl-radius);
            font-weight: 600;
        }
        .pef-toolbar-row .input-group {
            flex-shrink: 1;
            min-width: 0;
            width: auto;
            display: inline-flex;
            align-items: stretch;
        }
        .pef-toolbar-row .input-group > .form-control,
        .pef-toolbar-row .input-group > .input-group-text,
        .pef-toolbar-row .input-group > .btn {
            height: 100% !important;
            min-height: 0 !important;
            max-height: none !important;
        }
        .pef-toolbar-row .input-group-text {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 0.45rem !important;
        }
        .pef-toolbar-row .dropdown {
            position: relative;
            z-index: 2;
            height: var(--pef-ctrl-h);
        }
        .pef-toolbar-row .dropdown > .btn {
            height: 100% !important;
        }
        .pef-toolbar-row .dropdown.show {
            z-index: 1080;
        }
        .pef-toolbar-row .dropdown .dropdown-menu {
            z-index: 1085;
        }
        .pef-toolbar-row label.pef-lbl {
            font-size: var(--pef-ctrl-fs);
            color: #64748b;
            margin: 0;
            line-height: var(--pef-ctrl-h);
            height: var(--pef-ctrl-h);
            display: inline-flex;
            align-items: center;
        }
        .pef-toolbar-row .form-check {
            position: relative;
            z-index: 5;
            margin-left: 6px;
            padding-left: 0;
            height: var(--pef-ctrl-h);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .pef-toolbar-row .form-check .form-check-input {
            position: relative;
            z-index: 6;
            margin: 0;
            float: none;
            flex-shrink: 0;
            width: 16px;
            height: 16px;
        }
        .pef-toolbar-row .form-check-label {
            font-size: var(--pef-ctrl-fs);
            position: relative;
            z-index: 6;
            line-height: 1;
        }
        .pef-toolbar-spacer { flex: 1 1 auto; min-width: 4px; }
        /* Parent bodies must not clip Dil%/GPFT%/GROI%/Prc Mode menus */
        .card:has(.pef-toolbar) { overflow: visible; }
        .card-body:has(.pef-toolbar) {
            position: relative;
            z-index: 40;
            overflow: visible;
        }
        .card-body:has(#pef-table-wrapper) {
            position: relative;
            z-index: 1;
        }
        .pef-target-box {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 0 6px !important;
            border: 1px solid #dee2e6;
            border-radius: var(--pef-ctrl-radius);
            background: #fff;
            box-sizing: border-box;
        }
        .pef-target-box label {
            font-size: var(--pef-ctrl-fs);
            line-height: 1;
            white-space: nowrap;
        }
        .pef-target-box .form-control {
            height: 22px !important;
            min-height: 22px !important;
            max-height: 22px !important;
            padding: 0 4px !important;
            font-size: var(--pef-ctrl-fs) !important;
        }
        .pef-target-box .btn {
            height: 22px !important;
            min-height: 22px !important;
            max-height: 22px !important;
            width: 22px;
            padding: 0 !important;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Pricing Errors Fix',
        'sub_title' => 'All channels — per-channel pricing logic',
    ])

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1080;"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2">
                <div class="pef-toolbar">
                    {{-- Line 1: status + filters --}}
                    <div class="pef-toolbar-row">
                        <span class="badge text-bg-dark" id="pef-rows-badge">Rows: 0</span>
                        <span class="badge text-bg-secondary" id="pef-channels-badge">Channels: 0</span>
                        <span class="badge text-bg-danger" id="pef-error-badge" style="display:none;">Channel errors: 0</span>

                        <label class="pef-lbl" for="pef-channel-filter">Channel</label>
                        <select id="pef-channel-filter" class="form-select form-select-sm" style="width:118px;" title="Filter channel (client-side)">
                            <option value="">All channels</option>
                            @foreach(($channels ?? []) as $ch)
                                <option value="{{ is_array($ch) ? ($ch['key'] ?? '') : $ch }}">{{ is_array($ch) ? ($ch['label'] ?? $ch['key'] ?? '') : $ch }}</option>
                            @endforeach
                        </select>

                        <label class="pef-lbl" for="pef-inv-filter">INV</label>
                        <select id="pef-inv-filter" class="form-select form-select-sm" style="width:64px;" title="Filter by inventory">
                            <option value="all">All</option>
                            <option value="eq_0">= 0</option>
                            <option value="gt_0">&gt; 0</option>
                        </select>

                        <label class="pef-lbl" for="pef-price-filter">Price</label>
                        <select id="pef-price-filter" class="form-select form-select-sm" style="width:72px;"
                            title="Price filter — Exist = price &gt; 0">
                            <option value="all">All</option>
                            <option value="null">Null</option>
                            <option value="exist">Exist</option>
                        </select>

                        <span class="badge text-bg-danger" id="pef-missing-badge"
                            title="Shortcut: INV &gt; 0 + Price Null">Missing: 0</span>

                        <span class="badge text-bg-danger" id="pef-sku-groi-badge"
                            title="Unique SKUs with GROI &lt; 40% — click to filter">SKU: 0</span>

                        <div class="dropdown">
                            <button class="btn btn-sm btn-light dropdown-toggle border" type="button"
                                id="pef-dil-filter-btn" data-bs-toggle="dropdown" aria-expanded="false"
                                title="Filter by Dil% color band">
                                <span class="status-circle default" id="pef-dil-filter-dot"></span>
                                <span id="pef-dil-filter-label">Dil%</span>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="pef-dil-filter-btn">
                                <li><a class="dropdown-item pef-dil-filter-item active" href="#" data-color="all"><span class="status-circle default"></span> All Dil%</a></li>
                                <li><a class="dropdown-item pef-dil-filter-item" href="#" data-color="red"><span class="status-circle red"></span> Red (&lt;25%)</a></li>
                                <li><a class="dropdown-item pef-dil-filter-item" href="#" data-color="green"><span class="status-circle green"></span> Green (25–50%)</a></li>
                                <li><a class="dropdown-item pef-dil-filter-item" href="#" data-color="pink"><span class="status-circle pink"></span> Pink (50%+)</a></li>
                            </ul>
                        </div>
                        <input type="hidden" id="pef-dil-filter" value="all">

                        <div class="dropdown">
                            <button class="btn btn-sm btn-light dropdown-toggle border" type="button"
                                id="pef-gpft-filter-btn" data-bs-toggle="dropdown" aria-expanded="false"
                                title="Filter by GPFT% slab">
                                <span class="status-circle default" id="pef-gpft-filter-dot"></span>
                                <span id="pef-gpft-filter-label">GPFT%</span>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="pef-gpft-filter-btn">
                                <li><a class="dropdown-item pef-gpft-filter-item active" href="#" data-range="all"><span class="status-circle default"></span> All GPFT</a></li>
                                <li><a class="dropdown-item pef-gpft-filter-item" href="#" data-range="lt-20"><span class="status-circle red"></span> &lt; 20%</a></li>
                                <li><a class="dropdown-item pef-gpft-filter-item" href="#" data-range="20-30"><span class="status-circle yellow"></span> 20–30%</a></li>
                                <li><a class="dropdown-item pef-gpft-filter-item" href="#" data-range="30-40"><span class="status-circle green"></span> 30–40%</a></li>
                                <li><a class="dropdown-item pef-gpft-filter-item" href="#" data-range="gt-40"><span class="status-circle magenta-bg"></span> &gt; 40%</a></li>
                            </ul>
                        </div>
                        <input type="hidden" id="pef-gpft-filter" value="all">

                        <div class="dropdown">
                            <button class="btn btn-sm btn-light dropdown-toggle border" type="button"
                                id="pef-groi-filter-btn" data-bs-toggle="dropdown" aria-expanded="false"
                                title="Filter by GROI% slab">
                                <span class="status-circle default" id="pef-groi-filter-dot"></span>
                                <span id="pef-groi-filter-label">GROI%</span>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="pef-groi-filter-btn">
                                <li><a class="dropdown-item pef-groi-filter-item active" href="#" data-range="all"><span class="status-circle default"></span> All GROI</a></li>
                                <li><a class="dropdown-item pef-groi-filter-item" href="#" data-range="lt-40"><span class="status-circle red"></span> &lt; 40%</a></li>
                                <li><a class="dropdown-item pef-groi-filter-item" href="#" data-range="40-60"><span class="status-circle yellow"></span> 40–60%</a></li>
                                <li><a class="dropdown-item pef-groi-filter-item" href="#" data-range="60-80"><span class="status-circle orange"></span> 60–80%</a></li>
                                <li><a class="dropdown-item pef-groi-filter-item" href="#" data-range="80-100"><span class="status-circle green"></span> 80–100%</a></li>
                                <li><a class="dropdown-item pef-groi-filter-item" href="#" data-range="gte-100"><span class="status-circle pink"></span> 100%+</a></li>
                            </ul>
                        </div>
                        <input type="hidden" id="pef-groi-filter" value="all">

                        <div class="form-check form-check-inline mb-0 me-0">
                            <input class="form-check-input" type="checkbox" id="pef-listed-only" checked>
                            <label class="form-check-label" for="pef-listed-only"
                                title="Listed / has price or SPRICE (reloads from cache)">Listed</label>
                        </div>
                    </div>

                    {{-- Line 2: search + targets + cache/actions --}}
                    <div class="pef-toolbar-row">
                        <div class="input-group input-group-sm" style="width:160px;">
                            <span class="input-group-text" title="Quick Search Parent"><i class="fas fa-search"></i></span>
                            <input type="text" id="pef-parent-search" class="form-control"
                                list="pef-parent-datalist"
                                placeholder="Parent…"
                                autocomplete="off"
                                title="Quick Search Parent">
                            <datalist id="pef-parent-datalist"></datalist>
                        </div>
                        <input type="text" id="pef-sku-search" class="form-control form-control-sm"
                            placeholder="SKU…" style="width:110px;" autocomplete="off">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="pef-clear-search-btn"
                            title="Clear Parent / SKU search">Clear</button>
                        <span class="small text-muted" id="pef-parent-match-hint"></span>

                        <div class="pef-target-box" id="pef-target-roi-controls"
                            title="Target ROI% — sets SPrice so Sroi = target">
                            <label for="pef-target-roi-input" class="mb-0 fw-bold">🎯 ROI%:</label>
                            <input type="number" id="pef-target-roi-input" class="form-control form-control-sm text-end"
                                placeholder="30" step="0.1" style="width:52px;">
                            <button type="button" id="pef-apply-target-roi-btn" class="btn btn-sm btn-success" title="Apply Target ROI%">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>
                        <div class="pef-target-box" id="pef-target-gpft-controls"
                            title="Target GPFT% — sets SPrice so Sgpft = target">
                            <label for="pef-target-gpft-input" class="mb-0 fw-bold">🎯 GPFT%:</label>
                            <input type="number" id="pef-target-gpft-input" class="form-control form-control-sm text-end"
                                placeholder="30" step="0.1" style="width:52px;">
                            <button type="button" id="pef-apply-target-gpft-btn" class="btn btn-sm btn-success" title="Apply Target GPFT%">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>

                        <span class="badge bg-primary" id="pef-selected-count" style="display:none;">0 selected</span>

                        <div class="btn-group">
                            <button type="button" id="pef-price-pct-btn" class="btn btn-sm btn-primary dropdown-toggle"
                                data-bs-toggle="dropdown" aria-expanded="false" title="Decrease / Increase / Same Price">
                                <i class="fas fa-percent"></i> Prc Mode
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" id="pef-price-pct-dropdown">
                                <li><a class="dropdown-item" href="#" data-mode="decrease"><i class="fas fa-minus-circle text-warning"></i> Decrease</a></li>
                                <li><a class="dropdown-item" href="#" data-mode="increase"><i class="fas fa-plus-circle text-success"></i> Increase</a></li>
                                <li><a class="dropdown-item" href="#" data-mode="same"><i class="fas fa-equals text-info"></i> Same Price</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-mode="cancel"><i class="fas fa-times"></i> Cancel</a></li>
                            </ul>
                        </div>

                        <span class="pef-toolbar-spacer"></span>

                        <button type="button" class="btn btn-sm btn-outline-secondary" id="pef-reload-btn"
                            title="Reload from pre-calculated table (instant)">
                            <i class="fas fa-sync-alt"></i> Reload
                        </button>
                        <span class="small text-muted d-none" id="pef-pull-hint"></span>
                        <button type="button" class="btn btn-sm btn-primary" id="pef-bulk-push-btn" disabled
                            title="Push SPRICE for selected rows">
                            <i class="fas fa-upload"></i> Push (<span id="pef-push-count">0</span>)
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0 position-relative">
                <div id="pef-loading" style="display:none;"><i class="fas fa-spinner fa-spin me-2"></i> Pull Data to load…</div>
                <div id="pef-table-wrapper">
                    {{-- Shown only when Prc Mode Decrease/Increase/Same is active --}}
                    <div id="pef-discount-input-container" class="p-2 bg-light border-bottom" style="display:none;">
                        <div class="d-flex align-items-center gap-2 flex-nowrap">
                            <span id="pef-discount-input-label" class="text-muted fw-bold me-1">By how much:</span>
                            <span id="pef-discount-type-select-wrap">
                                <select id="pef-discount-type-select" class="form-select form-select-sm" style="width:140px;">
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="value">Value ($)</option>
                                </select>
                            </span>
                            <input type="number" id="pef-discount-percentage-input" class="form-control form-control-sm"
                                placeholder="e.g. 10 or 2.50" step="0.1" min="0"
                                style="width:140px;" title="Enter % or $ amount to decrease/increase price">
                            <button type="button" id="pef-apply-discount-btn" class="btn btn-sm btn-primary">
                                <i class="fas fa-check"></i> Apply
                            </button>
                            <span id="pef-discount-selected-hint" class="text-muted ms-2"></span>
                        </div>
                    </div>
                    <div id="pef-table" style="flex:1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
(function() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let table = null;
    const selectedIds = new Set();
    /** Pre-embedded from DB on page render — no Ajax needed on first open */
    const PEF_INITIAL_ROWS = @json($initial_rows ?? []);
    /** Full dataset — filters work client-side; Reload refreshes via Ajax */
    let pulledRows = Array.isArray(PEF_INITIAL_ROWS) ? PEF_INITIAL_ROWS.slice() : [];
    let dataLoaded = pulledRows.length > 0;
    let pullInProgress = false;
    // % Prc Mode flags — same as /amazon-tabulator-view
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let samePriceModeActive = false;

    function toast(msg, type) {
        const bg = type === 'error' ? 'text-bg-danger' : (type === 'success' ? 'text-bg-success' : 'text-bg-dark');
        const id = 't' + Date.now();
        const html = `<div id="${id}" class="toast align-items-center ${bg} border-0 show mb-2" role="alert">
            <div class="d-flex"><div class="toast-body">${msg}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
        $('.toast-container').append(html);
        setTimeout(() => $('#' + id).fadeOut(200, function() { $(this).remove(); }), 4000);
    }

    function metricClass(v) {
        if (v === null || v === undefined || v === '') return '';
        const n = Number(v);
        if (isNaN(n)) return '';
        if (n < 20) return 'pef-metric-red';
        if (n < 30) return 'pef-metric-yellow';
        if (n <= 40) return 'pef-metric-green';
        return 'pef-metric-hot';
    }

    function fmtPct(cell) {
        const v = cell.getValue();
        if (v === null || v === undefined || v === '') return '';
        const n = Number(v);
        if (isNaN(n)) return '';
        const cls = metricClass(n);
        return cls ? `<span class="${cls}">${Math.round(n)}%</span>` : `${Math.round(n)}%`;
    }

    function fmtMoney(cell) {
        let v = cell.getValue();
        if (v === null || v === undefined || v === '') {
            // Fallback if row still has raw channel price under another key
            const d = cell.getRow().getData() || {};
            v = d.price ?? d.channel_price ?? null;
        }
        if (v === null || v === undefined || v === '') return '';
        if (typeof v === 'string') v = v.replace(/[$,\s]/g, '');
        const n = Number(v);
        if (!isFinite(n) || n <= 0) return '';
        return '$' + n.toFixed(2);
    }

    function successDot(status) {
        const s = String(status || '').toLowerCase();
        let cls = '';
        let tip = status || '—';
        if (['pushed', 'success', 'ok', 'applied'].includes(s)) cls = 'ok';
        else if (['error', 'failed', 'fail'].includes(s)) cls = 'err';
        else if (['pushing', 'pending', 'queued'].includes(s)) cls = 'pending';
        else if (['saved'].includes(s)) cls = 'saved';
        return `<span class="pef-success-dot ${cls}" title="${String(tip).replace(/"/g, '&quot;')}"></span>`;
    }

    function updatePushBtn() {
        let n = 0;
        let selected = 0;
        if (table) {
            table.getRows('active').forEach(row => {
                const d = row.getData();
                if (selectedIds.has(d.id)) {
                    selected++;
                    if (d.sprice > 0) n++;
                }
            });
        }
        $('#pef-push-count').text(n);
        $('#pef-bulk-push-btn').prop('disabled', n === 0);
        if (selected > 0) {
            $('#pef-selected-count').show().text(selected + ' selected');
        } else {
            $('#pef-selected-count').hide();
        }
        if (decreaseModeActive || increaseModeActive || samePriceModeActive) {
            $('#pef-discount-selected-hint').text(selected ? (selected + ' row(s) selected') : 'Select rows to apply');
        }
    }

    /** Retail rounding — same as /amazon-tabulator-view */
    function roundToRetailPrice(price) {
        if (price < 20.99) return +Number(price).toFixed(2);
        const roundedDollar = Math.ceil(price);
        return +(roundedDollar - 0.01).toFixed(2);
    }
    function roundToRetailPrice49(price) {
        if (price < 20.99) return +Number(price).toFixed(2);
        const roundedDollar = Math.ceil(price);
        return +(roundedDollar - 0.51).toFixed(2);
    }

    function exitPricePctMode() {
        decreaseModeActive = false;
        increaseModeActive = false;
        samePriceModeActive = false;
        $('#pef-discount-input-container').hide();
        $('#pef-price-pct-btn').removeClass('btn-danger btn-warning btn-success btn-info').addClass('btn-primary')
            .html('<i class="fas fa-percent"></i> Prc Mode');
        $('#pef-apply-discount-btn').html('<i class="fas fa-check"></i> Apply');
        $('#pef-discount-type-select-wrap').show();
        $('#pef-discount-input-label').text('By how much:');
        $('#pef-discount-percentage-input')
            .attr('placeholder', 'e.g. 10 or 2.50')
            .attr('title', 'Enter % or $ amount to decrease/increase price');
        $('#pef-discount-selected-hint').text('');
    }

    function setPricePctMode(mode) {
        if (mode === 'cancel') {
            exitPricePctMode();
            return;
        }
        decreaseModeActive = (mode === 'decrease');
        increaseModeActive = (mode === 'increase');
        samePriceModeActive = (mode === 'same');
        $('#pef-discount-input-container').show();
        $('#pef-discount-percentage-input').val('');
        updatePushBtn();

        if (mode === 'decrease') {
            $('#pef-discount-type-select-wrap').show();
            $('#pef-discount-input-label').text('By how much:');
            $('#pef-discount-percentage-input')
                .attr('placeholder', 'e.g. 10 or 2.50')
                .attr('title', 'Enter % or $ amount to decrease price');
            $('#pef-price-pct-btn').removeClass('btn-primary btn-success btn-info').addClass('btn-warning')
                .html('<i class="fas fa-minus-circle"></i> Decrease');
            $('#pef-apply-discount-btn').html('<i class="fas fa-check"></i> Apply Decrease');
        } else if (mode === 'increase') {
            $('#pef-discount-type-select-wrap').show();
            $('#pef-discount-input-label').text('By how much:');
            $('#pef-discount-percentage-input')
                .attr('placeholder', 'e.g. 10 or 2.50')
                .attr('title', 'Enter % or $ amount to increase price');
            $('#pef-price-pct-btn').removeClass('btn-primary btn-warning btn-info').addClass('btn-success')
                .html('<i class="fas fa-plus-circle"></i> Increase');
            $('#pef-apply-discount-btn').html('<i class="fas fa-check"></i> Apply Increase');
        } else if (mode === 'same') {
            $('#pef-discount-type-select-wrap').hide();
            $('#pef-discount-input-label').text('Same Price ($):');
            $('#pef-discount-percentage-input')
                .attr('placeholder', 'Enter price (e.g. 19.99)')
                .attr('title', 'This single price will be applied to every selected row');
            $('#pef-price-pct-btn').removeClass('btn-primary btn-warning btn-success').addClass('btn-info')
                .html('<i class="fas fa-equals"></i> Same Price');
            $('#pef-apply-discount-btn').html('<i class="fas fa-check"></i> Apply Same Price');
        }
    }

    function applyPricePctMode() {
        const rawInput = $('#pef-discount-percentage-input').val();
        const inputValue = parseFloat(String(rawInput).replace(',', '.'));

        if (rawInput === '' || rawInput == null) {
            toast(samePriceModeActive ? 'Please enter a price' : 'Please enter a value (% or $)', 'error');
            return;
        }
        if (isNaN(inputValue) || inputValue < 0) {
            toast('Please enter a valid positive number', 'error');
            return;
        }

        const discountType = $('#pef-discount-type-select').val();
        if (!samePriceModeActive && discountType === 'percentage' && inputValue > 100) {
            toast('Percentage cannot exceed 100', 'error');
            return;
        }

        const selected = collectSelectedRows();
        if (!selected.length) {
            toast('Please select at least one row', 'error');
            return;
        }
        if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
            toast('Please activate Decrease, Increase, or Same Price mode first', 'error');
            return;
        }

        const mode = samePriceModeActive ? 'same' : (increaseModeActive ? 'increase' : 'decrease');
        const rowsToProcess = [];

        selected.forEach(function(item) {
            const originalPrice = Number(item.d.price) || 0;
            // Same Price applies even when live price is empty
            if (mode !== 'same' && !(originalPrice > 0)) return;

            let newPrice;
            if (mode === 'same') {
                newPrice = Math.max(0.01, inputValue);
            } else if (discountType === 'percentage') {
                const decimal = inputValue / 100;
                newPrice = mode === 'decrease'
                    ? originalPrice * (1 - decimal)
                    : originalPrice * (1 + decimal);
            } else {
                newPrice = mode === 'decrease'
                    ? Math.max(0.01, originalPrice - inputValue)
                    : originalPrice + inputValue;
            }

            newPrice = roundToRetailPrice(newPrice);
            if (mode !== 'same' && newPrice.toFixed(2) === originalPrice.toFixed(2)) {
                newPrice = roundToRetailPrice49(newPrice);
            }
            const newPriceNum = parseFloat(newPrice.toFixed(2));
            if (!(newPriceNum > 0)) return;
            rowsToProcess.push({ row: item.row, d: item.d, sprice: newPriceNum });
        });

        if (!rowsToProcess.length) {
            toast(mode === 'same'
                ? 'No selected rows to update'
                : 'No selected rows have Price > 0', 'error');
            return;
        }

        const actionText = mode === 'same' ? 'Same Price' : (mode === 'increase' ? 'Increase' : 'Decrease');
        const detail = mode === 'same'
            ? ('$' + inputValue.toFixed(2))
            : (discountType === 'percentage' ? (inputValue + '%') : ('$' + inputValue.toFixed(2)));
        if (!confirm(actionText + ' ' + detail + ' for ' + rowsToProcess.length + ' row(s)?')) {
            return;
        }

        bulkSaveTargetSprice(
            rowsToProcess,
            actionText + ' applied',
            $('#pef-apply-discount-btn'),
            '<i class="fas fa-check"></i> Apply ' + actionText
        );
    }

    /** Channel take-home decimal (0–1). Amazon always 0.80 — same as amazon-tabulator-view. */
    function rowMargin(d) {
        const mp = String(d.marketplace || d.channel_key || '').toLowerCase();
        if (mp === 'amazon') return 0.80;
        let m = Number(d.margin || 0);
        if (m > 1) m = m / 100;
        if (m > 0 && m <= 1) return m;
        if (mp.indexOf('temu') !== -1) return 0.87;
        if (mp.indexOf('doba') !== -1) return 0.90;
        if (mp.indexOf('ebay') !== -1) return 0.85;
        return 0.80;
    }

    /** Channel Ads% (TACOS) for NPFT / Snroi / Snpft. */
    function rowAdsPct(d) {
        const a = Number(d.ads_pct);
        return isFinite(a) && a >= 0 ? a : 0;
    }

    function collectSelectedRows() {
        const out = [];
        if (!table) return out;
        table.getRows('active').forEach(function(row) {
            const d = row.getData();
            if (selectedIds.has(d.id)) out.push({ row: row, d: d });
        });
        return out;
    }

    /**
     * Target ROI% — same as /amazon-tabulator-view, per-row channel margin:
     *   sprice = (LP × (1 + ROI%/100) + Ship) / margin
     */
    function applyTargetRoi() {
        const rawInput = $('#pef-target-roi-input').val();
        const targetRoiPct = parseFloat(String(rawInput).replace(',', '.'));
        if (rawInput === '' || rawInput == null) {
            toast('Please enter a Target ROI%', 'error');
            return;
        }
        if (!isFinite(targetRoiPct)) {
            toast('Target ROI% must be a number', 'error');
            return;
        }
        const selected = collectSelectedRows();
        if (!selected.length) {
            toast('Please select at least one row', 'error');
            return;
        }

        const rowsToProcess = [];
        const roiMultiplier = 1 + (targetRoiPct / 100);
        selected.forEach(function(item) {
            const lp = Number(item.d.lp || 0);
            if (!(lp > 0)) return;
            const ship = Number(item.d.ship || 0);
            const margin = rowMargin(item.d);
            const sprice = round2((lp * roiMultiplier + ship) / margin);
            if (!(sprice > 0)) return;
            rowsToProcess.push({ row: item.row, d: item.d, sprice: sprice, margin: margin });
        });

        if (!rowsToProcess.length) {
            toast('No selected rows have LP > 0', 'error');
            return;
        }
        if (!confirm('Compute & save SPrice for ' + rowsToProcess.length + ' row(s) so Sroi = ' + targetRoiPct + '%?\n\n(sprice = (LP × (1 + Target/100) + Ship) / channel margin)')) {
            return;
        }
        bulkSaveTargetSprice(rowsToProcess, 'Sroi = ' + targetRoiPct + '%', $('#pef-apply-target-roi-btn'));
    }

    /**
     * Target GPFT% — same as /amazon-tabulator-view, per-row channel margin:
     *   sprice = (LP + Ship) / (margin − GPFT%/100)
     */
    function applyTargetGpft() {
        const rawInput = $('#pef-target-gpft-input').val();
        const targetGpftPct = parseFloat(String(rawInput).replace(',', '.'));
        if (rawInput === '' || rawInput == null) {
            toast('Please enter a Target GPFT%', 'error');
            return;
        }
        if (!isFinite(targetGpftPct)) {
            toast('Target GPFT% must be a number', 'error');
            return;
        }

        const selected = collectSelectedRows();
        if (!selected.length) {
            toast('Please select at least one row', 'error');
            return;
        }

        const rowsToProcess = [];
        let skippedHigh = 0;
        selected.forEach(function(item) {
            const lp = Number(item.d.lp || 0);
            if (!(lp > 0)) return;
            const ship = Number(item.d.ship || 0);
            const margin = rowMargin(item.d);
            const denom = margin - (targetGpftPct / 100);
            if (!(denom > 0)) {
                skippedHigh++;
                return;
            }
            const sprice = round2((lp + ship) / denom);
            if (!(sprice > 0)) return;
            rowsToProcess.push({ row: item.row, d: item.d, sprice: sprice, margin: margin });
        });

        if (!rowsToProcess.length) {
            toast(skippedHigh
                ? ('Target GPFT% ' + targetGpftPct + '% is too high for selected channel margins')
                : 'No selected rows have LP > 0', 'error');
            return;
        }
        const note = skippedHigh ? ('\n(' + skippedHigh + ' skipped — target ≥ channel margin)') : '';
        if (!confirm('Compute & save SPrice for ' + rowsToProcess.length + ' row(s) so Sgpft = ' + targetGpftPct + '%?' + note + '\n\n(sprice = (LP + Ship) / (margin − GPFT%/100))')) {
            return;
        }
        bulkSaveTargetSprice(rowsToProcess, 'Sgpft = ' + targetGpftPct + '%', $('#pef-apply-target-gpft-btn'));
    }

    function bulkSaveTargetSprice(rowsToProcess, label, $btn, restoreHtml) {
        let ok = 0, fail = 0;
        const total = rowsToProcess.length;
        const doneHtml = restoreHtml || '<i class="fas fa-calculator"></i>';
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        rowsToProcess.forEach(function(item) {
            const local = recalcSuggestedForRow(Object.assign({}, item.d, { sprice: item.sprice, margin: item.margin }));
            const patch = {
                sprice: item.sprice,
                sroi: local.sroi,
                sgpft: local.sgpft,
                snroi: local.snroi,
                snpft: local.snpft,
                success: 'saving',
            };
            item.row.update(patch);
            const cacheIdx = pulledRows.findIndex(function(r) { return r.id === item.d.id; });
            if (cacheIdx >= 0) pulledRows[cacheIdx] = Object.assign({}, pulledRows[cacheIdx], patch);

            $.ajax({
                url: '/cvr-master-save-suggested-data',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf },
                data: {
                    sku: item.d.sku,
                    marketplace: item.d.marketplace,
                    sprice: item.sprice,
                    sgpft: local.sgpft || 0,
                    // Amazon/eBay store SROI as NET; SGROI as gross
                    sroi: local.snroi != null ? local.snroi : (local.sroi || 0),
                    sgroi: local.sroi || 0,
                    spft: local.snpft || 0,
                },
            }).done(async function() {
                ok++;
                item.row.update({ success: 'saved' });
                await refreshRowFromBreakdown(item.row);
                item.row.update({ success: 'saved' });
            }).fail(function() {
                fail++;
                item.row.update({ success: 'error' });
            }).always(function() {
                if (ok + fail === total) {
                    $btn.prop('disabled', false).html(doneHtml);
                    // Keep row checkboxes selected after apply
                    if (table) table.redraw(true);
                    updatePushBtn();
                    syncSelectAllCheckbox();
                    toast(fail
                        ? ('Saved ' + ok + ' of ' + total + ' (' + fail + ' failed)')
                        : ('SPrice saved for ' + ok + ' row(s) — ' + label),
                        fail ? 'error' : 'success');
                }
            });
        });
    }

    function isMissingPriceInv(d) {
        const inv = Number(d.inv || 0);
        const price = Number(d.price || 0);
        return inv > 0 && !(price > 0);
    }

    /** Price Null = missing / empty / not a number / ≤ 0 */
    function isPriceNull(d) {
        const p = d.price;
        if (p === null || p === undefined || p === '') return true;
        const n = Number(p);
        return !isFinite(n) || n <= 0;
    }

    /** Price Exist = price > 0 */
    function isPriceExist(d) {
        const n = Number(d.price);
        return isFinite(n) && n > 0;
    }

    /** Dil% bands — same as /price-increase: Red &lt;25, Green 25–50, Pink 50%+ */
    function dilColorBand(dil) {
        const n = Number(dil);
        if (!isFinite(n)) return null;
        if (n < 25) return 'red';
        if (n < 50) return 'green';
        return 'pink';
    }

    function matchesDilFilter(d, color) {
        if (!color || color === 'all') return true;
        return dilColorBand(d.dil) === color;
    }

    /** GPFT% slabs — same as /price-increase: &lt;20 · 20–30 · 30–40 · &gt;40 */
    function matchesGpftFilter(d, range) {
        if (!range || range === 'all') return true;
        const n = Number(d.gpft);
        if (!isFinite(n)) return false;
        if (range === 'lt-20') return n < 20;
        if (range === '20-30') return n >= 20 && n < 30;
        if (range === '30-40') return n >= 30 && n <= 40;
        if (range === 'gt-40') return n > 40;
        return true;
    }

    /** GROI% slabs: &lt;40 · 40–60 · 60–80 · 80–100 · 100%+ */
    function matchesGroiFilter(d, range) {
        if (!range || range === 'all') return true;
        const n = Number(d.groi);
        if (!isFinite(n)) return false;
        if (range === 'lt-40') return n < 40;
        if (range === '40-60') return n >= 40 && n < 60;
        if (range === '60-80') return n >= 60 && n < 80;
        if (range === '80-100') return n >= 80 && n < 100;
        if (range === 'gte-100') return n >= 100;
        return true;
    }

    function buildAjaxUrl(channelKey) {
        // Loads from pre-calculated table (instant). Optional channel filter for subset.
        let listed = $('#pef-listed-only').is(':checked') ? '1' : '0';
        let url = '/pricing-errors-fix-data-json?listed_only=' + listed;
        if (channelKey) url += '&channel=' + encodeURIComponent(channelKey);
        return url;
    }

    function updateMissingBadge() {
        let n = 0;
        pulledRows.forEach(function(d) {
            if (isMissingPriceInv(d)) n++;
        });
        $('#pef-missing-badge').text('Missing: ' + n);
    }

    /** Unique SKUs with GROI% &lt; 40 */
    function updateSkuGroiBadge() {
        const skus = new Set();
        pulledRows.forEach(function(d) {
            if (!d.sku) return;
            if (matchesGroiFilter(d, 'lt-40')) skus.add(String(d.sku));
        });
        $('#pef-sku-groi-badge').text('SKU: ' + skus.size);
    }

    /** Snapshot current column sort so filters can re-apply across ALL pages. */
    function snapshotTableSort() {
        if (!table) return [];
        try {
            return (table.getSorters() || []).map(function(s) {
                try {
                    const col = s.column;
                    const field = col && typeof col.getField === 'function' ? col.getField() : null;
                    return field ? { column: field, dir: s.dir } : null;
                } catch (e) {
                    return null;
                }
            }).filter(Boolean);
        } catch (e) {
            return [];
        }
    }

    /**
     * After filter/sort: keep sort on full dataset + jump to page 1
     * (same approach as amazon-tabulator-view — Tabulator can drop sort when filters shrink data).
     */
    function finalizeFilterSort(sortSnapshot) {
        if (!table) return;
        queueMicrotask(function() {
            if (!table) return;
            const snap = (sortSnapshot && sortSnapshot.length)
                ? sortSnapshot
                : [{ column: 'groi', dir: 'asc' }];
            try {
                table.setSort(snap);
            } catch (e) { /* ignore */ }
            try {
                table.setPage(1);
            } catch (e) { /* ignore */ }
            try {
                const maxP = table.getPageMax();
                const cur = table.getPage();
                if (typeof maxP === 'number' && maxP >= 1 && cur > maxP) {
                    table.setPage(maxP);
                }
            } catch (e) { /* ignore */ }
            $('#pef-rows-badge').text('Rows: ' + table.getDataCount('active'));
            updatePushBtn();
        });
    }

    /** Client-side filters on 100% of pulled rows (all pages) — never hits the server */
    function applyStatusFilter() {
        if (!table) return;
        const sortSnapshot = snapshotTableSort();
        const invFilter = $('#pef-inv-filter').val() || 'all';
        const dilColor = $('#pef-dil-filter').val() || 'all';
        const gpftRange = $('#pef-gpft-filter').val() || 'all';
        const groiRange = $('#pef-groi-filter').val() || 'all';
        const priceFilter = $('#pef-price-filter').val() || 'all';
        const channelKey = $('#pef-channel-filter').val() || '';

        table.clearFilter(true);
        applyTextFilters(false);

        if (channelKey) {
            table.addFilter(function(data) {
                return String(data.pull_key || data.channel_key || data.marketplace || '') === channelKey;
            });
        }
        // INV filter (separate): All | = 0 | > 0
        if (invFilter === 'gt_0') {
            table.addFilter(function(data) { return Number(data.inv || 0) > 0; });
        } else if (invFilter === 'eq_0') {
            table.addFilter(function(data) { return Number(data.inv || 0) === 0; });
        }
        // Price filter (separate)
        if (priceFilter === 'null') {
            table.addFilter(function(data) { return isPriceNull(data); });
        } else if (priceFilter === 'exist') {
            table.addFilter(function(data) { return isPriceExist(data); });
        }
        if (dilColor !== 'all') {
            table.addFilter(function(data) { return matchesDilFilter(data, dilColor); });
        }
        if (gpftRange !== 'all') {
            table.addFilter(function(data) { return matchesGpftFilter(data, gpftRange); });
        }
        if (groiRange !== 'all') {
            table.addFilter(function(data) { return matchesGroiFilter(data, groiRange); });
        }
        finalizeFilterSort(sortSnapshot);
    }

    /**
     * Channel-wise PFT/ROI — same formulas as amazon-tabulator-view:
     *   SGPFT = ((sprice × margin − ship − lp) / sprice) × 100
     *   Sroi  = SGROI = ((sprice × margin − ship − lp) / lp) × 100
     *   Snpft = SGPFT − Ads%
     *   Snroi = (gross$ − sprice×Ads%/100) / lp × 100
     */
    function recalcSuggestedForRow(d) {
        const sprice = Number(d.sprice || 0);
        const lp = Number(d.lp || 0);
        const ship = Number(d.ship || 0);
        const margin = rowMargin(d);
        const adsPct = rowAdsPct(d);
        if (!(sprice > 0) || !(margin > 0) || !(lp > 0)) {
            return { sroi: null, sgpft: null, snroi: null, snpft: null };
        }
        const gross = (sprice * margin) - lp - ship;
        const sgpft = round2((gross / sprice) * 100);
        const sroi = round2((gross / lp) * 100); // SGROI (gross)
        const snpft = round2(sgpft - adsPct); // SPFT
        const adSpend = sprice * (adsPct / 100);
        const snroi = round2(((gross - adSpend) / lp) * 100); // SROI (net)
        return { sroi: sroi, sgpft: sgpft, snroi: snroi, snpft: snpft };
    }

    /** Live Price columns with same channel formulas */
    function recalcLiveForRow(d) {
        const price = Number(d.price || 0);
        const lp = Number(d.lp || 0);
        const ship = Number(d.ship || 0);
        const margin = rowMargin(d);
        const adsPct = rowAdsPct(d);
        if (!(price > 0) || !(margin > 0) || !(lp > 0)) {
            return { groi: null, gpft: null, nroi: null, npft: null };
        }
        const gross = (price * margin) - lp - ship;
        const gpft = round2((gross / price) * 100);
        const groi = round2((gross / lp) * 100);
        const npft = round2(gpft - adsPct);
        const adSpend = price * (adsPct / 100);
        const nroi = round2(((gross - adSpend) / lp) * 100);
        return { groi: groi, gpft: gpft, nroi: nroi, npft: npft };
    }

    function round2(n) {
        return Math.round(Number(n) * 100) / 100;
    }

    function normMp(name) {
        return String(name || '').toLowerCase().replace(/\s+/g, '').replace(/[^a-z0-9]/g, '');
    }

    function marketplaceMatches(breakdownMp, rowMp) {
        const a = normMp(breakdownMp);
        const b = normMp(rowMp);
        if (!a || !b) return false;
        if (a === b) return true;
        const aliases = {
            ebay: ['ebay1', 'ebay'],
            ebay1: ['ebay', 'ebay1'],
            ebay2: ['ebaytwo', 'ebay2'],
            ebaytwo: ['ebay2', 'ebaytwo'],
            ebay3: ['ebaythree', 'ebay3'],
            ebaythree: ['ebay3', 'ebaythree'],
            macy: ['macys', 'macy'],
            macys: ['macy', 'macys'],
            sb2c: ['shopifyb2c', 'shopify', 'sb2c'],
            shopifyb2c: ['sb2c', 'shopify', 'shopifyb2c'],
            sb2b: ['shopifyb2b', 'sb2b'],
            shopifyb2b: ['sb2b', 'shopifyb2b'],
            ppower: ['purchasingpower', 'purchase', 'ppower'],
            purchasingpower: ['ppower', 'purchase', 'purchasingpower'],
            bestbuy: ['bestbuyusa', 'bestbuy'],
            bestbuyusa: ['bestbuy', 'bestbuyusa'],
            tiktok2: ['tiktokshop2', 'tiktok2'],
        };
        const list = aliases[b] || [];
        return list.indexOf(a) !== -1 || (aliases[a] || []).indexOf(b) !== -1;
    }

    /**
     * Row-wise refresh for ONE sku × channel — uses /cvr-master-breakdown
     * (same channel logic) and patches only that table row. Never reloads all channels.
     */
    async function refreshRowFromBreakdown(row) {
        const d = row.getData();
        if (!d.sku) return;
        try {
            const items = await $.ajax({
                url: '/cvr-master-breakdown',
                method: 'GET',
                data: { sku: d.sku },
                dataType: 'json',
                timeout: 60000,
            });
            const list = Array.isArray(items) ? items : (items.data || []);
            const match = list.find(function(it) {
                return marketplaceMatches(it.marketplace, d.marketplace)
                    || marketplaceMatches(it.marketplace, d.channel);
            });
            if (!match) return;

            const sprice = match.sprice != null && Number(match.sprice) > 0
                ? round2(Number(match.sprice)) : d.sprice;
            const price = match.price != null && Number(match.price) > 0
                ? round2(Number(match.price)) : d.price;
            const patch = {
                price: price,
                sprice: sprice,
                lp: match.lp != null ? Number(match.lp) : d.lp,
                ship: match.ship != null ? Number(match.ship) : d.ship,
                // Prefer breakdown margin; Amazon breakdown sends 0.80
                margin: match.margin != null ? Number(match.margin) : d.margin,
                ads_pct: d.ads_pct,
            };
            // Breakdown stores SROI as net and often omits SGROI — recompute channel-wise
            const live = recalcLiveForRow(Object.assign({}, d, patch));
            const sug = recalcSuggestedForRow(Object.assign({}, d, patch, {
                sprice: sprice > 0 ? sprice : (price > 0 ? price : 0),
            }));
            Object.assign(patch, live, sug);
            // If breakdown has ads on the item, keep NPFT = GPFT − ad when present
            if (match.ad != null && isFinite(Number(match.ad))) {
                patch.ads_pct = Number(match.ad);
                const live2 = recalcLiveForRow(Object.assign({}, d, patch));
                const sug2 = recalcSuggestedForRow(Object.assign({}, d, patch, {
                    sprice: sprice > 0 ? sprice : (price > 0 ? price : 0),
                }));
                Object.assign(patch, live2, sug2);
            }
            row.update(patch);
            // Keep pulledRows cache in sync for this id only
            const idx = pulledRows.findIndex(function(r) { return r.id === d.id; });
            if (idx >= 0) pulledRows[idx] = Object.assign({}, pulledRows[idx], patch);
        } catch (e) {
            // silent — row already has local save/push status
        }
    }

    function setDilFilterUI(color) {
        const map = {
            all: { label: 'Dil%', dot: 'default' },
            red: { label: 'Dil% Red', dot: 'red' },
            green: { label: 'Dil% Green', dot: 'green' },
            pink: { label: 'Dil% Pink', dot: 'pink' },
        };
        const m = map[color] || map.all;
        $('#pef-dil-filter').val(color);
        $('#pef-dil-filter-label').text(m.label);
        $('#pef-dil-filter-dot').attr('class', 'status-circle ' + m.dot);
        $('.pef-dil-filter-item').removeClass('active');
        $('.pef-dil-filter-item[data-color="' + color + '"]').addClass('active');
    }

    function setGpftFilterUI(range) {
        const map = {
            all: { label: 'GPFT%', dot: 'default' },
            'lt-20': { label: 'GPFT <20%', dot: 'red' },
            '20-30': { label: 'GPFT 20–30%', dot: 'yellow' },
            '30-40': { label: 'GPFT 30–40%', dot: 'green' },
            'gt-40': { label: 'GPFT >40%', dot: 'magenta-bg' },
        };
        const m = map[range] || map.all;
        $('#pef-gpft-filter').val(range);
        $('#pef-gpft-filter-label').text(m.label);
        $('#pef-gpft-filter-dot').attr('class', 'status-circle ' + m.dot);
        $('.pef-gpft-filter-item').removeClass('active');
        $('.pef-gpft-filter-item[data-range="' + range + '"]').addClass('active');
    }

    function setGroiFilterUI(range) {
        const map = {
            all: { label: 'GROI%', dot: 'default' },
            'lt-40': { label: 'GROI <40%', dot: 'red' },
            '40-60': { label: 'GROI 40–60%', dot: 'yellow' },
            '60-80': { label: 'GROI 60–80%', dot: 'orange' },
            '80-100': { label: 'GROI 80–100%', dot: 'green' },
            'gte-100': { label: 'GROI 100%+', dot: 'pink' },
        };
        const m = map[range] || map.all;
        $('#pef-groi-filter').val(range);
        $('#pef-groi-filter-label').text(m.label);
        $('#pef-groi-filter-dot').attr('class', 'status-circle ' + m.dot);
        $('.pef-groi-filter-item').removeClass('active');
        $('.pef-groi-filter-item[data-range="' + range + '"]').addClass('active');
    }

    function pctFormatter(cell) { return fmtPct(cell); }

    /** All channel keys for Pull Data (full pull). Channel dropdown filters client-side only. */
    function allChannelKeys() {
        const keys = [];
        $('#pef-channel-filter option').each(function() {
            const v = $(this).val();
            if (v) keys.push(v);
        });
        return keys;
    }

    function initTable(rows) {
        if (table) {
            table.replaceData(rows);
            return;
        }

        table = new Tabulator('#pef-table', {
            data: rows || [],
            layout: 'fitDataFill',
            height: '100%',
            // Local modes = sort/filter run on 100% of pulled rows, then paginate
            pagination: true,
            paginationMode: 'local',
            sortMode: 'local',
            filterMode: 'local',
            paginationSize: 100,
            paginationSizeSelector: [100, 200, 500, 1000, true], // true = ALL
            paginationCounter: 'rows',
            // Vertical virtual OK; horizontal virtual + frozen = header/body mismatch
            renderHorizontal: 'basic',
            renderVertical: 'virtual',
            initialSort: [{ column: 'groi', dir: 'asc' }],
            columnDefaults: {
                resizable: false,
                minWidth: 40,
                headerSort: false,
                headerFilterLiveFilter: true,
            },
            columns: [
                {
                    title: '',
                    field: '_selected',
                    headerSort: false,
                    width: 36,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    titleFormatter: function() {
                        return '<input type="checkbox" id="pef-select-all" title="Select current page only">';
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const checked = selectedIds.has(d.id) ? 'checked' : '';
                        return `<input type="checkbox" class="pef-row-cb" data-id="${String(d.id).replace(/"/g, '&quot;')}" ${checked}>`;
                    },
                },
                {
                    title: 'Parent',
                    field: 'parent',
                    width: 100,
                    hozAlign: 'left',
                    vertAlign: 'middle',
                    headerFilter: 'input',
                    headerFilterPlaceholder: 'Parent…',
                    headerFilterFunc: 'like',
                },
                {
                    title: 'SKU',
                    field: 'sku',
                    width: 160,
                    hozAlign: 'left',
                    vertAlign: 'middle',
                    headerFilter: 'input',
                    headerFilterPlaceholder: 'SKU…',
                    headerFilterFunc: 'like',
                    cssClass: 'pef-sku-cell',
                },
                {
                    title: 'Channel',
                    field: 'channel',
                    width: 100,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerFilter: 'list',
                    headerFilterParams: { valuesLookup: true, clearable: true },
                    formatter: function(cell) {
                        const v = cell.getValue() || '';
                        return v ? `<span class="pef-channel-badge">${v}</span>` : '';
                    },
                },
                {
                    title: 'INV', field: 'inv', width: 56, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number', sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable',
                },
                {
                    title: 'OVL30', field: 'ov_l30', width: 58, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number', sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable',
                },
                {
                    title: 'Dil%',
                    field: 'dil',
                    width: 56,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: true,
                    sorter: 'number',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable',
                    headerTooltip: 'Dil% — Red <25%, Green 25–50%, Pink 50%+',
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '') return '';
                        const n = Math.round(Number(v));
                        if (!isFinite(n)) return '';
                        const band = dilColorBand(n);
                        const cls = band ? ('pef-dil-' + band) : '';
                        return cls
                            ? `<span class="${cls}">${n}%</span>`
                            : (n + '%');
                    },
                },
                {
                    title: 'Price',
                    field: 'price',
                    width: 72,
                    hozAlign: 'right',
                    vertAlign: 'middle',
                    headerSort: true,
                    sorter: 'number',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable',
                    headerTooltip: 'Channel listing price',
                    formatter: fmtMoney,
                    accessorDownload: function(value) { return value; },
                },
                {
                    title: 'GROI%', field: 'groi', width: 64, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number', sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable', formatter: pctFormatter,
                },
                {
                    title: 'NROI%', field: 'nroi', width: 64, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number', sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable', formatter: pctFormatter,
                },
                {
                    title: 'GPFT%', field: 'gpft', width: 64, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number', sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable', formatter: pctFormatter,
                },
                {
                    title: 'NPFT%', field: 'npft', width: 64, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number', sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable', formatter: pctFormatter,
                },
                {
                    title: 'SPrice',
                    field: 'sprice',
                    width: 78,
                    hozAlign: 'right',
                    vertAlign: 'middle',
                    headerSort: true,
                    sorter: 'number',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable',
                    editor: 'number',
                    editorParams: { step: 0.01, min: 0 },
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '') {
                            return '<span class="text-muted">—</span>';
                        }
                        return '$' + Number(v).toFixed(2);
                    },
                    cellEdited: function(cell) {
                        saveSprice(cell.getRow());
                    },
                },
                {
                    title: 'SGROI%', field: 'sroi', width: 66, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number', sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable', formatter: pctFormatter,
                },
                {
                    title: 'SGPFT%', field: 'sgpft', width: 66, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number', sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable', formatter: pctFormatter,
                },
                {
                    title: 'Snroi', field: 'snroi', width: 62, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number', sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable', formatter: pctFormatter,
                },
                {
                    title: 'Snpft', field: 'snpft', width: 62, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number', sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable', formatter: pctFormatter,
                },
                {
                    title: 'Push',
                    field: 'push',
                    width: 52,
                    headerSort: false,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const disabled = !(d.sprice > 0);
                        return `<button type="button" class="btn btn-sm btn-primary pef-push-one" ${disabled ? 'disabled' : ''}
                            data-id="${String(d.id).replace(/"/g, '&quot;')}" title="Push SPRICE to ${d.channel}">
                            <i class="fas fa-upload"></i></button>`;
                    },
                },
                {
                    title: 'OK',
                    field: 'success',
                    width: 44,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    formatter: function(cell) {
                        return successDot(cell.getValue());
                    },
                },
            ],
        });

        table.on('dataFiltered', function() {
            $('#pef-rows-badge').text('Rows: ' + table.getDataCount('active'));
            updatePushBtn();
            syncSelectAllCheckbox();
        });
        // Header sort uses full dataset; jump to page 1 so top of sorted list is visible
        table.on('dataSorted', function() {
            try {
                if (table.getPage() !== 1) table.setPage(1);
            } catch (e) { /* ignore */ }
            $('#pef-rows-badge').text('Rows: ' + table.getDataCount('active'));
            syncSelectAllCheckbox();
        });
        table.on('pageLoaded', function() {
            syncSelectAllCheckbox();
        });
    }

    /** Rows on the current pagination page only (filtered + sorted active set). */
    function pefCurrentPageData() {
        if (!table) return [];
        let page = 1;
        let pageSize = 100;
        try {
            page = table.getPage() || 1;
            pageSize = table.getPageSize();
        } catch (e) { /* ignore */ }
        if (page < 1) page = 1;
        // Tabulator "ALL" can return true / boolean — treat as full active set
        if (pageSize === true || pageSize === false || pageSize == null || pageSize <= 0) {
            return table.getData('active') || [];
        }
        const activeData = table.getData('active') || [];
        const start = (page - 1) * pageSize;
        return activeData.slice(start, start + pageSize);
    }

    function syncSelectAllCheckbox() {
        const $all = $('#pef-select-all');
        if (!$all.length || !table) return;
        const pageData = pefCurrentPageData();
        if (!pageData.length) {
            $all.prop({ checked: false, indeterminate: false });
            return;
        }
        let selected = 0;
        pageData.forEach(function(d) {
            if (d && d.id && selectedIds.has(d.id)) selected++;
        });
        $all.prop('checked', selected === pageData.length);
        $all.prop('indeterminate', selected > 0 && selected < pageData.length);
    }

    /**
     * Instant load from pricing_errors_fix_calculated_data (marketplace-wise cache).
     * No channel fan-out — command `pricing-errors:calculate-data` fills the table.
     */
    async function loadFromCache() {
        if (pullInProgress) return;
        pullInProgress = true;
        $('#pef-reload-btn').prop('disabled', true);
        selectedIds.clear();
        updatePushBtn();
        if (!table) initTable([]);

        try {
            const resp = await $.ajax({
                url: buildAjaxUrl(''), // all marketplaces from cache table
                method: 'GET',
                dataType: 'json',
                timeout: 60000,
            });
            const rows = Array.isArray(resp) ? resp : (resp.data || []);
            const meta = resp.meta || {};
            pulledRows = rows;
            dataLoaded = true;

            $('#pef-price-filter').val('all');
            $('#pef-inv-filter').val('all');

            if (table) {
                await table.replaceData(pulledRows);
                table.setSort([{ column: 'groi', dir: 'asc' }]);
            }
            // Defer non-critical UI so table paints first
            requestAnimationFrame(function() {
                rebuildParentDatalist();
                updateMissingBadge();
                updateSkuGroiBadge();
                applyStatusFilter();
                updatePushBtn();
            });

            const src = meta.source || 'cache';
            $('#pef-channels-badge').text('Channels: ' + allChannelKeys().length);
            $('#pef-rows-badge').text('Rows: ' + rows.length);

            if (meta.errors && Object.keys(meta.errors).length) {
                const errKeys = Object.keys(meta.errors);
                $('#pef-error-badge').show().text('Channel errors: ' + errKeys.length)
                    .attr('title', errKeys.map(function(k) { return k + ': ' + meta.errors[k]; }).join('\n'));
            } else {
                $('#pef-error-badge').hide();
            }

            if (!rows.length && src !== 'cache') {
                toast('Cache empty — run: php artisan pricing-errors:calculate-data --force', 'error');
            } else if (!rows.length) {
                toast('No rows in cache (or Listed filter hid all)', 'error');
            }
        } catch (xhr) {
            toast('Load failed: ' + ((xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'error'), 'error');
        }

        $('#pef-loading').hide();
        $('#pef-reload-btn').prop('disabled', false);
        pullInProgress = false;
    }

    function saveSprice(row) {
        const d = row.getData();
        const sprice = parseFloat(d.sprice);
        if (!d.sku || !d.marketplace || !(sprice > 0)) return;

        // Local suggested % update immediately (row-wise)
        const local = recalcSuggestedForRow(Object.assign({}, d, { sprice: sprice }));
        const localPatch = {
            sprice: sprice,
            sroi: local.sroi,
            sgpft: local.sgpft,
            snroi: local.snroi,
            snpft: local.snpft,
            success: 'saving',
        };
        row.update(localPatch);
        const cacheIdx = pulledRows.findIndex(function(r) { return r.id === d.id; });
        if (cacheIdx >= 0) pulledRows[cacheIdx] = Object.assign({}, pulledRows[cacheIdx], localPatch);

        $.ajax({
            url: '/cvr-master-save-suggested-data',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
            data: {
                sku: d.sku,
                marketplace: d.marketplace,
                sprice: sprice,
                sgpft: local.sgpft || 0,
                // Amazon/eBay: SROI = net; SGROI = gross
                sroi: local.snroi != null ? local.snroi : (local.sroi || 0),
                sgroi: local.sroi || 0,
                spft: local.snpft || 0,
            },
        }).done(async function() {
            row.update({ success: 'saved' });
            // Row-wise server refresh for this SKU × channel only
            await refreshRowFromBreakdown(row);
            row.update({ success: 'saved' });
            toast('SPRICE saved for ' + d.sku + ' / ' + d.channel, 'success');
            updatePushBtn();
        }).fail(function(xhr) {
            toast('Save failed: ' + (xhr.responseJSON?.error || xhr.responseJSON?.message || 'error'), 'error');
            row.update({ success: 'error' });
        });
    }

    function pushOne(d) {
        return $.ajax({
            url: '/cvr-master-push-price',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
            data: {
                sku: d.sku,
                price: d.sprice,
                marketplace: d.marketplace,
            },
        });
    }

    async function pushSelected() {
        if (!table) return;
        const items = [];
        table.getRows('active').forEach(row => {
            const d = row.getData();
            if (selectedIds.has(d.id) && d.sprice > 0) items.push({ row, d });
        });
        if (!items.length) return;
        if (!confirm('Push SPRICE for ' + items.length + ' row(s) using each channel\'s push logic?')) return;

        const $btn = $('#pef-bulk-push-btn').prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin"></i> Pushing...');
        let ok = 0, fail = 0;

        for (const item of items) {
            try {
                const resp = await pushOne(item.d);
                const body = resp;
                if (body && body.success === false) {
                    fail++;
                    item.row.update({ success: 'error' });
                } else {
                    ok++;
                    item.row.update({ success: 'pushed' });
                    // Row-wise only — never re-pull the whole channel
                    await refreshRowFromBreakdown(item.row);
                    item.row.update({ success: 'pushed' });
                }
            } catch (e) {
                fail++;
                item.row.update({ success: 'error' });
            }
        }

        $btn.html('<i class="fas fa-upload"></i> Push (<span id="pef-push-count">0</span>)');
        updatePushBtn();
        toast(`Push done: ${ok} ok, ${fail} failed`, fail ? 'error' : 'success');
    }

    $(document).on('change', '#pef-select-all', function() {
        const checked = $(this).is(':checked');
        if (!table) return;
        $(this).prop('indeterminate', false);
        // Select current pagination page only (not all filtered pages)
        pefCurrentPageData().forEach(function(d) {
            if (!d || !d.id) return;
            if (checked) selectedIds.add(d.id);
            else selectedIds.delete(d.id);
        });
        table.redraw(true);
        updatePushBtn();
        syncSelectAllCheckbox();
    });

    $(document).on('change', '.pef-row-cb', function() {
        const id = $(this).data('id');
        if ($(this).is(':checked')) selectedIds.add(id);
        else selectedIds.delete(id);
        updatePushBtn();
        syncSelectAllCheckbox();
    });

    $(document).on('click', '.pef-push-one', async function() {
        const id = $(this).data('id');
        if (!table) return;
        const row = table.getRows().find(r => r.getData().id === id);
        if (!row) return;
        const d = row.getData();
        if (!(d.sprice > 0)) return;
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        try {
            const resp = await pushOne(d);
            if (resp && resp.success === false) {
                row.update({ success: 'error' });
                toast(resp.message || 'Push failed', 'error');
            } else {
                row.update({ success: 'pushed' });
                await refreshRowFromBreakdown(row);
                row.update({ success: 'pushed' });
                toast('Pushed ' + d.sku + ' → ' + d.channel, 'success');
            }
        } catch (e) {
            row.update({ success: 'error' });
            toast('Push failed', 'error');
        }
        table.redraw(true);
        updatePushBtn();
    });

    function rebuildParentDatalist() {
        if (!table) return;
        const seen = {};
        table.getData().forEach(function(d) {
            const p = String(d.parent || '').trim();
            if (p) seen[p] = true;
        });
        const parents = Object.keys(seen).sort(function(a, b) {
            return a.localeCompare(b, undefined, { sensitivity: 'base' });
        });
        const $dl = $('#pef-parent-datalist').empty();
        parents.forEach(function(p) {
            $dl.append($('<option>').attr('value', p));
        });
        $('#pef-parent-match-hint').text(parents.length ? (parents.length + ' parents') : '');
    }

    function applyTextFilters(updateCounts) {
        if (!table) return;
        const parentQ = ($('#pef-parent-search').val() || '').trim();
        const skuQ = ($('#pef-sku-search').val() || '').trim();
        // Drive column header filters so Parent / SKU search stay in sync (all pages)
        table.setHeaderFilterValue('parent', parentQ);
        table.setHeaderFilterValue('sku', skuQ);
        if (parentQ) {
            const activeParents = {};
            (table.getData('active') || []).forEach(function(d) {
                const p = String(d.parent || '').trim();
                if (p) activeParents[p] = true;
            });
            const n = Object.keys(activeParents).length;
            $('#pef-parent-match-hint').text(n + ' parent match' + (n === 1 ? '' : 'es'));
        } else {
            const total = $('#pef-parent-datalist option').length;
            $('#pef-parent-match-hint').text(total ? (total + ' parents') : '');
        }
        if (updateCounts !== false) {
            try { table.setPage(1); } catch (e) { /* ignore */ }
            $('#pef-rows-badge').text('Rows: ' + table.getDataCount('active'));
            updatePushBtn();
        }
    }

    let searchTimer = null;
    function scheduleTextFilters() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            // Re-apply status + text so they stack correctly
            applyStatusFilter();
        }, 200);
    }

    $('#pef-bulk-push-btn').on('click', pushSelected);
    $('#pef-reload-btn').on('click', loadFromCache);

    // Target ROI% / GPFT% — same as /amazon-tabulator-view
    $('#pef-apply-target-roi-btn').on('click', applyTargetRoi);
    $('#pef-apply-target-gpft-btn').on('click', applyTargetGpft);
    $('#pef-target-roi-input').on('keypress', function(e) {
        if (e.which === 13) $('#pef-apply-target-roi-btn').click();
    });
    $('#pef-target-gpft-input').on('keypress', function(e) {
        if (e.which === 13) $('#pef-apply-target-gpft-btn').click();
    });

    // % Prc Mode — same as /amazon-tabulator-view
    $(document).on('click', '#pef-price-pct-dropdown a[data-mode]', function(e) {
        e.preventDefault();
        setPricePctMode($(this).data('mode'));
    });
    $('#pef-apply-discount-btn').on('click', applyPricePctMode);
    $('#pef-discount-percentage-input').on('keypress', function(e) {
        if (e.which === 13) $('#pef-apply-discount-btn').click();
    });
    $('#pef-discount-type-select').on('change', function() {
        const type = $(this).val();
        const $input = $('#pef-discount-percentage-input');
        if (type === 'percentage') {
            $input.attr('placeholder', 'Enter percentage').attr('max', '100');
        } else {
            $input.attr('placeholder', 'Enter value').removeAttr('max');
        }
    });

    // Filters are client-side on cached rows
    $('#pef-channel-filter').on('change', function() {
        if (!dataLoaded) return;
        applyStatusFilter();
    });
    $('#pef-listed-only').on('change', function() {
        // Reloads from cache with listed_only flag (still instant)
        loadFromCache();
    });
    $('#pef-inv-filter, #pef-price-filter').on('change', function() {
        if (!dataLoaded) return;
        applyStatusFilter();
    });

    $(document).on('click', '.pef-dil-filter-item', function(e) {
        e.preventDefault();
        setDilFilterUI($(this).data('color') || 'all');
        if (dataLoaded) applyStatusFilter();
    });

    $(document).on('click', '.pef-gpft-filter-item', function(e) {
        e.preventDefault();
        setGpftFilterUI($(this).data('range') || 'all');
        if (dataLoaded) applyStatusFilter();
    });

    $(document).on('click', '.pef-groi-filter-item', function(e) {
        e.preventDefault();
        setGroiFilterUI($(this).data('range') || 'all');
        if (dataLoaded) applyStatusFilter();
    });
    $('#pef-parent-search, #pef-sku-search').on('input change', scheduleTextFilters);
    $('#pef-parent-search').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            applyStatusFilter();
            $(this).select();
        }
    });
    $('#pef-clear-search-btn').on('click', function() {
        $('#pef-parent-search, #pef-sku-search').val('');
        applyStatusFilter();
        $('#pef-parent-search').focus();
    });
    $('#pef-missing-badge').on('click', function() {
        if (!dataLoaded) return;
        $('#pef-inv-filter').val('gt_0');
        $('#pef-price-filter').val('null');
        applyStatusFilter();
    }).css('cursor', 'pointer');

    $('#pef-sku-groi-badge').on('click', function() {
        if (!dataLoaded) return;
        setGroiFilterUI('lt-40');
        applyStatusFilter();
    }).css('cursor', 'pointer');

    // Data already in HTML from DB — paint immediately (no Ajax load)
    initTable(pulledRows);
    if (dataLoaded) {
        $('#pef-channels-badge').text('Channels: ' + allChannelKeys().length);
        $('#pef-rows-badge').text('Rows: ' + pulledRows.length);
        requestAnimationFrame(function() {
            rebuildParentDatalist();
            updateMissingBadge();
            updateSkuGroiBadge();
            applyStatusFilter();
            updatePushBtn();
        });
    } else {
        // Fallback only when table empty (command not run yet)
        loadFromCache();
    }
})();
</script>
@endsection
