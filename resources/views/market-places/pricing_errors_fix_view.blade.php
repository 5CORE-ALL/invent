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
        .pef-metric-yellow { color: #ffc107; font-weight: 700; }
        .pef-metric-green { color: #28a745; font-weight: 600; }
        .pef-metric-hot { color: #e83e8c; font-weight: 700; background: transparent; padding: 0; border-radius: 0; }
        .pef-metric-pink-dil { color: #4e0dab; font-weight: 700; }
        .pef-success-dot {
            display: inline-block; width: 12px; height: 12px; border-radius: 50%;
            border: 1px solid #94a3b8; background: #e2e8f0;
        }
        .pef-success-dot.ok { background: #22c55e; border-color: #15803d; }
        .pef-success-dot.err { background: #ef4444; border-color: #b91c1c; }
        .pef-success-dot.pending { background: #f59e0b; border-color: #b45309; }
        .pef-success-dot.saved { background: #3b82f6; border-color: #1d4ed8; }
        #pef-push-progress {
            display: none;
            margin: 0;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 8px 12px;
        }
        #pef-push-progress.active { display: block; }
        #pef-push-progress .pef-push-bar {
            height: 8px; border-radius: 999px; background: #e2e8f0; overflow: hidden;
        }
        #pef-push-progress .pef-push-bar > span {
            display: block; height: 100%; background: #0d6efd; width: 0%; transition: width .25s ease;
        }
        #pef-push-fail-list {
            max-height: 120px; overflow: auto; font-size: 12px; margin-top: 6px;
        }
        #pef-push-fail-list .pef-fail-item { color: #b91c1c; padding: 2px 0; }
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
        /* Keep Push full-color even on selected / already-pushed rows */
        .pef-push-one:not(:disabled) {
            opacity: 1 !important;
            pointer-events: auto !important;
        }
        .tabulator-row.tabulator-selected .pef-push-one:not(:disabled),
        .tabulator-row .pef-push-one.pef-push-done:not(:disabled) {
            opacity: 1 !important;
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
            color: #fff !important;
        }
        .pef-push-one.pef-push-done:not(:disabled) {
            background-color: #198754 !important;
            border-color: #198754 !important;
        }
        /* CPN $ / CPN % / DSC — same UX as /price-increase OV L30 */
        .pef-promo-cell {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
        }
        .pef-promo-cell.has-val { color: #0f172a; }
        .tabulator-row .tabulator-cell[tabulator-field="cpn_dollar"],
        .tabulator-row .tabulator-cell[tabulator-field="cpn_pct"],
        .tabulator-row .tabulator-cell[tabulator-field="prmt_pct"],
        .tabulator-row .tabulator-cell[tabulator-field="dsc"],
        .tabulator-row .tabulator-cell[tabulator-field="appr"] {
            padding: 2px 4px !important;
        }
        .pef-std-lmp-alert {
            color: #dc3545;
            font-size: 11px;
            line-height: 1;
            cursor: help;
        }
        #pef-dil-prmt-table .pef-dil-prmt-input,
        #pef-cvr-cpn-table .pef-cvr-cpn-input {
            max-width: 90px;
            margin-left: auto;
            text-align: right;
            font-weight: 600;
        }
        /* STD PRC — same teal header as /price-increase OV L30 */
        .tabulator .tabulator-header .tabulator-col[tabulator-field="standard_price"] {
            background: #20c997 !important;
            color: #000 !important;
        }
        .tabulator .tabulator-header .tabulator-col[tabulator-field="standard_price"] .tabulator-col-title {
            color: #000 !important;
            font-weight: 700;
        }
        .pef-std-prc-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-weight: 600;
        }
        .pef-std-prc-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        #pef-dil-prmt-table tbody tr td:first-child,
        #pef-cvr-cpn-table tbody tr td:first-child {
            font-weight: 600;
            color: #334155;
        }
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
        /* Channel multi-select dropdown */
        #pef-channel-filter-btn {
            min-width: 130px;
            max-width: 200px;
            justify-content: space-between;
        }
        #pef-channel-filter-label {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 150px;
            display: inline-block;
            text-align: left;
        }
        .pef-channel-menu {
            max-height: 320px;
            overflow-y: auto;
            min-width: 200px;
            padding: 6px 0;
        }
        .pef-channel-menu .dropdown-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            padding: 4px 12px;
            cursor: pointer;
        }
        .pef-channel-menu .dropdown-item:active,
        .pef-channel-menu .dropdown-item:hover {
            background: #f1f5f9;
            color: inherit;
        }
        .pef-channel-menu .form-check-input {
            margin: 0;
            flex-shrink: 0;
            cursor: pointer;
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

                        <label class="pef-lbl">Channel</label>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light dropdown-toggle border" type="button"
                                id="pef-channel-filter-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                aria-expanded="false" title="Filter channels (multi-select)">
                                <span id="pef-channel-filter-label">All channels</span>
                            </button>
                            <ul class="dropdown-menu pef-channel-menu" aria-labelledby="pef-channel-filter-btn">
                                <li>
                                    <label class="dropdown-item mb-0">
                                        <input type="checkbox" class="form-check-input" id="pef-channel-all" checked>
                                        <span>All channels</span>
                                    </label>
                                </li>
                                <li><hr class="dropdown-divider my-1"></li>
                                @foreach(($channels ?? []) as $ch)
                                    @php
                                        $chKey = is_array($ch) ? ($ch['key'] ?? '') : $ch;
                                        $chLabel = is_array($ch) ? ($ch['label'] ?? $ch['key'] ?? '') : $ch;
                                    @endphp
                                    @if($chKey !== '')
                                        <li>
                                            <label class="dropdown-item mb-0">
                                                <input type="checkbox" class="form-check-input pef-channel-cb"
                                                    value="{{ $chKey }}" data-label="{{ $chLabel }}" checked>
                                                <span>{{ $chLabel }}</span>
                                            </label>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>

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
                            title="Unique SKUs with GROI &lt; 60% — click to filter">SKU: 0</span>

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

                        <button type="button" class="btn btn-sm btn-outline-primary" id="pef-dil-vs-prmt-btn"
                            title="Dil% slabs vs PRMT% rules — edit and apply as PRMT %">
                            <i class="fas fa-sliders-h"></i> Dil vs PRMT
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="pef-cvr-vs-cpn-btn"
                            title="CVR% slabs vs CPN% rules — edit and apply as CPN %">
                            <i class="fas fa-percentage"></i> CVR vs CPN
                        </button>

                        <div class="dropdown">
                            <button class="btn btn-sm btn-light dropdown-toggle border" type="button"
                                id="pef-gpft-filter-btn" data-bs-toggle="dropdown" aria-expanded="false"
                                title="Filter by GPFT% slab">
                                <span class="status-circle default" id="pef-gpft-filter-dot"></span>
                                <span id="pef-gpft-filter-label">GPFT%</span>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="pef-gpft-filter-btn">
                                <li><a class="dropdown-item pef-gpft-filter-item active" href="#" data-range="all"><span class="status-circle default"></span> All GPFT</a></li>
                                <li><a class="dropdown-item pef-gpft-filter-item" href="#" data-range="lt-20"><span class="status-circle red"></span> ≤ 20%</a></li>
                                <li><a class="dropdown-item pef-gpft-filter-item" href="#" data-range="20-30"><span class="status-circle yellow"></span> 20–30%</a></li>
                                <li><a class="dropdown-item pef-gpft-filter-item" href="#" data-range="30-43"><span class="status-circle green"></span> 30–43%</a></li>
                                <li><a class="dropdown-item pef-gpft-filter-item" href="#" data-range="gt-43"><span class="status-circle magenta-bg"></span> ≥ 43%</a></li>
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
                                <li><a class="dropdown-item pef-groi-filter-item" href="#" data-range="lt-60"><span class="status-circle red"></span> &lt; 60%</a></li>
                                <li><a class="dropdown-item pef-groi-filter-item" href="#" data-range="60-90"><span class="status-circle yellow"></span> 60–90%</a></li>
                                <li><a class="dropdown-item pef-groi-filter-item" href="#" data-range="90-150"><span class="status-circle green"></span> 90–150%</a></li>
                                <li><a class="dropdown-item pef-groi-filter-item" href="#" data-range="gte-150"><span class="status-circle pink"></span> ≥ 150%</a></li>
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
                        <button type="button" class="btn btn-sm btn-success" id="pef-std-to-sprice-btn" disabled
                            title="Set SPRICE from STD PRC on selected rows. Doba −25%. TopDawg/Faire/SB2B = (STD × marketplace%) − Ship. Purchase = (STD × 1.15) − Ship. Others = STD.">
                            <i class="fas fa-arrow-right"></i> Add STD price to Sprice (<span id="pef-std-to-sprice-count">0</span>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="pef-clear-sprice-btn" disabled
                            title="Clear SPRICE on selected rows (same as /price-increase Clear SPRICE)">
                            <i class="fas fa-eraser"></i> Clear SPRICE (<span id="pef-clear-sprice-count">0</span>)
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="pef-bulk-push-btn" disabled
                            title="Queue SPRICE push in background (auto-retry until done)">
                            <i class="fas fa-upload"></i> Push (<span id="pef-push-count">0</span>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="pef-push-cancel-btn"
                            style="display:none;" title="Cancel background push">
                            <i class="fas fa-stop"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0 position-relative">
                <div id="pef-push-progress">
                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                        <div class="small fw-semibold" id="pef-push-progress-msg">Ready</div>
                        <div class="small text-muted" id="pef-push-progress-counts"></div>
                    </div>
                    <div class="pef-push-bar mt-1"><span id="pef-push-progress-bar"></span></div>
                    <div id="pef-push-fail-list"></div>
                </div>
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

    {{-- CVR vs CPN: CVR% slabs with editable CPN% --}}
    <div class="modal fade" id="pefCvrVsCpnModal" tabindex="-1" aria-labelledby="pefCvrVsCpnModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="pefCvrVsCpnModalLabel">
                        <i class="fas fa-percentage me-1"></i> CVR vs CPN
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Map CVR% slabs to CPN%. First-time defaults: <strong>&gt; 7% → 0</strong> up to
                        <strong>CVR 0% → 10</strong>. Save stores rules; Apply fills <strong>CPN %</strong> from each row’s CVR%.
                        If <strong>INV = 0</strong>, CPN% is forced to <strong>0</strong>.
                        Auto-applies to <strong>eBay1</strong> coupons every night at <strong>00:30 IST</strong>
                        (after Dil/PRMT @ midnight — whether or not CVR changed).
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="pef-cvr-cpn-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:55%;">CVR%</th>
                                    <th style="width:45%;" class="text-end">CPN %</th>
                                </tr>
                            </thead>
                            <tbody id="pef-cvr-cpn-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="pef-cvr-cpn-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pef-cvr-cpn-reset-btn"
                        title="Reset CPN% to first-time defaults (0–10)">
                        Reset defaults
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="pef-cvr-cpn-save-btn">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="pef-cvr-cpn-apply-selected-btn"
                        title="Apply CPN% from CVR slabs on checked rows">
                        Apply to selected
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="pef-cvr-cpn-apply-visible-btn"
                        title="Apply CPN% from CVR slabs on all visible rows">
                        Apply to visible
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Dil vs PRMT: 11 Dil% slabs with editable PRMT% --}}
    <div class="modal fade" id="pefDilVsPrmtModal" tabindex="-1" aria-labelledby="pefDilVsPrmtModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="pefDilVsPrmtModalLabel">
                        <i class="fas fa-sliders-h me-1"></i> Dil vs PRMT
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Map Dil% slabs to PRMT%. First-time defaults: <strong>&gt; 100% → 0</strong> up to
                        <strong>0–10% → 10</strong>. Save stores rules; Apply fills the <strong>PRMT %</strong> column from each row’s Dil%.
                        If <strong>INV = 0</strong>, PRMT% is forced to <strong>0</strong>.
                        Auto-applies to <strong>eBay1</strong> promotions every night at <strong>midnight IST</strong> (whether or not Dil/INV changed).
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="pef-dil-prmt-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:55%;">Dil%</th>
                                    <th style="width:45%;" class="text-end">PRMT %</th>
                                </tr>
                            </thead>
                            <tbody id="pef-dil-prmt-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="pef-dil-prmt-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pef-dil-prmt-reset-btn"
                        title="Reset PRMT% to first-time defaults (0–10)">
                        Reset defaults
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="pef-dil-prmt-save-btn">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="pef-dil-prmt-apply-selected-btn"
                        title="Apply Dil→PRMT % on checked rows">
                        Apply to selected
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="pef-dil-prmt-apply-visible-btn"
                        title="Apply Dil→PRMT % on all visible rows">
                        Apply to visible
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
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
    const PEF_DIL_PRMT_DEFAULTS = @json($dil_prmt_rules ?? []);
    const PEF_CVR_CPN_DEFAULTS = @json($cvr_cpn_rules ?? []);
    /** Full dataset — filters work client-side; Reload refreshes via Ajax */
    let pulledRows = Array.isArray(PEF_INITIAL_ROWS) ? PEF_INITIAL_ROWS.slice() : [];
    let dataLoaded = pulledRows.length > 0;
    let pullInProgress = false;
    // % Prc Mode flags — same as /amazon-tabulator-view
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let samePriceModeActive = false;
    let pefPushInFlight = false;
    let pefPushPollTimer = null;
    /** After eBay 1/2/3 SPRICE push: pull live Price (+ SPRICE) for only those rows after 1 min. */
    const PEF_EBAY_PULL_DELAY_MS = 60000;
    let pefEbayPullQueue = {}; // row_id → {row_id, sku, marketplace}
    let pefEbayPullTimer = null;

    function toast(msg, type) {
        const bg = type === 'error' ? 'text-bg-danger' : (type === 'success' ? 'text-bg-success' : 'text-bg-dark');
        const id = 't' + Date.now();
        const html = `<div id="${id}" class="toast align-items-center ${bg} border-0 show mb-2" role="alert">
            <div class="d-flex"><div class="toast-body">${msg}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
        $('.toast-container').append(html);
        setTimeout(() => $('#' + id).fadeOut(200, function() { $(this).remove(); }), 4000);
    }

    /** PEF field → metric kind (sroi column is SGROI% / gross). */
    const PEF_METRIC_KIND = {
        groi: 'groi', nroi: 'nroi', gpft: 'gpft', npft: 'npft',
        sroi: 'groi', sgpft: 'gpft', snroi: 'nroi', snpft: 'npft',
    };

    /** Map MetricPctColors band → PEF CSS class (by field: GROI/NROI/GPFT/NPFT/S*). */
    function metricClass(v, field) {
        if (v === null || v === undefined || v === '') return '';
        if (!window.MetricPctColors) return '';
        const key = String(field || '').toLowerCase();
        const kind = PEF_METRIC_KIND[key] || MetricPctColors.kindFromField(field);
        if (!kind) return '';
        const band = MetricPctColors.bandFor(kind, v);
        if (!band) return '';
        if (band === 'pink') return 'pef-metric-hot';
        if (band === 'pink-dil') return 'pef-metric-pink-dil';
        return 'pef-metric-' + band;
    }

    function fmtPct(cell) {
        const v = cell.getValue();
        if (v === null || v === undefined || v === '') return '';
        const n = Number(v);
        if (isNaN(n)) return '';
        const field = (typeof cell.getField === 'function') ? cell.getField() : '';
        const cls = metricClass(n, field);
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

    function successDot(status, errorMsg) {
        const s = String(status || '').toLowerCase();
        let cls = '';
        let tip = status || '—';
        if (['pushed', 'success', 'ok', 'applied', 'done'].includes(s)) cls = 'ok';
        else if (['error', 'failed', 'fail'].includes(s)) {
            cls = 'err';
            if (errorMsg) tip = String(errorMsg);
        }
        else if (['pushing', 'pending', 'queued', 'retrying', 'pulling'].includes(s)) cls = 'pending';
        else if (['saved'].includes(s)) cls = 'saved';
        return `<span class="pef-success-dot ${cls}" title="${String(tip).replace(/"/g, '&quot;')}"></span>`;
    }

    /** Channels with API price push via /cvr-master-push-price (same as /price-increase). */
    const PEF_PUSHABLE_CHANNELS = [
        'amazon', 'doba',
        'ebay', 'ebay1', 'ebay2', 'ebaytwo', 'ebay3', 'ebaythree',
        'sb2c', 'shopify', 'shopifyb2c', 'sb2b', 'shopifyb2b',
        'bestbuy', 'bestbuyusa', 'macy', 'macys',
        'ppower', 'purchasingpower', 'purchase',
        'reverb', 'fba', 'topdawg', 'temu', 'temu2',
        'tiktok', 'tiktok1', 'tiktok2',
    ];

    function isPushableChannel(d) {
        const mp = pushMarketplaceKey(d);
        return PEF_PUSHABLE_CHANNELS.indexOf(mp) !== -1;
    }

    function updatePushBtn() {
        let n = 0;
        let clearN = 0;
        let stdN = 0;
        let selected = 0;
        if (table) {
            table.getRows('active').forEach(row => {
                const d = row.getData();
                if (selectedIds.has(d.id)) {
                    selected++;
                    if (Number(d.standard_price) > 0) stdN++;
                    if (Number(d.sprice) > 0) {
                        clearN++;
                        if (isPushableChannel(d)) n++;
                    }
                }
            });
        }
        $('#pef-push-count').text(n);
        $('#pef-bulk-push-btn').prop('disabled', pefPushInFlight || n === 0);
        $('#pef-clear-sprice-count').text(clearN);
        $('#pef-clear-sprice-btn').prop('disabled', pefPushInFlight || clearN === 0);
        $('#pef-std-to-sprice-count').text(stdN);
        $('#pef-std-to-sprice-btn').prop('disabled', pefPushInFlight || stdN === 0);
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
        // Doba 0.95 — same as /price-increase & /doba-tabulator
        if (mp.indexOf('doba') !== -1) return 0.95;
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

    /** Product-master ship for STD→SPRICE rules (fallback to any sibling row ship for same SKU). */
    function pefShipForStdApply(d) {
        let ship = Number(d.ship || 0);
        if (isFinite(ship) && ship > 0) return ship;
        if (!table || !d.sku) return 0;
        const want = String(d.sku).toUpperCase();
        const rows = table.getRows('active');
        for (let i = 0; i < rows.length; i++) {
            const rd = rows[i].getData();
            if (String(rd.sku || '').toUpperCase() !== want) continue;
            const sn = Number(rd.ship || 0);
            if (isFinite(sn) && sn > 0) return sn;
        }
        return 0;
    }

    /**
     * STD PRC → channel SPRICE (same rules as /price-increase):
     * Doba −25%; TopDawg/Faire/SB2B = (STD × marketplace%) − Ship; Purchase = (STD × 1.15) − Ship; else STD.
     */
    function spriceFromStdPrice(d, stdPrice) {
        const base = Math.max(0.01, round2(Number(stdPrice)));
        const mp = String(d.marketplace || d.channel_key || '').toLowerCase().replace(/\s+/g, '');
        const ship = pefShipForStdApply(d);
        if (mp === 'doba') {
            return Math.max(0.01, round2(base * 0.75));
        }
        if (mp === 'ppower' || mp === 'purchasingpower' || mp === 'purchase') {
            return Math.max(0.01, round2((base * 1.15) - ship));
        }
        if (mp === 'topdawg' || mp === 'topdog' || mp === 'faire'
            || mp === 'sb2b' || mp === 'shopifyb2b' || mp === 'shopify_b2b') {
            let rate = Number(d.margin || 0);
            if (rate > 1) rate = rate / 100;
            if (!(rate > 0 && rate <= 1)) rate = 0.80;
            return Math.max(0.01, round2((base * rate) - ship));
        }
        return base;
    }

    /** Selected rows: copy STD PRC into SPRICE (channel rules) and save. */
    function applyStdPriceToSprice() {
        const selected = collectSelectedRows();
        if (!selected.length) {
            toast('Select row(s) first', 'error');
            return;
        }
        const rowsToProcess = [];
        let skippedNoStd = 0;
        selected.forEach(function(item) {
            const std = Number(item.d.standard_price || 0);
            if (!(std > 0)) {
                skippedNoStd++;
                return;
            }
            const sprice = spriceFromStdPrice(item.d, std);
            if (!(sprice > 0)) return;
            rowsToProcess.push({
                row: item.row,
                d: item.d,
                sprice: sprice,
                margin: rowMargin(item.d),
            });
        });
        if (!rowsToProcess.length) {
            toast(skippedNoStd
                ? 'No selected rows have STD PRC > 0'
                : 'Could not compute SPRICE from STD PRC', 'error');
            return;
        }
        const sampleStd = Number(rowsToProcess[0].d.standard_price || 0);
        if (!confirm(
            'Add STD price to Sprice for ' + rowsToProcess.length + ' row(s)?'
            + (sampleStd > 0 ? ('\n\nExample STD: $' + sampleStd.toFixed(2)) : '')
            + '\nDoba −25%; TopDawg/Faire/SB2B = (STD × marketplace%) − Ship; Purchase = (STD × 1.15) − Ship'
            + (skippedNoStd ? ('\n(' + skippedNoStd + ' skipped — no STD PRC)') : '')
        )) {
            return;
        }
        bulkSaveTargetSprice(
            rowsToProcess,
            'STD → SPRICE',
            $('#pef-std-to-sprice-btn'),
            '<i class="fas fa-arrow-right"></i> Add STD price to Sprice (<span id="pef-std-to-sprice-count">' + rowsToProcess.length + '</span>)'
        );
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
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                data: {
                    sku: item.d.sku,
                    marketplace: item.d.marketplace,
                    sprice: item.sprice,
                    sgpft: local.sgpft || 0,
                    // Amazon/eBay store SROI as NET; SGROI as gross
                    sroi: local.snroi != null ? local.snroi : (local.sroi || 0),
                    sgroi: local.sroi || 0,
                    spft: local.snpft || 0,
                    _token: csrf,
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

    /** GPFT% slabs: ≤20 red · 20–30 yellow · 30–43 green · ≥43 pink-dil */
    function matchesGpftFilter(d, range) {
        if (!range || range === 'all') return true;
        const n = Number(d.gpft);
        if (!isFinite(n)) return false;
        if (range === 'lt-20') return n <= 20;
        if (range === '20-30') return n > 20 && n < 30;
        if (range === '30-43') return n >= 30 && n < 43;
        if (range === 'gt-43') return n >= 43;
        // legacy saved filters
        if (range === '30-40') return n >= 30 && n < 43;
        if (range === 'gt-40') return n >= 43;
        return true;
    }

    /** GROI% slabs: &lt;60 red · 60–90 yellow · 90–150 green · ≥150 pink */
    function matchesGroiFilter(d, range) {
        if (!range || range === 'all') return true;
        const n = Number(d.groi);
        if (!isFinite(n)) return false;
        if (range === 'lt-60') return n < 60;
        if (range === '60-90') return n >= 60 && n < 90;
        if (range === '90-150') return n >= 90 && n < 150;
        if (range === 'gte-150') return n >= 150;
        // legacy saved filters
        if (range === 'lt-40') return n < 60;
        if (range === '40-60') return n >= 40 && n < 60;
        if (range === '60-80') return n >= 60 && n < 90;
        if (range === '80-100') return n >= 90 && n < 150;
        if (range === 'gte-100') return n >= 150;
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

    /** Unique SKUs with GROI% &lt; 60 */
    function updateSkuGroiBadge() {
        const skus = new Set();
        pulledRows.forEach(function(d) {
            if (!d.sku) return;
            if (matchesGroiFilter(d, 'lt-60')) skus.add(String(d.sku));
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
        const channelKeys = getSelectedChannelKeys();

        table.clearFilter(true);
        applyTextFilters(false);

        // Multi-select channel filter
        const allKeys = allChannelKeys();
        if (channelKeys.length === 0) {
            table.addFilter(function() { return false; }); // none checked → no rows
        } else if (channelKeys.length < allKeys.length) {
            const allow = {};
            channelKeys.forEach(function(k) { allow[k] = true; });
            table.addFilter(function(data) {
                const k = String(data.pull_key || data.channel_key || data.marketplace || '');
                return !!allow[k];
            });
        }
        // all selected → no channel filter (show every channel)
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

    /** Parse SPRICE from number editor / formatted cell ($12.34). */
    function parseSpriceValue(v) {
        if (v === null || v === undefined || v === '') return NaN;
        if (typeof v === 'number') return v;
        return parseFloat(String(v).replace(/[$,\s]/g, ''));
    }

    // ==================== CPN $ / CPN % / DSC (same logic as /price-increase) ====================
    // UI-only discount tools → rewrite SPRICE → save via /cvr-master-save-suggested-data
    // (same marketplace data_view SPRICE columns as price-increase).
    function parsePefDollarAmount(raw) {
        const s = String(raw == null ? '' : raw).trim();
        if (!s || /%/.test(s)) return null;
        const num = parseFloat(s.replace(/[$,\s]/g, '').replace(',', '.'));
        if (!isFinite(num) || num === 0) return null;
        return { type: 'dollar', value: Math.abs(num) };
    }
    function parsePefPercentAmount(raw) {
        const s = String(raw == null ? '' : raw).trim();
        if (!s) return null;
        const num = parseFloat(s.replace(/[%$,\s]/g, '').replace(',', '.'));
        if (!isFinite(num) || num === 0) return null;
        return { type: 'percent', value: Math.abs(num) };
    }
    /** CPN % including 0 (0 = pause eBay1 coupon). */
    function parsePefCpnPercentAllowZero(raw) {
        const s = String(raw == null ? '' : raw).trim();
        if (s === '') return null;
        const num = parseFloat(s.replace(/[%$,\s]/g, '').replace(',', '.'));
        if (!isFinite(num) || num < 0) return null;
        return { type: 'percent', value: Math.abs(num) };
    }
    function isPefEbay1Row(d) {
        const mp = String(d && (d.marketplace || d.channel_key || d.pull_key) || '')
            .toLowerCase().replace(/\s+/g, '');
        return mp === 'ebay1' || mp === 'ebay' || mp === 'ebayone';
    }
    /**
     * Sync CPN % to eBay1 Marketing coupon API (0 = pause).
     * @returns {Promise<{ok:boolean,message?:string}>}
     */
    function syncEbay1CouponApi(sku, percent) {
        return $.ajax({
            url: '/pricing-errors-fix-ebay1-coupon',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            data: { sku: sku, percent: percent, _token: csrf },
            dataType: 'json',
        }).then(function(res) {
            return { ok: !!(res && res.success), message: (res && res.message) || '' };
        }, function(xhr) {
            const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error))
                || 'eBay1 coupon API error';
            return { ok: false, message: msg };
        });
    }
    /** Sync PRMT % to eBay1 Marketing promotion API (0 = pause). */
    function syncEbay1PromotionApi(sku, percent) {
        return $.ajax({
            url: '/pricing-errors-fix-ebay1-promotion',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            data: { sku: sku, percent: percent, _token: csrf },
            dataType: 'json',
        }).then(function(res) {
            return { ok: !!(res && res.success), message: (res && res.message) || '' };
        }, function(xhr) {
            const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error))
                || 'eBay1 promotion API error';
            return { ok: false, message: msg };
        });
    }
    function applyPromoToSpriceBase(base, promo) {
        if (!(base > 0) || !promo) return null;
        const next = promo.type === 'percent'
            ? base * (1 - (promo.value / 100))
            : base - promo.value;
        return Math.max(0.01, round2(next));
    }
    function pefPromoFieldMeta(kind, mode) {
        if (kind === 'dsc') {
            return {
                label: 'DSC %',
                field: 'dsc',
                appliedKey: '_dsc_applied',
                parse: parsePefPercentAmount,
                err: 'Enter DSC % (e.g. 1)',
                skipPairSync: false,
            };
        }
        if (kind === 'prmt') {
            return {
                label: 'PRMT %',
                field: 'prmt_pct',
                appliedKey: '_prmt_pct_applied',
                parse: parsePefPercentAmount,
                err: 'Enter PRMT % (e.g. 10)',
                skipPairSync: true,
            };
        }
        if (mode === 'percent') {
            return {
                label: 'CPN %',
                field: 'cpn_pct',
                appliedKey: '_cpn_pct_applied',
                parse: parsePefPercentAmount,
                err: 'Enter CPN % (e.g. 10)',
                skipPairSync: true,
            };
        }
        return {
            label: 'CPN $',
            field: 'cpn_dollar',
            appliedKey: '_cpn_dollar_applied',
            parse: parsePefDollarAmount,
            err: 'Enter CPN $ (e.g. 1)',
            skipPairSync: true,
        };
    }
    function getPefDiscountBase(d, appliedKey, mode) {
        let base = Number(d.sprice) > 0 ? Number(d.sprice) : Number(d.price) || 0;
        const prev = Number(d[appliedKey] || 0) || 0;
        if (mode === 'dollar' && prev > 0) base = base + prev;
        else if (mode === 'percent' && prev > 0 && prev < 100) base = base / (1 - (prev / 100));
        return round2(base);
    }
    function fmtPefPromoCell(value, placeholder) {
        if (value === null || value === undefined || value === '') {
            return '<span class="pef-promo-cell">' + placeholder + '</span>';
        }
        return '<span class="pef-promo-cell has-val">' + String(value) + '</span>';
    }

    /** Dollar LMP difference amount = Price − LMP (only when Price > LMP). */
    function pefLmpDiffAmount(d) {
        const price = Number(d.price) || 0;
        const lmp = Number(d.lmp) || 0;
        if (!(price > 0) || !(lmp > 0)) return null;
        const amt = round2(price - lmp);
        return amt > 0 ? amt : null;
    }

    function pefLmpChanged(lockedLmp, currentLmp) {
        const a = Number(lockedLmp);
        const b = Number(currentLmp);
        if (!isFinite(a) || !(a > 0)) return true;
        if (!isFinite(b) || !(b > 0)) return true;
        return a.toFixed(2) !== b.toFixed(2);
    }

    function syncPefRowCache(id, patch) {
        const cacheIdx = pulledRows.findIndex(function(r) { return r.id === id; });
        if (cacheIdx >= 0) {
            pulledRows[cacheIdx] = Object.assign({}, pulledRows[cacheIdx], patch);
        }
    }

    /**
     * Clear Appr + DSC (and restore SPRICE if a DSC % was applied).
     */
    function clearPefApprDiscount(row, opts) {
        opts = opts || {};
        const d = row.getData();
        const prev = Number(d._dsc_applied) || 0;
        const patch = {
            appr: false,
            _appr_lmp: null,
            dsc: '',
            _dsc_applied: 0,
        };
        if (prev > 0 && prev < 100 && Number(d.sprice) > 0) {
            patch.sprice = round2(Number(d.sprice) / (1 - (prev / 100)));
        }
        row.update(patch);
        syncPefRowCache(d.id, patch);
        if (opts.save && patch.sprice != null && Number(patch.sprice) > 0) {
            saveSprice(row, { silent: true });
        }
        if (opts.redraw && table) table.redraw(true);
        return patch;
    }

    /**
     * Approve: convert Price−LMP into DSC % of SPRICE base and discount SPRICE.
     */
    function applyPefApprDiscount(row) {
        const d = row.getData();
        const amt = pefLmpDiffAmount(d);
        const lmp = Number(d.lmp);
        if (!(amt > 0) || !(lmp > 0)) {
            row.update({ appr: false, _appr_lmp: null });
            syncPefRowCache(d.id, { appr: false, _appr_lmp: null });
            toast('Appr needs Price > LMP for this channel', 'error');
            if (table) table.redraw(true);
            return false;
        }
        const base = getPefDiscountBase(d, '_dsc_applied', 'percent');
        if (!(base > 0)) {
            row.update({ appr: false, _appr_lmp: null });
            syncPefRowCache(d.id, { appr: false, _appr_lmp: null });
            toast('No SPRICE/Price to discount', 'error');
            if (table) table.redraw(true);
            return false;
        }
        let pct = round2((amt / base) * 100);
        if (!(pct > 0) || pct >= 100) {
            row.update({ appr: false, _appr_lmp: null });
            syncPefRowCache(d.id, { appr: false, _appr_lmp: null });
            toast('Appr DSC % out of range', 'error');
            if (table) table.redraw(true);
            return false;
        }
        const promo = { type: 'percent', value: pct };
        const newPrice = applyPromoToSpriceBase(base, promo);
        if (!(newPrice > 0)) {
            row.update({ appr: false, _appr_lmp: null });
            syncPefRowCache(d.id, { appr: false, _appr_lmp: null });
            toast('No SPRICE/Price to discount', 'error');
            if (table) table.redraw(true);
            return false;
        }
        const patch = {
            appr: true,
            _appr_lmp: round2(lmp),
            dsc: String(pct),
            _dsc_applied: pct,
            sprice: newPrice,
        };
        row.update(patch);
        syncPefRowCache(d.id, patch);
        saveSprice(row, { silent: true });
        updatePushBtn();
        if (table) table.redraw(true);
        return true;
    }
    /**
     * Apply CPN/DSC to one row (or all selected if the edited row is checked).
     * eBay1 CPN % → Sell Marketing coupon API (0 pauses). Other channels discount SPRICE.
     */
    async function applyPefPromoFromCell(cell, kind, mode) {
        const meta = pefPromoFieldMeta(kind, mode);
        const editedRow = cell.getRow();
        const raw = cell.getValue();
        // CPN % / PRMT % allow 0 so eBay1 can pause coupon/promotion
        const promo = ((kind === 'cpn' || kind === 'prmt') && mode === 'percent')
            ? parsePefCpnPercentAllowZero(raw)
            : meta.parse(raw);
        if (!promo) {
            if (String(raw == null ? '' : raw).trim() !== '') toast(meta.err, 'error');
            return;
        }

        let targets = [{ row: editedRow, d: editedRow.getData() }];
        // Checked rows: same idea as price-increase "checked channels"
        const selected = collectSelectedRows();
        const editedId = editedRow.getData().id;
        if (selected.length > 1 && selectedIds.has(editedId)) {
            targets = selected;
        }

        const displayVal = promo.type === 'percent'
            ? String(promo.value)
            : String(round2(promo.value));
        let ok = 0;
        let skipped = 0;
        let ebayOk = 0;
        let ebayFail = 0;
        let ebayPromoOk = 0;
        let ebayPromoFail = 0;

        for (let i = 0; i < targets.length; i++) {
            const item = targets[i];
            const d = item.row.getData();

            // eBay1: CPN % drives live coupon API (0 = pause). Do not rewrite SPRICE.
            if (kind === 'cpn' && mode === 'percent' && isPefEbay1Row(d)) {
                item.row.update({
                    cpn_pct: displayVal,
                    _cpn_pct_applied: promo.value,
                    coupon_status: 'syncing',
                });
                syncPefRowCache(d.id, {
                    cpn_pct: displayVal,
                    _cpn_pct_applied: promo.value,
                    coupon_status: 'syncing',
                });
                const api = await syncEbay1CouponApi(d.sku, promo.value);
                item.row.update({
                    cpn_pct: displayVal,
                    _cpn_pct_applied: promo.value,
                    coupon_status: api.ok ? (promo.value > 0 ? 'live' : 'paused') : 'error',
                    coupon_message: api.message || '',
                });
                syncPefRowCache(d.id, {
                    cpn_pct: displayVal,
                    _cpn_pct_applied: promo.value,
                    coupon_status: api.ok ? (promo.value > 0 ? 'live' : 'paused') : 'error',
                    coupon_message: api.message || '',
                });
                if (api.ok) ebayOk++;
                else {
                    ebayFail++;
                    toast('eBay1 ' + d.sku + ': ' + (api.message || 'coupon failed'), 'error');
                }
                continue;
            }

            // eBay1: PRMT % drives live promotion API (0 = pause). Do not rewrite SPRICE.
            if (kind === 'prmt' && isPefEbay1Row(d)) {
                item.row.update({
                    prmt_pct: displayVal,
                    _prmt_pct_applied: promo.value,
                    prmt_status: 'syncing',
                });
                syncPefRowCache(d.id, {
                    prmt_pct: displayVal,
                    _prmt_pct_applied: promo.value,
                    prmt_status: 'syncing',
                });
                const api = await syncEbay1PromotionApi(d.sku, promo.value);
                item.row.update({
                    prmt_pct: displayVal,
                    _prmt_pct_applied: promo.value,
                    prmt_status: api.ok ? (promo.value > 0 ? 'live' : 'paused') : 'error',
                    prmt_message: api.message || '',
                });
                syncPefRowCache(d.id, {
                    prmt_pct: displayVal,
                    _prmt_pct_applied: promo.value,
                    prmt_status: api.ok ? (promo.value > 0 ? 'live' : 'paused') : 'error',
                    prmt_message: api.message || '',
                });
                if (api.ok) ebayPromoOk++;
                else {
                    ebayPromoFail++;
                    toast('eBay1 ' + d.sku + ': ' + (api.message || 'promotion failed'), 'error');
                }
                continue;
            }

            if (kind === 'cpn' && mode === 'percent' && !(promo.value > 0)) {
                // Non-eBay: 0% = clear stamp only
                item.row.update({ cpn_pct: '0', _cpn_pct_applied: 0 });
                syncPefRowCache(d.id, { cpn_pct: '0', _cpn_pct_applied: 0 });
                skipped++;
                continue;
            }

            if (kind === 'prmt' && !(promo.value > 0)) {
                item.row.update({ prmt_pct: '0', _prmt_pct_applied: 0 });
                syncPefRowCache(d.id, { prmt_pct: '0', _prmt_pct_applied: 0 });
                skipped++;
                continue;
            }

            const base = getPefDiscountBase(d, meta.appliedKey, mode === 'percent' ? 'percent' : 'dollar');
            const newPrice = applyPromoToSpriceBase(base, promo);
            if (!(base > 0) || !(newPrice > 0)) {
                skipped++;
                continue;
            }
            const patch = {};
            patch[meta.field] = displayVal;
            patch[meta.appliedKey] = promo.value;
            patch.sprice = newPrice;
            // Manual DSC % edit clears Appr (approval is tied to LMP-gap %)
            if (kind === 'dsc') {
                patch.appr = false;
                patch._appr_lmp = null;
            }
            item.row.update(patch);
            syncPefRowCache(d.id, patch);
            saveSprice(item.row, {
                skipPairSync: meta.skipPairSync,
                silent: true,
            });
            ok++;
        }

        if (ebayOk || ebayFail) {
            toast(
                'eBay1 coupon: ' + ebayOk + ' ok'
                    + (ebayFail ? (', ' + ebayFail + ' failed') : '')
                    + (promo.value > 0 ? (' @ ' + promo.value + '%') : ' (paused)'),
                ebayFail && !ebayOk ? 'error' : 'success'
            );
        }
        if (ebayPromoOk || ebayPromoFail) {
            toast(
                'eBay1 promotion: ' + ebayPromoOk + ' ok'
                    + (ebayPromoFail ? (', ' + ebayPromoFail + ' failed') : '')
                    + (promo.value > 0 ? (' @ ' + promo.value + '%') : ' (paused)'),
                ebayPromoFail && !ebayPromoOk ? 'error' : 'success'
            );
        }
        if (ok > 0) {
            const modeLabel = promo.type === 'percent'
                ? (promo.value + '% less on SPRICE')
                : ('$' + round2(promo.value).toFixed(2) + ' less on SPRICE');
            toast(
                meta.label + ': ' + modeLabel + ' → ' + ok + ' row(s)'
                    + (skipped ? ('; skipped ' + skipped) : '')
                    + '. Use Push to go live.',
                'success'
            );
        } else if (!ebayOk && !ebayFail && !ebayPromoOk && !ebayPromoFail && skipped) {
            toast('No SPRICE/Price to discount', 'error');
        }
        updatePushBtn();
        if (table) table.redraw(true);
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
            tiktok: ['tiktok1', 'tiktokshop', 'tiktokshop1', 'tiktok'],
            tiktok1: ['tiktok', 'tiktokshop', 'tiktokshop1', 'tiktok1'],
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
                // Channel L30 sold qty from CVR breakdown
                l30: match.l30 != null && isFinite(Number(match.l30))
                    ? Number(match.l30)
                    : d.l30,
            };
            // Temu push IDs from breakdown (same as /price-increase)
            const gid = match.goods_id != null ? String(match.goods_id).trim() : '';
            const sid = match.sku_id != null ? String(match.sku_id).trim() : '';
            if (gid) patch.goods_id = gid;
            if (sid) patch.sku_id = sid;
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
            // Channel CVR from breakdown views/l30 when present
            if (match.views != null && isFinite(Number(match.views))) {
                patch.views = Number(match.views);
            }
            const viewsForCvr = Number(patch.views != null ? patch.views : d.views) || 0;
            const l30ForCvr = Number(patch.l30 != null ? patch.l30 : d.l30) || 0;
            if (viewsForCvr > 0) {
                patch.cvr = round2((l30ForCvr / viewsForCvr) * 100);
            } else if (d.cvr != null) {
                patch.cvr = d.cvr;
            }
            // LMP / LMP diff% for LMP vs DISC (breakdown may send lmp / lmp_price)
            let lmpKeep = d.lmp;
            if (match.lmp != null && isFinite(Number(match.lmp)) && Number(match.lmp) > 0) {
                lmpKeep = round2(Number(match.lmp));
            } else if (match.lmp_price != null && isFinite(Number(match.lmp_price)) && Number(match.lmp_price) > 0) {
                lmpKeep = round2(Number(match.lmp_price));
            }
            patch.lmp = lmpKeep != null && Number(lmpKeep) > 0 ? Number(lmpKeep) : d.lmp;
            const priceForLmp = Number(patch.price != null ? patch.price : d.price) || 0;
            const lmpForDiff = Number(patch.lmp) || 0;
            if (priceForLmp > 0 && lmpForDiff > 0) {
                patch.lmp_diff = round2(((priceForLmp - lmpForDiff) / lmpForDiff) * 100);
            } else if (d.lmp_diff != null) {
                patch.lmp_diff = d.lmp_diff;
            }
            // STD PRC from breakdown (amazon_data_view.STANDARD_PRICE — same as /price-increase)
            if (match.standard_price != null && isFinite(Number(match.standard_price)) && Number(match.standard_price) > 0) {
                patch.standard_price = round2(Number(match.standard_price));
            } else if (d.standard_price != null) {
                patch.standard_price = d.standard_price;
            }
            // Keep session CPN/PRMT/DSC/Appr UI fields (not in breakdown / DB)
            patch.cpn_dollar = d.cpn_dollar;
            patch.cpn_pct = d.cpn_pct;
            patch.prmt_pct = d.prmt_pct;
            patch.dsc = d.dsc;
            patch.appr = !!d.appr;
            patch._appr_lmp = d._appr_lmp;
            patch._cpn_dollar_applied = d._cpn_dollar_applied;
            patch._cpn_pct_applied = d._cpn_pct_applied;
            patch._prmt_pct_applied = d._prmt_pct_applied;
            patch._dsc_applied = d._dsc_applied;

            // If Appr was on and LMP revised ↑/↓ → clear DSC % + untick Appr + restore SPRICE
            let lmpRevisedClear = false;
            if (d.appr && pefLmpChanged(d._appr_lmp, patch.lmp)) {
                const prev = Number(d._dsc_applied) || 0;
                patch.appr = false;
                patch._appr_lmp = null;
                patch.dsc = '';
                patch._dsc_applied = 0;
                if (prev > 0 && prev < 100) {
                    const curSp = Number(patch.sprice != null ? patch.sprice : d.sprice) || 0;
                    if (curSp > 0) patch.sprice = round2(curSp / (1 - (prev / 100)));
                }
                lmpRevisedClear = true;
            }

            row.update(patch);
            // Keep pulledRows cache in sync for this id only
            const idx = pulledRows.findIndex(function(r) { return r.id === d.id; });
            if (idx >= 0) pulledRows[idx] = Object.assign({}, pulledRows[idx], patch);
            if (lmpRevisedClear && Number(patch.sprice) > 0) {
                saveSprice(row, { silent: true });
            }
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
            'lt-20': { label: 'GPFT ≤20%', dot: 'red' },
            '20-30': { label: 'GPFT 20–30%', dot: 'yellow' },
            '30-43': { label: 'GPFT 30–43%', dot: 'green' },
            'gt-43': { label: 'GPFT ≥43%', dot: 'magenta-bg' },
            // legacy
            '30-40': { label: 'GPFT 30–43%', dot: 'green' },
            'gt-40': { label: 'GPFT ≥43%', dot: 'magenta-bg' },
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
            'lt-60': { label: 'GROI <60%', dot: 'red' },
            '60-90': { label: 'GROI 60–90%', dot: 'yellow' },
            '90-150': { label: 'GROI 90–150%', dot: 'green' },
            'gte-150': { label: 'GROI ≥150%', dot: 'pink' },
            // legacy
            'lt-40': { label: 'GROI <60%', dot: 'red' },
            '40-60': { label: 'GROI <60%', dot: 'red' },
            '60-80': { label: 'GROI 60–90%', dot: 'yellow' },
            '80-100': { label: 'GROI 90–150%', dot: 'green' },
            'gte-100': { label: 'GROI ≥150%', dot: 'pink' },
        };
        const m = map[range] || map.all;
        $('#pef-groi-filter').val(range);
        $('#pef-groi-filter-label').text(m.label);
        $('#pef-groi-filter-dot').attr('class', 'status-circle ' + m.dot);
        $('.pef-groi-filter-item').removeClass('active');
        $('.pef-groi-filter-item[data-range="' + range + '"]').addClass('active');
    }

    function pctFormatter(cell) { return fmtPct(cell); }

    /** All channel keys available in the multi-select dropdown. */
    function allChannelKeys() {
        const keys = [];
        $('.pef-channel-cb').each(function() {
            const v = $(this).val();
            if (v) keys.push(v);
        });
        return keys;
    }

    /** Currently checked channel keys (multi-select). */
    function getSelectedChannelKeys() {
        const keys = [];
        $('.pef-channel-cb:checked').each(function() {
            const v = $(this).val();
            if (v) keys.push(v);
        });
        return keys;
    }

    function syncChannelFilterLabel() {
        const selected = [];
        $('.pef-channel-cb:checked').each(function() {
            selected.push($(this).attr('data-label') || $(this).val());
        });
        const total = $('.pef-channel-cb').length;
        let label = 'All channels';
        if (selected.length === 0) {
            label = 'No channels';
        } else if (selected.length === 1) {
            label = selected[0];
        } else if (selected.length < total) {
            label = selected.length <= 2
                ? selected.join(', ')
                : (selected.length + ' channels');
        }
        $('#pef-channel-filter-label').text(label);
        const allOn = selected.length === total && total > 0;
        $('#pef-channel-all').prop('checked', allOn);
        $('#pef-channel-all').prop('indeterminate', selected.length > 0 && !allOn);
    }

    function setAllChannelsChecked(on) {
        $('.pef-channel-cb').prop('checked', !!on);
        syncChannelFilterLabel();
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
                        return '<input type="checkbox" id="pef-select-all" title="Select all filtered rows (site-wise / all pages)">';
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
                },
                {
                    title: 'SKU',
                    field: 'sku',
                    width: 160,
                    hozAlign: 'left',
                    vertAlign: 'middle',
                    cssClass: 'pef-sku-cell',
                },
                {
                    title: 'Channel',
                    field: 'channel',
                    width: 100,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    formatter: function(cell) {
                        const v = cell.getValue() || '';
                        return v ? `<span class="pef-channel-badge">${v}</span>` : '';
                    },
                },
                {
                    title: 'Reviews',
                    field: 'reviews',
                    width: 72,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: true,
                    sorter: 'number',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable',
                    headerTooltip: 'Review count from /reviews (sku_reviews) for this SKU × channel',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const n = Number(cell.getValue());
                        const count = isFinite(n) && n > 0 ? Math.round(n) : 0;
                        const sku = encodeURIComponent(String(d.sku || ''));
                        const mp = encodeURIComponent(String(d.marketplace || d.channel_key || ''));
                        if (!(sku && mp)) {
                            return count > 0 ? String(count) : '<span class="text-muted">0</span>';
                        }
                        const href = '/reviews?sku=' + sku + '&marketplace=' + mp;
                        const label = count > 0 ? String(count) : '0';
                        const cls = count > 0 ? 'text-primary fw-semibold' : 'text-muted';
                        return '<a href="' + href + '" target="_blank" rel="noopener" class="' + cls
                            + '" title="Open /reviews for this SKU × channel">' + label + '</a>';
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
                    headerTooltip: 'Overall Shopify L30 (all channels)',
                },
                {
                    title: 'L30',
                    field: 'l30',
                    width: 52,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: true,
                    sorter: 'number',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable',
                    headerTooltip: 'Channel L30 — units sold on this marketplace in the last 30 days',
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '') return '';
                        const n = Number(v);
                        if (!isFinite(n) || n <= 0) return n === 0 ? '0' : '';
                        return Number.isInteger(n) ? String(n) : n.toFixed(0);
                    },
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
                    title: 'CVR',
                    field: 'cvr',
                    width: 56,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: true,
                    sorter: 'number',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable',
                    headerTooltip: 'Channel CVR% — same as /price-increase (L30 ÷ Views × 100 for this marketplace)',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        let n = Number(cell.getValue());
                        if (!isFinite(n)) {
                            const views = Number(d.views) || 0;
                            const l30 = Number(d.l30) || 0;
                            n = views > 0 ? (l30 / views) * 100 : 0;
                        }
                        if (!isFinite(n)) return '';
                        return (Math.round(n * 10) / 10).toFixed(1) + '%';
                    },
                },
                {
                    title: 'STD PRC',
                    field: 'standard_price',
                    width: 78,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: true,
                    sorter: 'number',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable',
                    headerTooltip: 'Standard Price (STD PRC) — same as /price-increase (amazon_data_view.STANDARD_PRICE). Red triangle when channel LMP is lower than STD PRC.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const std = Number(cell.getValue());
                        if (!isFinite(std) || !(std > 0)) {
                            return '<span class="text-muted">—</span>';
                        }
                        const price = Number(d.price) || 0;
                        const lmp = Number(d.lmp) || 0;
                        let color = '#ffc107';
                        let tip = 'Hold';
                        if (price > 0) {
                            const s2 = std.toFixed(2);
                            const p2 = price.toFixed(2);
                            if (parseFloat(s2) < parseFloat(p2)) {
                                color = '#dc3545';
                                tip = 'Reduced vs channel price';
                            } else if (parseFloat(s2) > parseFloat(p2)) {
                                color = '#28a745';
                                tip = 'Increase vs channel price';
                            } else {
                                tip = 'Hold (matches channel price)';
                            }
                        }
                        // Red triangle when LMP < STD PRC for this channel
                        const lmpBelowStd = lmp > 0 && lmp < std;
                        const alertHtml = lmpBelowStd
                            ? '<i class="fas fa-exclamation-triangle pef-std-lmp-alert" title="Alert: LMP ($'
                                + lmp.toFixed(2) + ') is lower than Standard Price ($' + std.toFixed(2)
                                + ') for this channel"></i>'
                            : '';
                        return '<span class="pef-std-prc-cell" title="' + tip.replace(/"/g, '&quot;')
                            + ' — SP (Standard Price)">'
                            + alertHtml
                            + '<span class="pef-std-prc-dot" style="background:' + color + ';"></span>'
                            + std.toFixed(2)
                            + '</span>';
                    },
                    accessorDownload: function(value) { return value; },
                },
                {
                    title: 'LMP',
                    field: 'lmp',
                    width: 72,
                    hozAlign: 'right',
                    vertAlign: 'middle',
                    headerSort: true,
                    sorter: 'number',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'pef-sortable',
                    headerTooltip: 'Lowest Marketplace Price for this SKU on this channel (same source as /price-increase OV L30 LMP). Amazon / eBay / Temu when available.',
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '') {
                            return '<span class="text-muted">—</span>';
                        }
                        const n = Number(v);
                        if (!isFinite(n) || !(n > 0)) {
                            return '<span class="text-muted">—</span>';
                        }
                        return '$' + n.toFixed(2);
                    },
                    accessorDownload: function(value) { return value; },
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
                    title: 'CPN $',
                    field: 'cpn_dollar',
                    width: 62,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    visible: false,
                    editable: true,
                    editor: 'input',
                    headerTooltip: '$ less on checked channels (same as /price-increase CPN $ → saves SPRICE)',
                    formatter: function(cell) {
                        return fmtPefPromoCell(cell.getValue(), '$');
                    },
                    cellEdited: function(cell) {
                        applyPefPromoFromCell(cell, 'cpn', 'dollar');
                    },
                },
                {
                    title: 'PRMT %',
                    field: 'prmt_pct',
                    width: 64,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    editable: true,
                    editor: 'input',
                    headerTooltip: 'eBay1: live promotion API (5–80%, or 0 = pause). Other channels: % less on SPRICE. Also filled by Dil vs PRMT.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        const base = fmtPefPromoCell(cell.getValue(), '%');
                        if (!isPefEbay1Row(d)) return base;
                        const st = String(d.prmt_status || '');
                        if (!st) return base;
                        const tip = String(d.prmt_message || st).replace(/"/g, '&quot;');
                        let badge = '';
                        if (st === 'live') badge = '<span class="text-success ms-1" title="' + tip + '">●</span>';
                        else if (st === 'paused') badge = '<span class="text-muted ms-1" title="' + tip + '">◐</span>';
                        else if (st === 'syncing') badge = '<span class="text-primary ms-1" title="Syncing…">…</span>';
                        else if (st === 'error') badge = '<span class="text-danger ms-1" title="' + tip + '">!</span>';
                        return '<span class="d-inline-flex align-items-center">' + base + badge + '</span>';
                    },
                    cellEdited: function(cell) {
                        applyPefPromoFromCell(cell, 'prmt', 'percent');
                    },
                },
                {
                    title: 'CPN %',
                    field: 'cpn_pct',
                    width: 62,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    editable: true,
                    editor: 'input',
                    headerTooltip: 'eBay1: live coupon API (5–80%, or 0 = pause). Other channels: % less on SPRICE.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        const base = fmtPefPromoCell(cell.getValue(), '%');
                        if (!isPefEbay1Row(d)) return base;
                        const st = String(d.coupon_status || '');
                        if (!st) return base;
                        const tip = String(d.coupon_message || st).replace(/"/g, '&quot;');
                        let badge = '';
                        if (st === 'live') badge = '<span class="text-success ms-1" title="' + tip + '">●</span>';
                        else if (st === 'paused') badge = '<span class="text-muted ms-1" title="' + tip + '">◐</span>';
                        else if (st === 'syncing') badge = '<span class="text-primary ms-1" title="Syncing…">…</span>';
                        else if (st === 'error') badge = '<span class="text-danger ms-1" title="' + tip + '">!</span>';
                        return '<span class="d-inline-flex align-items-center">' + base + badge + '</span>';
                    },
                    cellEdited: function(cell) {
                        applyPefPromoFromCell(cell, 'cpn', 'percent');
                    },
                },
                {
                    title: 'Appr',
                    field: 'appr',
                    width: 48,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    headerTooltip: 'Approve — ticks to put LMP gap (Price − LMP) as DSC % off SPRICE. Auto-clears if LMP is revised.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const checked = d.appr ? 'checked' : '';
                        const id = String(d.id || '').replace(/"/g, '&quot;');
                        return '<input type="checkbox" class="pef-appr-cb" data-id="' + id + '" ' + checked
                            + ' title="Approve LMP gap → DSC %">';
                    },
                },
                {
                    title: 'DSC %',
                    field: 'dsc',
                    width: 56,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    editable: true,
                    editor: 'input',
                    headerTooltip: '% less on SPRICE. Filled by Appr (Price − LMP as %) or edit manually.',
                    formatter: function(cell) {
                        return fmtPefPromoCell(cell.getValue(), '%');
                    },
                    cellEdited: function(cell) {
                        applyPefPromoFromCell(cell, 'dsc', 'percent');
                    },
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
                    editorParams: { step: 0.01, min: 0, selectContents: true },
                    // Normalize "$12.34" / strings from the number editor before save
                    mutatorEdit: function(value) {
                        const n = parseSpriceValue(value);
                        return (isFinite(n) && n > 0) ? round2(n) : null;
                    },
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '') {
                            return '<span class="text-muted">—</span>';
                        }
                        const n = parseSpriceValue(v);
                        if (!isFinite(n) || n <= 0) {
                            return '<span class="text-muted">—</span>';
                        }
                        return '$' + n.toFixed(2);
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
                        const hasSprice = Number(d.sprice) > 0;
                        const canPush = hasSprice && isPushableChannel(d);
                        const st = String(d.success || '').toLowerCase();
                        const alreadyPushed = ['pushed', 'success', 'ok', 'applied'].indexOf(st) !== -1;
                        const cls = alreadyPushed ? 'btn-success pef-push-done' : 'btn-primary';
                        let tip;
                        if (!isPushableChannel(d)) {
                            tip = 'Price push not available for ' + d.channel;
                        } else if (!hasSprice) {
                            tip = 'Set SPrice before push';
                        } else if (alreadyPushed) {
                            tip = 'Already pushed — click to push again to ' + d.channel;
                        } else {
                            tip = 'Push SPRICE to ' + d.channel;
                        }
                        return `<button type="button" class="btn btn-sm ${cls} pef-push-one" ${canPush ? '' : 'disabled'}
                            data-id="${String(d.id).replace(/"/g, '&quot;')}" title="${String(tip).replace(/"/g, '&quot;')}">
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
                        const d = cell.getRow().getData() || {};
                        return successDot(cell.getValue(), d.push_error || d.push_message || null);
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
        // Match header checkbox to all filtered rows (site-wise), not just current page
        const activeData = table.getData('active') || [];
        if (!activeData.length) {
            $all.prop({ checked: false, indeterminate: false });
            return;
        }
        let selected = 0;
        activeData.forEach(function(d) {
            if (d && d.id && selectedIds.has(d.id)) selected++;
        });
        $all.prop('checked', selected === activeData.length);
        $all.prop('indeterminate', selected > 0 && selected < activeData.length);
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

    function saveSprice(row, opts) {
        opts = opts || {};
        const d = row.getData();
        const sprice = round2(parseSpriceValue(d.sprice));
        if (!d.sku || !d.marketplace) {
            if (!opts.silent) toast('Save failed: missing SKU/marketplace', 'error');
            return;
        }
        if (!(sprice > 0)) {
            if (!opts.silent) toast('Enter a SPRICE greater than 0', 'error');
            return;
        }

        // Keep numeric sprice on the row (editor may leave a string)
        row.update({ sprice: sprice });

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

        const payload = {
            sku: d.sku,
            marketplace: d.marketplace,
            sprice: sprice,
            sgpft: local.sgpft || 0,
            // Amazon/eBay: SROI = net; SGROI = gross
            sroi: local.snroi != null ? local.snroi : (local.sroi || 0),
            sgroi: local.sroi || 0,
            spft: local.snpft || 0,
            _token: csrf,
        };
        // CPN applies one channel at a time (same as /price-increase skip_pair_sync)
        if (opts.skipPairSync) payload.skip_pair_sync = 1;

        $.ajax({
            url: '/cvr-master-save-suggested-data',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            data: payload,
        }).done(async function() {
            // Keep saved SPRICE even if breakdown refresh is slow/fails
            row.update(Object.assign({}, localPatch, { success: 'saved' }));
            const idx = pulledRows.findIndex(function(r) { return r.id === d.id; });
            if (idx >= 0) pulledRows[idx] = Object.assign({}, pulledRows[idx], localPatch, { success: 'saved' });
            try {
                await refreshRowFromBreakdown(row);
                // Ensure refresh did not wipe a just-saved positive SPRICE with empty/0
                const after = row.getData();
                if (!(Number(after.sprice) > 0)) {
                    row.update(Object.assign({}, localPatch, { success: 'saved' }));
                } else {
                    // Preserve CPN/DSC UI fields — breakdown has no coupon keys
                    const keep = {
                        success: 'saved',
                        cpn_dollar: after.cpn_dollar != null ? after.cpn_dollar : d.cpn_dollar,
                        cpn_pct: after.cpn_pct != null ? after.cpn_pct : d.cpn_pct,
                        prmt_pct: after.prmt_pct != null ? after.prmt_pct : d.prmt_pct,
                        dsc: after.dsc != null ? after.dsc : d.dsc,
                        appr: after.appr != null ? after.appr : d.appr,
                        _appr_lmp: after._appr_lmp != null ? after._appr_lmp : d._appr_lmp,
                        _cpn_dollar_applied: after._cpn_dollar_applied != null ? after._cpn_dollar_applied : d._cpn_dollar_applied,
                        _cpn_pct_applied: after._cpn_pct_applied != null ? after._cpn_pct_applied : d._cpn_pct_applied,
                        _prmt_pct_applied: after._prmt_pct_applied != null ? after._prmt_pct_applied : d._prmt_pct_applied,
                        _dsc_applied: after._dsc_applied != null ? after._dsc_applied : d._dsc_applied,
                    };
                    // If refresh brought a revised LMP while Appr was on, clear DSC %/Appr
                    if (keep.appr && pefLmpChanged(keep._appr_lmp, after.lmp != null ? after.lmp : d.lmp)) {
                        const prev = Number(keep._dsc_applied) || 0;
                        keep.appr = false;
                        keep._appr_lmp = null;
                        keep.dsc = '';
                        keep._dsc_applied = 0;
                        if (prev > 0 && prev < 100 && Number(after.sprice) > 0) {
                            keep.sprice = round2(Number(after.sprice) / (1 - (prev / 100)));
                        }
                    }
                    row.update(keep);
                    if (keep.sprice != null && Number(keep.sprice) > 0 && keep.appr === false && d.appr) {
                        saveSprice(row, { silent: true });
                    }
                }
            } catch (e) {
                row.update({ success: 'saved' });
            }
            if (!opts.silent) toast('SPRICE saved for ' + d.sku + ' / ' + d.channel, 'success');
            updatePushBtn();
        }).fail(function(xhr) {
            if (!opts.silent) {
                toast('Save failed: ' + (xhr.responseJSON?.error || xhr.responseJSON?.message || 'error'), 'error');
            }
            row.update({ success: 'error' });
        });
    }

    /** Clear SPRICE on one row — same endpoint/payload as /price-increase Clear SPRICE. */
    function clearSpriceForRow(row) {
        const d = row.getData();
        if (!d || !d.sku || !d.marketplace) {
            return $.Deferred().resolve({ ok: false, message: 'Missing SKU/marketplace' }).promise();
        }
        const clearPatch = {
            sprice: null,
            sroi: null,
            sgpft: null,
            snroi: null,
            snpft: null,
            success: 'saving',
        };
        row.update(clearPatch);
        const cacheIdx = pulledRows.findIndex(function(r) { return r.id === d.id; });
        if (cacheIdx >= 0) pulledRows[cacheIdx] = Object.assign({}, pulledRows[cacheIdx], clearPatch);

        return $.ajax({
            url: '/cvr-master-save-suggested-data',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
            data: {
                sku: d.sku,
                marketplace: d.marketplace,
                sprice: 0,
                sgpft: 0,
                sroi: 0,
                sgroi: 0,
                spft: 0,
                _token: csrf,
            },
        }).then(function() {
            row.update({
                sprice: null,
                sroi: null,
                sgpft: null,
                snroi: null,
                snpft: null,
                success: 'saved',
            });
            const idx = pulledRows.findIndex(function(r) { return r.id === d.id; });
            if (idx >= 0) {
                Object.assign(pulledRows[idx], {
                    sprice: null, sroi: null, sgpft: null, snroi: null, snpft: null, success: 'saved',
                });
            }
            return { ok: true, sku: d.sku, channel: d.channel || d.marketplace };
        }, function(xhr) {
            row.update({ success: 'error' });
            return {
                ok: false,
                sku: d.sku,
                channel: d.channel || d.marketplace,
                message: ajaxErrorMessage(xhr, 'Clear failed'),
            };
        });
    }

    async function clearSelectedSprice() {
        if (!table || pefPushInFlight) return;
        const targets = [];
        table.getRows('active').forEach(function(row) {
            const d = row.getData();
            if (!d || !d.id || !selectedIds.has(d.id)) return;
            if (!(Number(d.sprice) > 0)) return;
            targets.push(row);
        });
        if (!targets.length) {
            toast('No selected rows with SPRICE to clear', 'error');
            return;
        }
        if (!confirm(
            'Clear SPRICE on ' + targets.length + ' selected row(s)?\n'
            + 'This removes suggested price on those channel rows (same as /price-increase).'
        )) return;

        const $btn = $('#pef-clear-sprice-btn');
        const btnHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Clearing…');

        let ok = 0;
        let fail = 0;
        // Sequential to avoid hammering CVR / rate limits
        for (let i = 0; i < targets.length; i++) {
            const result = await clearSpriceForRow(targets[i]);
            if (result && result.ok) ok++;
            else fail++;
        }

        $btn.html(btnHtml);
        updatePushBtn();
        try { table.redraw(true); } catch (e) { /* ignore */ }
        toast(
            'Cleared SPRICE: ' + ok + ' ok' + (fail ? (', ' + fail + ' failed') : ''),
            fail ? 'error' : 'success'
        );
    }

    /** Normalize PEF marketplace keys for /cvr-master-push-price (TikTok 1/2 aliases). */
    function pushMarketplaceKey(d) {
        let mp = String(d.marketplace || d.channel_key || d.pull_key || d.channel || '')
            .toLowerCase().replace(/\s+/g, '');
        if (['tiktok1', 'tiktokshop', 'tiktokshop1'].indexOf(mp) !== -1) mp = 'tiktok';
        if (mp === 'tiktokshop2') mp = 'tiktok2';
        if (mp === 'ebaytwo') mp = 'ebay2';
        if (mp === 'ebaythree') mp = 'ebay3';
        if (mp === 'shopifyb2c' || mp === 'shopify') mp = 'sb2c';
        if (mp === 'shopifyb2b') mp = 'sb2b';
        if (mp === 'purchasingpower' || mp === 'purchase') mp = 'ppower';
        if (mp === 'bestbuyusa') mp = 'bestbuy';
        if (mp === 'macys') mp = 'macy';
        return mp;
    }

    /** Temu / Temu2 push base — same as /price-increase. */
    function temuPushBaseFromSprice(sprice) {
        const s = parseFloat(sprice);
        if (!isFinite(s) || s <= 0) return null;
        const push = s < 35 ? ((s * 0.85) - 2.99) : (s * 0.85);
        if (!(push > 0)) return null;
        return +push.toFixed(2);
    }

    function ajaxErrorMessage(xhr, fallback) {
        if (!xhr) return fallback || 'error';
        const j = xhr.responseJSON;
        if (j && (j.message || j.error)) return String(j.message || j.error);
        if (xhr.responseText) {
            try {
                const parsed = JSON.parse(xhr.responseText);
                if (parsed && (parsed.message || parsed.error)) return String(parsed.message || parsed.error);
            } catch (e) { /* ignore */ }
        }
        return fallback || xhr.message || xhr.statusText || 'error';
    }

    /**
     * Push SPRICE via /cvr-master-push-price — same payload rules as /price-increase & Doba:
     * - Doba: price = SPRICE, self_pick_price = SPRICE − Ship
     * - Temu/Temu2: convert SPRICE → push base
     * Retries mirror /doba-tabulator (transient API / IP whitelist blips).
     */
    function buildPushPayload(d) {
        const mp = pushMarketplaceKey(d);
        const sprice = parseFloat(d.sprice);
        if (!d.sku || !(sprice > 0)) {
            return { error: 'SKU and SPRICE required' };
        }

        let pushPrice = +sprice.toFixed(2);
        if (mp === 'temu' || mp === 'temu2') {
            const converted = temuPushBaseFromSprice(sprice);
            if (converted == null) {
                return {
                    error: 'Skipped — ' + (mp === 'temu2' ? 'Temu2' : 'Temu')
                        + ' SPRICE converts to invalid base',
                };
            }
            pushPrice = converted;
        }

        const payload = {
            sku: d.sku,
            price: pushPrice,
            marketplace: mp,
            _token: csrf,
        };

        // Doba: Self Pick = SPRICE − Ship (same as /price-increase & /doba-tabulator)
        if (mp === 'doba') {
            const ship = parseFloat(d.ship) || 0;
            payload.self_pick_price = Math.max(0, +(sprice - ship).toFixed(2));
        }

        // Temu / Temu2: pass goods_id / sku_id when available (same as /price-increase)
        if (mp === 'temu' || mp === 'temu2') {
            const goodsId = String(d.goods_id || d.temu_goods_id || '').trim();
            const skuId = String(d.sku_id || d.temu_sku_id || '').trim();
            if (goodsId) payload.goods_id = goodsId;
            if (skuId) payload.sku_id = skuId;
        }

        return { payload: payload, pushPrice: pushPrice, mp: mp, sprice: sprice };
    }

    function collectPushItems(rowIds) {
        const items = [];
        const skip = [];
        if (!table) return { items: items, skip: skip };
        const want = rowIds ? new Set(rowIds) : null;
        // Use filtered/active rows so channel filter = site-wise push scope
        table.getRows('active').forEach(function(row) {
            const d = row.getData();
            if (!d || !d.id) return;
            if (want && !want.has(d.id)) return;
            if (!want && !selectedIds.has(d.id)) return;
            if (!(Number(d.sprice) > 0) || !isPushableChannel(d)) return;
            const built = buildPushPayload(d);
            if (built.error) {
                skip.push({ row: row, d: d, error: built.error });
                return;
            }
            items.push({
                row: row,
                d: d,
                built: built,
                queueItem: {
                    row_id: d.id,
                    sku: built.payload.sku,
                    marketplace: built.payload.marketplace,
                    channel: d.channel || built.mp,
                    price: built.payload.price,
                    sprice: built.sprice,
                    self_pick_price: built.payload.self_pick_price,
                    goods_id: built.payload.goods_id || null,
                    sku_id: built.payload.sku_id || null,
                },
            });
        });
        return { items: items, skip: skip };
    }

    function isPefEbay123Marketplace(mp) {
        const m = String(mp || '').toLowerCase().replace(/\s+/g, '');
        return m === 'ebay' || m === 'ebay1' || m === 'ebayone'
            || m === 'ebay2' || m === 'ebaytwo'
            || m === 'ebay3' || m === 'ebaythree';
    }

    /** Queue eBay 1/2/3 rows for a delayed live Price/SPRICE pull (only pushed SKUs). */
    function queueEbayPostPushPull(targets) {
        const list = Array.isArray(targets) ? targets : [];
        let added = 0;
        list.forEach(function(t) {
            if (!t || !t.sku || !t.row_id) return;
            if (!isPefEbay123Marketplace(t.marketplace)) return;
            const key = String(t.row_id);
            pefEbayPullQueue[key] = {
                row_id: key,
                sku: String(t.sku),
                marketplace: String(t.marketplace),
            };
            added++;
        });
        if (!added && !Object.keys(pefEbayPullQueue).length) return;
        if (pefEbayPullTimer) clearTimeout(pefEbayPullTimer);
        const n = Object.keys(pefEbayPullQueue).length;
        pefEbayPullTimer = setTimeout(function() {
            pefEbayPullTimer = null;
            runEbayPostPushPull();
        }, PEF_EBAY_PULL_DELAY_MS);
        toast('eBay Price/SPRICE pull in 1 min for ' + n + ' pushed row(s)', 'success');
    }

    function collectEbayPushedFromJob(resp) {
        const out = [];
        if (!resp || !resp.job) return out;
        const results = (resp.job.results && typeof resp.job.results === 'object') ? resp.job.results : {};
        const tasks = Array.isArray(resp.job.tasks) ? resp.job.tasks : [];
        const byId = {};
        tasks.forEach(function(t) {
            if (t && t.row_id) byId[t.row_id] = t;
        });
        Object.keys(results).forEach(function(id) {
            if (!byId[id]) byId[id] = results[id];
        });
        Object.keys(byId).forEach(function(id) {
            const t = byId[id];
            if (!t) return;
            const st = String(t.status || (t.success === true ? 'done' : '')).toLowerCase();
            if (!(st === 'done' || st === 'pushed' || t.success === true)) return;
            const mp = t.marketplace || (results[id] && results[id].marketplace) || '';
            if (!isPefEbay123Marketplace(mp)) return;
            out.push({
                row_id: id,
                sku: t.sku || (results[id] && results[id].sku) || '',
                marketplace: mp,
            });
        });
        return out;
    }

    async function runEbayPostPushPull() {
        const items = Object.keys(pefEbayPullQueue).map(function(k) { return pefEbayPullQueue[k]; });
        pefEbayPullQueue = {};
        if (!items.length || !table) return;

        toast('Pulling eBay Price/SPRICE for ' + items.length + ' pushed row(s)…', 'success');
        items.forEach(function(it) {
            const row = table.getRows().find(function(r) { return r.getData().id === it.row_id; });
            if (row) {
                row.update({ push_message: 'pulling price…', success: 'pulling' });
            }
        });

        try {
            const resp = await $.ajax({
                url: '/pricing-errors-fix-ebay-pull-prices',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                data: { _token: csrf, items: items },
                dataType: 'json',
                timeout: 300000,
            });
            const results = (resp && Array.isArray(resp.results)) ? resp.results : [];
            let ok = 0;
            let fail = 0;
            results.forEach(function(r) {
                const id = r.row_id || (r.marketplace + '|' + r.sku);
                const row = table.getRows().find(function(rw) {
                    const d = rw.getData();
                    return d.id === id || (String(d.sku) === String(r.sku) && isPefEbay123Marketplace(d.marketplace || d.channel_key));
                });
                if (!row) {
                    if (r.success) ok++; else fail++;
                    return;
                }
                const d = row.getData();
                if (r.success && Number(r.price) > 0) {
                    const price = +Number(r.price).toFixed(2);
                    const sprice = (r.sprice != null && Number(r.sprice) > 0)
                        ? +Number(r.sprice).toFixed(2)
                        : (Number(d.sprice) > 0 ? +Number(d.sprice).toFixed(2) : null);
                    const patch = {
                        price: price,
                        success: 'pushed',
                        push_error: null,
                        push_message: r.message || 'price pulled',
                    };
                    if (sprice > 0) patch.sprice = sprice;
                    const live = recalcLiveForRow(Object.assign({}, d, patch));
                    Object.assign(patch, live);
                    if (sprice > 0) {
                        const sug = recalcSuggestedForRow(Object.assign({}, d, patch, { sprice: sprice }));
                        Object.assign(patch, sug);
                    }
                    row.update(patch);
                    syncPefRowCache(d.id, patch);
                    ok++;
                } else {
                    row.update({
                        success: 'pushed',
                        push_message: r.message || 'pull failed',
                        push_error: r.message || 'pull failed',
                    });
                    fail++;
                }
            });
            try { table.redraw(true); } catch (e) { /* ignore */ }
            toast(
                'eBay pull done: ' + ok + ' ok' + (fail ? (', ' + fail + ' failed') : ''),
                fail && !ok ? 'error' : 'success'
            );
        } catch (xhr) {
            toast(ajaxErrorMessage(xhr, 'eBay price pull failed'), 'error');
            items.forEach(function(it) {
                const row = table.getRows().find(function(r) { return r.getData().id === it.row_id; });
                if (row) row.update({ success: 'pushed', push_message: 'pull failed' });
            });
        }
        updatePushBtn();
    }

    /**
     * After push: update live Price only. Keep SPRICE / SROI / SGPFT formulas as-is.
     */
    function patchRowAfterPush(row, d, pushPrice) {
        const mp = pushMarketplaceKey(d);
        const sprice = Number(d.sprice) > 0 ? +Number(d.sprice).toFixed(2) : null;
        let newPrice = sprice;
        if (mp === 'temu' || mp === 'temu2') {
            newPrice = pushPrice > 0 ? +Number(pushPrice).toFixed(2) : sprice;
        }
        const patch = { success: 'pushed', push_error: null, push_message: 'pushed' };
        if (newPrice > 0) {
            const live = recalcLiveForRow(Object.assign({}, d, { price: newPrice }));
            Object.assign(patch, { price: newPrice }, live);
        }
        if (sprice > 0) {
            patch.sprice = sprice;
            if (d.sroi != null) patch.sroi = d.sroi;
            if (d.sgpft != null) patch.sgpft = d.sgpft;
            if (d.snroi != null) patch.snroi = d.snroi;
            if (d.snpft != null) patch.snpft = d.snpft;
        }
        row.update(patch);
        const idx = pulledRows.findIndex(function(r) { return r.id === d.id; });
        if (idx >= 0) pulledRows[idx] = Object.assign({}, pulledRows[idx], patch);
    }

    function patchRowPushFailed(row, errorMsg) {
        const patch = {
            success: 'error',
            push_error: errorMsg || 'Push failed',
            push_message: errorMsg || 'Push failed',
        };
        row.update(patch);
        const d = row.getData();
        const idx = pulledRows.findIndex(function(r) { return r.id === d.id; });
        if (idx >= 0) pulledRows[idx] = Object.assign({}, pulledRows[idx], patch);
    }

    function setPushProgressUi(active, msg, done, total, ok, fail, failedTasks) {
        const $box = $('#pef-push-progress');
        // Only keep banner open while push is running (or caller explicitly shows a result once).
        // Do not leave failed history stuck on screen forever across reloads.
        if (active) $box.addClass('active');
        else $box.removeClass('active');
        $('#pef-push-progress-msg').text(msg || '');
        const t = total || 0;
        const d = done || 0;
        const pct = t > 0 ? Math.min(100, Math.round((d / t) * 100)) : 0;
        $('#pef-push-progress-bar').css('width', pct + '%');
        $('#pef-push-progress-counts').text(
            t ? (d + '/' + t + ' · ' + (ok || 0) + ' ok · ' + (fail || 0) + ' failed') : ''
        );
        const $fail = $('#pef-push-fail-list').empty();
        if (active || (fail > 0 && pefPushInFlight)) {
            (failedTasks || []).slice(0, 50).forEach(function(f) {
                $fail.append(
                    $('<div class="pef-fail-item"></div>').text(
                        (f.sku || '') + ' → ' + (f.channel || f.marketplace || '') + ': ' + (f.error || 'failed')
                    )
                );
            });
        }
        $('#pef-push-cancel-btn').toggle(!!active);
    }

    /** Show completed push summary once (then user can dismiss / it won't return on reload). */
    function showPushResultOnce(msg, done, total, ok, fail, failedTasks) {
        const $box = $('#pef-push-progress');
        $box.addClass('active');
        $('#pef-push-progress-msg').text(msg || '');
        const t = total || 0;
        const d = done || 0;
        const pct = t > 0 ? Math.min(100, Math.round((d / t) * 100)) : 0;
        $('#pef-push-progress-bar').css('width', pct + '%');
        $('#pef-push-progress-counts').text(
            t ? (d + '/' + t + ' · ' + (ok || 0) + ' ok · ' + (fail || 0) + ' failed') : ''
        );
        const $fail = $('#pef-push-fail-list').empty();
        (failedTasks || []).slice(0, 50).forEach(function(f) {
            $fail.append(
                $('<div class="pef-fail-item"></div>').text(
                    (f.sku || '') + ' → ' + (f.channel || f.marketplace || '') + ': ' + (f.error || 'failed')
                )
            );
        });
        $('#pef-push-cancel-btn').hide();
    }

    function stopPushPoll() {
        if (pefPushPollTimer) {
            clearInterval(pefPushPollTimer);
            pefPushPollTimer = null;
        }
        pefPushInFlight = false;
        $('#pef-bulk-push-btn')
            .html('<i class="fas fa-upload"></i> Push (<span id="pef-push-count">0</span>)');
        updatePushBtn();
    }

    function applyPushJobToRows(resp) {
        if (!table || !resp || !resp.job) return;
        const results = (resp.job.results && typeof resp.job.results === 'object') ? resp.job.results : {};
        const tasks = Array.isArray(resp.job.tasks) ? resp.job.tasks : [];
        const byId = {};
        tasks.forEach(function(t) {
            if (t && t.row_id) byId[t.row_id] = t;
        });
        Object.keys(results).forEach(function(id) {
            if (!byId[id]) byId[id] = results[id];
        });

        table.getRows().forEach(function(row) {
            const d = row.getData();
            const t = byId[d.id];
            if (!t) return;
            const st = String(t.status || (t.success === true ? 'done' : (t.success === false ? 'failed' : ''))).toLowerCase();
            if (st === 'done' || st === 'pushed' || t.success === true) {
                const pushPrice = (results[d.id] && results[d.id].price != null)
                    ? Number(results[d.id].price)
                    : (t.price != null ? Number(t.price) : null);
                patchRowAfterPush(row, d, pushPrice);
            } else if (st === 'failed' || st === 'error' || t.success === false) {
                patchRowPushFailed(row, t.error || t.message || 'Push failed');
            } else if (st === 'pushing' || st === 'retrying' || st === 'pending' || st === 'queued') {
                row.update({
                    success: st === 'retrying' ? 'retrying' : 'pushing',
                    push_error: t.error || null,
                    push_message: t.message || st,
                });
            }
        });
    }

    let pefPushStuckToastAt = 0;
    function pollPushStatus() {
        $.ajax({
            url: '/pricing-errors-fix-push-status',
            method: 'GET',
            dataType: 'json',
            timeout: 30000,
        }).done(function(resp) {
            const job = resp && resp.job ? resp.job : {};
            const status = String(job.status || 'idle');
            const active = status === 'running';
            const total = Number(resp.total || job.total || 0);
            const done = Number(resp.done_count != null ? resp.done_count : job.current_index || 0);
            const ok = Number(resp.ok_count || job.ok_count || 0);
            const fail = Number(resp.fail_count || job.fail_count || 0);
            let msg = resp.message || job.last_message || '';
            // Surface stall clearly (server with no queue worker used to sit at 0/N forever)
            if (active && done === 0 && total > 0 && /queued|waiting for worker|stalled/i.test(String(msg))) {
                const now = Date.now();
                if (now - pefPushStuckToastAt > 60000) {
                    pefPushStuckToastAt = now;
                    toast('Push still starting… if this stays at 0, click Cancel then Push again after deploy.', 'error');
                }
            }
            setPushProgressUi(
                active,
                msg,
                done,
                total,
                ok,
                fail,
                resp.failed_tasks || []
            );
            applyPushJobToRows(resp);
            try { table && table.redraw(true); } catch (e) { /* ignore */ }

            if (!active) {
                stopPushPoll();
                syncSelectAllCheckbox();
                // eBay 1/2/3: pull live Price + SPRICE for only successfully pushed SKUs after 1 min
                const ebayPushed = collectEbayPushedFromJob(resp);
                if (ebayPushed.length) {
                    queueEbayPostPushPull(ebayPushed);
                }
                if (fail > 0) {
                    const first = (resp.failed_tasks && resp.failed_tasks[0])
                        ? ((resp.failed_tasks[0].sku || '') + ': ' + (resp.failed_tasks[0].error || 'failed'))
                        : '';
                    const doneMsg = 'Push done: ' + ok + ' ok, ' + fail + ' failed'
                        + (first ? ' — ' + first : '');
                    showPushResultOnce(
                        doneMsg,
                        done,
                        total,
                        ok,
                        fail,
                        resp.failed_tasks || []
                    );
                    toast(doneMsg, 'error');
                } else if (status === 'completed') {
                    setPushProgressUi(false, '', 0, 0, 0, 0, []);
                    toast('Push done: ' + ok + ' ok', 'success');
                } else if (status === 'failed') {
                    toast(resp.message || job.last_message || 'Push failed', 'error');
                    setPushProgressUi(false, '', 0, 0, 0, 0, []);
                }
            }
        }).fail(function(xhr) {
            // Keep polling — worker may still be fine
            console.warn('PEF push status poll failed', ajaxErrorMessage(xhr, 'status error'));
        });
    }

    function startPushPoll() {
        if (pefPushPollTimer) clearInterval(pefPushPollTimer);
        // 5s poll — fewer Auth/DB hits while the CLI worker holds MySQL during API calls
        pefPushPollTimer = setInterval(pollPushStatus, 5000);
        pollPushStatus();
    }

    async function queuePushItems(collected) {
        const items = collected.items || [];
        const skip = collected.skip || [];
        skip.forEach(function(s) {
            patchRowPushFailed(s.row, s.error);
        });
        if (!items.length) {
            toast(skip.length ? ('Nothing to queue — ' + skip[0].error) : 'No pushable rows', 'error');
            return;
        }

        items.forEach(function(it) {
            it.row.update({ success: 'queued', push_error: null, push_message: 'queued' });
        });

        pefPushInFlight = true;
        $('#pef-bulk-push-btn').prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin"></i> Queuing...');
        setPushProgressUi(true, 'Queuing ' + items.length + ' row(s)…', 0, items.length, 0, 0, []);

        try {
            const resp = await $.ajax({
                url: '/pricing-errors-fix-push',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                data: {
                    _token: csrf,
                    items: items.map(function(it) { return it.queueItem; }),
                },
                dataType: 'json',
                timeout: 60000,
            });
            toast(resp.message || ('Queued ' + items.length + ' push(es)'), 'success');
            $('#pef-bulk-push-btn').html('<i class="fas fa-spinner fa-spin"></i> Running…');
            startPushPoll();
        } catch (xhr) {
            stopPushPoll();
            const msg = ajaxErrorMessage(xhr, 'Could not queue push');
            items.forEach(function(it) { patchRowPushFailed(it.row, msg); });
            setPushProgressUi(false, msg, 0, items.length, 0, items.length, items.map(function(it) {
                return { sku: it.d.sku, channel: it.d.channel, error: msg };
            }));
            toast(msg, 'error');
            try { table.redraw(true); } catch (e) { /* ignore */ }
            updatePushBtn();
        }
    }

    async function pushSelected() {
        if (!table || pefPushInFlight) return;
        const collected = collectPushItems(null);
        if (!collected.items.length && !collected.skip.length) {
            toast('No selected pushable rows with SPRICE > 0', 'error');
            return;
        }
        const n = collected.items.length;
        if (!n) {
            toast(collected.skip[0] ? collected.skip[0].error : 'Nothing to push', 'error');
            return;
        }
        // Summarize by site/channel for confirm (site-wise bulk)
        const byMp = {};
        collected.items.forEach(function(it) {
            const mp = it.built.mp || it.queueItem.marketplace || '?';
            byMp[mp] = (byMp[mp] || 0) + 1;
        });
        const siteSummary = Object.keys(byMp).map(function(k) {
            return k + ': ' + byMp[k];
        }).join(', ');
        if (!confirm(
            'Queue SPRICE push for ' + n + ' row(s) in background?\n'
            + 'Sites: ' + siteSummary + '\n'
            + 'Worker will retry until each push succeeds (or fails with a reason).'
        )) return;
        await queuePushItems(collected);
    }

    $(document).on('change', '#pef-select-all', function() {
        const checked = $(this).is(':checked');
        if (!table) return;
        $(this).prop('indeterminate', false);
        // Site-wise: select / clear ALL filtered rows across every page
        // (channel filter → one site; All channels → every visible row)
        const activeData = table.getData('active') || [];
        activeData.forEach(function(d) {
            if (!d || !d.id) return;
            if (checked) selectedIds.add(d.id);
            else selectedIds.delete(d.id);
        });
        table.redraw(true);
        updatePushBtn();
        syncSelectAllCheckbox();
    });

    $(document).on('change', '.pef-row-cb', function() {
        // Use attr — jQuery .data() can coerce / cache and break id matching for bulk push
        const id = $(this).attr('data-id');
        if (!id) return;
        if ($(this).is(':checked')) selectedIds.add(id);
        else selectedIds.delete(id);
        updatePushBtn();
        syncSelectAllCheckbox();
    });

    $(document).on('change', '.pef-appr-cb', function() {
        const id = $(this).attr('data-id');
        if (!table || !id) return;
        const row = table.getRows().find(function(r) { return r.getData().id === id; });
        if (!row) return;
        if ($(this).is(':checked')) {
            applyPefApprDiscount(row);
        } else {
            clearPefApprDiscount(row, { save: true, redraw: true });
            updatePushBtn();
        }
    });

    $(document).on('click', '.pef-push-one', async function() {
        const id = $(this).attr('data-id');
        if (!table || !id || pefPushInFlight) return;
        const row = table.getRows().find(function(r) { return r.getData().id === id; });
        if (!row) return;
        const d = row.getData();
        if (!(Number(d.sprice) > 0) || !isPushableChannel(d)) return;
        const collected = collectPushItems([id]);
        await queuePushItems(collected);
    });

    $(document).on('click', '#pef-push-cancel-btn', function() {
        if (!confirm('Cancel the background price push?')) return;
        $.ajax({
            url: '/pricing-errors-fix-push-cancel',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            dataType: 'json',
        }).always(function(resp) {
            stopPushPoll();
            setPushProgressUi(false, (resp && resp.message) || 'Push cancelled', 0, 0, 0, 0, (resp && resp.failed_tasks) || []);
            toast((resp && resp.message) || 'Push cancelled', 'error');
            updatePushBtn();
        });
    });

    // Resume ONLY if a push is still running. Do not re-show old completed failures on every reload.
    $(function() {
        $.ajax({
            url: '/pricing-errors-fix-push-status',
            method: 'GET',
            dataType: 'json',
            timeout: 15000,
        }).done(function(resp) {
            const st = resp && resp.job && resp.job.status;
            if (st === 'running') {
                pefPushInFlight = true;
                $('#pef-bulk-push-btn').prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin"></i> Running…');
                startPushPoll();
            }
            // completed/failed history stays on server for debugging, but UI stays clean
        });

        // Click progress banner to dismiss after a finished push
        $(document).on('click', '#pef-push-progress', function() {
            if (pefPushInFlight) return;
            setPushProgressUi(false, '', 0, 0, 0, 0, []);
        });
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
        const parentQ = ($('#pef-parent-search').val() || '').trim().toLowerCase();
        const skuQ = ($('#pef-sku-search').val() || '').trim().toLowerCase();
        // Toolbar Parent / SKU search (no column header filters)
        if (parentQ) {
            table.addFilter(function(data) {
                return String(data.parent || '').toLowerCase().indexOf(parentQ) !== -1;
            });
        }
        if (skuQ) {
            table.addFilter(function(data) {
                return String(data.sku || '').toLowerCase().indexOf(skuQ) !== -1;
            });
        }
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

    // ==================== Dil vs PRMT modal ====================
    let pefDilPrmtRules = Array.isArray(PEF_DIL_PRMT_DEFAULTS) && PEF_DIL_PRMT_DEFAULTS.length
        ? PEF_DIL_PRMT_DEFAULTS.map(function(r) { return Object.assign({}, r); })
        : [
            { key: '0-10', label: '0–10%', prmt: 10 },
            { key: '10-20', label: '10–20%', prmt: 9 },
            { key: '20-30', label: '20–30%', prmt: 8 },
            { key: '30-40', label: '30–40%', prmt: 7 },
            { key: '40-50', label: '40–50%', prmt: 6 },
            { key: '50-60', label: '50–60%', prmt: 5 },
            { key: '60-70', label: '60–70%', prmt: 4 },
            { key: '70-80', label: '70–80%', prmt: 3 },
            { key: '80-90', label: '80–90%', prmt: 2 },
            { key: '90-100', label: '90–100%', prmt: 1 },
            { key: 'gt-100', label: '> 100%', prmt: 0 },
        ];

    function pefDilSlabKey(dil) {
        const n = Number(dil);
        if (!isFinite(n) || n < 0) return '0-10';
        if (n > 100) return 'gt-100';
        if (n >= 90) return '90-100';
        if (n >= 80) return '80-90';
        if (n >= 70) return '70-80';
        if (n >= 60) return '60-70';
        if (n >= 50) return '50-60';
        if (n >= 40) return '40-50';
        if (n >= 30) return '30-40';
        if (n >= 20) return '20-30';
        if (n >= 10) return '10-20';
        return '0-10';
    }

    function pefPrmtForDil(dil) {
        const key = pefDilSlabKey(dil);
        const rule = pefDilPrmtRules.find(function(r) { return r.key === key; });
        if (!rule) return 0;
        const n = Number(rule.prmt);
        return isFinite(n) && n >= 0 ? n : 0;
    }

    function renderDilPrmtModalTable() {
        const $tb = $('#pef-dil-prmt-tbody').empty();
        pefDilPrmtRules.forEach(function(r, idx) {
            const prmt = isFinite(Number(r.prmt)) ? Number(r.prmt) : 0;
            $tb.append(
                '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                + '<td>' + String(r.label || r.key) + '</td>'
                + '<td class="text-end">'
                + '<input type="number" class="form-control form-control-sm pef-dil-prmt-input" '
                + 'min="0" step="0.1" value="' + prmt + '" data-idx="' + idx + '" '
                + 'title="PRMT % for Dil ' + String(r.label || r.key) + '">'
                + '</td></tr>'
            );
        });
    }

    function readDilPrmtRulesFromModal() {
        $('#pef-dil-prmt-tbody tr').each(function() {
            const key = String($(this).attr('data-key') || '');
            const val = parseFloat($(this).find('.pef-dil-prmt-input').val());
            const rule = pefDilPrmtRules.find(function(r) { return r.key === key; });
            if (!rule) return;
            rule.prmt = (isFinite(val) && val >= 0) ? val : 0;
        });
        return pefDilPrmtRules.map(function(r) {
            return { key: r.key, label: r.label, prmt: Number(r.prmt) || 0 };
        });
    }

    async function loadDilPrmtRules() {
        $('#pef-dil-prmt-status').text('Loading…');
        try {
            const res = await $.ajax({
                url: '/pricing-errors-fix-dil-prmt',
                method: 'GET',
                dataType: 'json',
            });
            if (res && Array.isArray(res.rules) && res.rules.length) {
                pefDilPrmtRules = res.rules.map(function(r) { return Object.assign({}, r); });
            }
            renderDilPrmtModalTable();
            $('#pef-dil-prmt-status').text(res && res.is_default
                ? 'Using first-time defaults (0–10). Save to keep your edits.'
                : 'Loaded saved Dil vs PRMT rules.');
        } catch (e) {
            renderDilPrmtModalTable();
            $('#pef-dil-prmt-status').text('Could not load saved rules — showing defaults.');
        }
    }

    function saveDilPrmtRules() {
        const rules = readDilPrmtRulesFromModal();
        const $btn = $('#pef-dil-prmt-save-btn');
        const html = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');
        $.ajax({
            url: '/pricing-errors-fix-dil-prmt',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            data: { rules: rules, _token: csrf },
        }).done(function(res) {
            if (res && Array.isArray(res.rules)) {
                pefDilPrmtRules = res.rules.map(function(r) { return Object.assign({}, r); });
                renderDilPrmtModalTable();
            }
            toast('Dil vs PRMT rules saved', 'success');
            $('#pef-dil-prmt-status').text('Saved.');
        }).fail(function(xhr) {
            toast('Save failed: ' + (xhr.responseJSON?.message || 'error'), 'error');
        }).always(function() {
            $btn.prop('disabled', false).html(html);
        });
    }

    /**
     * Autopopulate PRMT % from Dil slabs.
     * eBay1 → promotion API (0 = pause). Other channels → % less on SPRICE.
     */
    async function applyDilPrmtToTargets(targets, label) {
        readDilPrmtRulesFromModal();
        if (!targets.length) {
            toast('No rows to apply', 'error');
            return;
        }
        let ok = 0;
        let skipped = 0;
        let ebayOk = 0;
        let ebayFail = 0;
        for (let i = 0; i < targets.length; i++) {
            const item = targets[i];
            const d = item.row.getData();
            const dil = Number(d.dil);
            // INV = 0 → always PRMT% = 0 (pauses eBay1 promotion)
            let prmt = Number(d.inv || 0) === 0 ? 0 : pefPrmtForDil(dil);

            // eBay1 → promotion API (0 pauses)
            if (isPefEbay1Row(d)) {
                item.row.update({
                    prmt_pct: String(prmt),
                    _prmt_pct_applied: prmt,
                    prmt_status: 'syncing',
                });
                const api = await syncEbay1PromotionApi(d.sku, prmt);
                item.row.update({
                    prmt_pct: String(prmt),
                    _prmt_pct_applied: prmt,
                    prmt_status: api.ok ? (prmt > 0 ? 'live' : 'paused') : 'error',
                    prmt_message: api.message || '',
                });
                syncPefRowCache(d.id, {
                    prmt_pct: String(prmt),
                    _prmt_pct_applied: prmt,
                    prmt_status: api.ok ? (prmt > 0 ? 'live' : 'paused') : 'error',
                    prmt_message: api.message || '',
                });
                if (api.ok) ebayOk++;
                else ebayFail++;
                continue;
            }

            if (!(prmt > 0)) {
                // 0% = no discount — still stamp PRMT % for visibility
                item.row.update({ prmt_pct: String(prmt), _prmt_pct_applied: 0 });
                skipped++;
                continue;
            }
            const promo = { type: 'percent', value: prmt };
            const base = getPefDiscountBase(d, '_prmt_pct_applied', 'percent');
            const newPrice = applyPromoToSpriceBase(base, promo);
            if (!(base > 0) || !(newPrice > 0)) {
                skipped++;
                continue;
            }
            const patch = {
                prmt_pct: String(prmt),
                _prmt_pct_applied: prmt,
                sprice: newPrice,
            };
            item.row.update(patch);
            syncPefRowCache(d.id, patch);
            saveSprice(item.row, { skipPairSync: true, silent: true });
            ok++;
        }
        toast(
            'Dil vs PRMT (' + label + '): PRMT % → ' + ok + ' row(s)'
                + (ebayOk || ebayFail ? ('; eBay1 promo ' + ebayOk + ' ok' + (ebayFail ? (' / ' + ebayFail + ' fail') : '')) : '')
                + (skipped ? ('; skipped ' + skipped) : '')
                + '.',
            (ok || ebayOk) ? 'success' : 'error'
        );
        updatePushBtn();
        if (table) table.redraw(true);
    }

    $('#pef-dil-vs-prmt-btn').on('click', function() {
        const modalEl = document.getElementById('pefDilVsPrmtModal');
        if (!modalEl) return;
        renderDilPrmtModalTable();
        loadDilPrmtRules();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
    $('#pef-dil-prmt-reset-btn').on('click', function() {
        pefDilPrmtRules = (Array.isArray(PEF_DIL_PRMT_DEFAULTS) && PEF_DIL_PRMT_DEFAULTS.length
            ? PEF_DIL_PRMT_DEFAULTS
            : pefDilPrmtRules
        ).map(function(r) { return Object.assign({}, r); });
        // Force first-time figure 0→10 if embedded defaults missing keys
        const forced = [
            ['0-10', '0–10%', 10], ['10-20', '10–20%', 9], ['20-30', '20–30%', 8],
            ['30-40', '30–40%', 7], ['40-50', '40–50%', 6], ['50-60', '50–60%', 5],
            ['60-70', '60–70%', 4], ['70-80', '70–80%', 3], ['80-90', '80–90%', 2],
            ['90-100', '90–100%', 1], ['gt-100', '> 100%', 0],
        ];
        pefDilPrmtRules = forced.map(function(t) {
            return { key: t[0], label: t[1], prmt: t[2] };
        });
        renderDilPrmtModalTable();
        $('#pef-dil-prmt-status').text('Reset to first-time defaults (0–10). Save to persist.');
    });
    $('#pef-dil-prmt-save-btn').on('click', saveDilPrmtRules);
    $('#pef-dil-prmt-apply-selected-btn').on('click', function() {
        applyDilPrmtToTargets(collectSelectedRows(), 'selected');
    });
    $('#pef-dil-prmt-apply-visible-btn').on('click', function() {
        if (!table) {
            toast('Load data first', 'error');
            return;
        }
        const targets = table.getRows('active').map(function(row) {
            return { row: row, d: row.getData() };
        });
        if (!confirm('Apply Dil→PRMT % to ' + targets.length + ' visible row(s)?')) return;
        applyDilPrmtToTargets(targets, 'visible');
    });

    // ==================== CVR vs CPN modal ====================
    let pefCvrCpnRules = Array.isArray(PEF_CVR_CPN_DEFAULTS) && PEF_CVR_CPN_DEFAULTS.length
        ? PEF_CVR_CPN_DEFAULTS.map(function(r) { return Object.assign({}, r); })
        : [
            { key: 'eq-0', label: '0%', cpn: 10 },
            { key: '0.01-1', label: '0.01–1%', cpn: 9 },
            { key: '1-1.5', label: '1–1.5%', cpn: 8 },
            { key: '1.5-2', label: '1.5–2%', cpn: 7 },
            { key: '2-3', label: '2–3%', cpn: 6 },
            { key: '3-4', label: '3–4%', cpn: 5 },
            { key: '4-5', label: '4–5%', cpn: 4 },
            { key: '5-6', label: '5–6%', cpn: 3 },
            { key: '6-6.5', label: '6–6.5%', cpn: 2 },
            { key: '6.5-7', label: '6.5–7%', cpn: 1 },
            { key: 'gt-7', label: '> 7%', cpn: 0 },
        ];

    function pefCvrSlabKey(cvr) {
        const n = Number(cvr);
        if (!isFinite(n) || n <= 0) return 'eq-0';
        if (n > 7) return 'gt-7';
        if (n >= 6.5) return '6.5-7';
        if (n >= 6) return '6-6.5';
        if (n >= 5) return '5-6';
        if (n >= 4) return '4-5';
        if (n >= 3) return '3-4';
        if (n >= 2) return '2-3';
        if (n >= 1.5) return '1.5-2';
        if (n >= 1) return '1-1.5';
        return '0.01-1'; // 0.01 ≤ cvr < 1
    }

    function pefCpnForCvr(cvr) {
        const key = pefCvrSlabKey(cvr);
        const rule = pefCvrCpnRules.find(function(r) { return r.key === key; });
        if (!rule) return 0;
        const n = Number(rule.cpn);
        return isFinite(n) && n >= 0 ? n : 0;
    }

    function renderCvrCpnModalTable() {
        const $tb = $('#pef-cvr-cpn-tbody').empty();
        pefCvrCpnRules.forEach(function(r, idx) {
            const cpn = isFinite(Number(r.cpn)) ? Number(r.cpn) : 0;
            $tb.append(
                '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                + '<td>' + String(r.label || r.key) + '</td>'
                + '<td class="text-end">'
                + '<input type="number" class="form-control form-control-sm pef-cvr-cpn-input" '
                + 'min="0" step="0.1" value="' + cpn + '" data-idx="' + idx + '" '
                + 'title="CPN % for CVR ' + String(r.label || r.key) + '">'
                + '</td></tr>'
            );
        });
    }

    function readCvrCpnRulesFromModal() {
        $('#pef-cvr-cpn-tbody tr').each(function() {
            const key = String($(this).attr('data-key') || '');
            const val = parseFloat($(this).find('.pef-cvr-cpn-input').val());
            const rule = pefCvrCpnRules.find(function(r) { return r.key === key; });
            if (!rule) return;
            rule.cpn = (isFinite(val) && val >= 0) ? val : 0;
        });
        return pefCvrCpnRules.map(function(r) {
            return { key: r.key, label: r.label, cpn: Number(r.cpn) || 0 };
        });
    }

    async function loadCvrCpnRules() {
        $('#pef-cvr-cpn-status').text('Loading…');
        try {
            const res = await $.ajax({
                url: '/pricing-errors-fix-cvr-cpn',
                method: 'GET',
                dataType: 'json',
            });
            if (res && Array.isArray(res.rules) && res.rules.length) {
                pefCvrCpnRules = res.rules.map(function(r) { return Object.assign({}, r); });
            }
            renderCvrCpnModalTable();
            $('#pef-cvr-cpn-status').text(res && res.is_default
                ? 'Using first-time defaults (0–10). Save to keep your edits.'
                : 'Loaded saved CVR vs CPN rules.');
        } catch (e) {
            renderCvrCpnModalTable();
            $('#pef-cvr-cpn-status').text('Could not load saved rules — showing defaults.');
        }
    }

    function saveCvrCpnRules() {
        const rules = readCvrCpnRulesFromModal();
        const $btn = $('#pef-cvr-cpn-save-btn');
        const html = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');
        $.ajax({
            url: '/pricing-errors-fix-cvr-cpn',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            data: { rules: rules, _token: csrf },
        }).done(function(res) {
            if (res && Array.isArray(res.rules)) {
                pefCvrCpnRules = res.rules.map(function(r) { return Object.assign({}, r); });
                renderCvrCpnModalTable();
            }
            toast('CVR vs CPN rules saved', 'success');
            $('#pef-cvr-cpn-status').text('Saved.');
        }).fail(function(xhr) {
            toast('Save failed: ' + (xhr.responseJSON?.message || 'error'), 'error');
        }).always(function() {
            $btn.prop('disabled', false).html(html);
        });
    }

    async function applyCvrCpnToTargets(targets, label) {
        readCvrCpnRulesFromModal();
        if (!targets.length) {
            toast('No rows to apply', 'error');
            return;
        }
        let ok = 0;
        let skipped = 0;
        let ebayOk = 0;
        let ebayFail = 0;
        for (let i = 0; i < targets.length; i++) {
            const item = targets[i];
            const d = item.row.getData();
            let cvr = Number(d.cvr);
            if (!isFinite(cvr) || cvr < 0) {
                const views = Number(d.views) || 0;
                const l30 = Number(d.l30) || 0;
                cvr = views > 0 ? round2((l30 / views) * 100) : 0;
            }
            // INV = 0 → always CPN% = 0 (pauses eBay1 coupon)
            let cpn = Number(d.inv || 0) === 0 ? 0 : pefCpnForCvr(cvr);

            // eBay1 → coupon API (0 pauses)
            if (isPefEbay1Row(d)) {
                item.row.update({
                    cvr: cvr,
                    cpn_pct: String(cpn),
                    _cpn_pct_applied: cpn,
                    coupon_status: 'syncing',
                });
                const api = await syncEbay1CouponApi(d.sku, cpn);
                item.row.update({
                    cvr: cvr,
                    cpn_pct: String(cpn),
                    _cpn_pct_applied: cpn,
                    coupon_status: api.ok ? (cpn > 0 ? 'live' : 'paused') : 'error',
                    coupon_message: api.message || '',
                });
                syncPefRowCache(d.id, {
                    cvr: cvr,
                    cpn_pct: String(cpn),
                    _cpn_pct_applied: cpn,
                    coupon_status: api.ok ? (cpn > 0 ? 'live' : 'paused') : 'error',
                    coupon_message: api.message || '',
                });
                if (api.ok) ebayOk++;
                else ebayFail++;
                continue;
            }

            if (!(cpn > 0)) {
                item.row.update({ cpn_pct: String(cpn), _cpn_pct_applied: 0, cvr: cvr });
                skipped++;
                continue;
            }
            const promo = { type: 'percent', value: cpn };
            const base = getPefDiscountBase(d, '_cpn_pct_applied', 'percent');
            const newPrice = applyPromoToSpriceBase(base, promo);
            if (!(base > 0) || !(newPrice > 0)) {
                skipped++;
                continue;
            }
            const patch = {
                cvr: cvr,
                cpn_pct: String(cpn),
                _cpn_pct_applied: cpn,
                sprice: newPrice,
            };
            item.row.update(patch);
            syncPefRowCache(d.id, patch);
            saveSprice(item.row, { skipPairSync: true, silent: true });
            ok++;
        }
        toast(
            'CVR vs CPN (' + label + '): CPN % → ' + ok + ' row(s)'
                + (ebayOk || ebayFail ? ('; eBay1 coupon ' + ebayOk + ' ok' + (ebayFail ? (' / ' + ebayFail + ' fail') : '')) : '')
                + (skipped ? ('; skipped ' + skipped) : '')
                + '.',
            (ok || ebayOk) ? 'success' : 'error'
        );
        updatePushBtn();
        if (table) table.redraw(true);
    }

    $('#pef-cvr-vs-cpn-btn').on('click', function() {
        const modalEl = document.getElementById('pefCvrVsCpnModal');
        if (!modalEl) return;
        renderCvrCpnModalTable();
        loadCvrCpnRules();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
    $('#pef-cvr-cpn-reset-btn').on('click', function() {
        pefCvrCpnRules = [
            ['eq-0', '0%', 10], ['0.01-1', '0.01–1%', 9], ['1-1.5', '1–1.5%', 8],
            ['1.5-2', '1.5–2%', 7], ['2-3', '2–3%', 6], ['3-4', '3–4%', 5],
            ['4-5', '4–5%', 4], ['5-6', '5–6%', 3], ['6-6.5', '6–6.5%', 2],
            ['6.5-7', '6.5–7%', 1], ['gt-7', '> 7%', 0],
        ].map(function(t) {
            return { key: t[0], label: t[1], cpn: t[2] };
        });
        renderCvrCpnModalTable();
        $('#pef-cvr-cpn-status').text('Reset to first-time defaults (0–10). Save to persist.');
    });
    $('#pef-cvr-cpn-save-btn').on('click', saveCvrCpnRules);
    $('#pef-cvr-cpn-apply-selected-btn').on('click', function() {
        applyCvrCpnToTargets(collectSelectedRows(), 'selected');
    });
    $('#pef-cvr-cpn-apply-visible-btn').on('click', function() {
        if (!table) {
            toast('Load data first', 'error');
            return;
        }
        const targets = table.getRows('active').map(function(row) {
            return { row: row, d: row.getData() };
        });
        if (!confirm('Apply CVR→CPN % to ' + targets.length + ' visible row(s)?')) return;
        applyCvrCpnToTargets(targets, 'visible');
    });

    $('#pef-bulk-push-btn').on('click', pushSelected);
    $('#pef-clear-sprice-btn').on('click', clearSelectedSprice);
    $('#pef-std-to-sprice-btn').on('click', applyStdPriceToSprice);
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

    // Channel multi-select — keep menu open while toggling; filter client-side
    $(document).on('click', '.pef-channel-menu', function(e) {
        e.stopPropagation();
    });
    $(document).on('change', '#pef-channel-all', function() {
        setAllChannelsChecked($(this).is(':checked'));
        if (dataLoaded) applyStatusFilter();
    });
    $(document).on('change', '.pef-channel-cb', function() {
        syncChannelFilterLabel();
        if (dataLoaded) applyStatusFilter();
    });
    syncChannelFilterLabel();
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
        setGroiFilterUI('lt-60');
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
