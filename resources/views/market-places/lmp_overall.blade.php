@extends('layouts.vertical', ['title' => 'LMP Overall', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        #lmp-overall-wrap .tabulator { border: 1px solid #dee2e6; border-radius: 8px; font-size: 12px; }
        #lmp-overall-wrap .tabulator .tabulator-header { background: #f8f9fa; }
        #lmp-overall-wrap .tabulator .tabulator-header .tabulator-col { height: 108px !important; }
        #lmp-overall-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: 100%;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding: 6px 2px;
        }
        #lmp-overall-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap !important;
            transform: rotate(180deg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            line-height: 1.1;
            padding: 2px 0;
        }
        #lmp-overall-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #lmp-overall-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }
        #lmp-overall-wrap .tabulator-col.lmp-header-flat .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
        }
        #lmp-overall-wrap .tabulator .tabulator-cell { padding: 4px 4px !important; white-space: nowrap; }
        #lmp-overall-wrap .tabulator-row.tabulator-selectable:hover { cursor: default; background-color: transparent; }
        #lmp-overall-wrap .tabulator-row.tabulator-row-odd.tabulator-selectable:hover { background-color: #fff; }
        #lmp-overall-wrap .tabulator-row.tabulator-row-even.tabulator-selectable:hover { background-color: #efefef; }
        #lmp-overall-wrap .tabulator-row .tabulator-cell input[type="checkbox"] { cursor: pointer; }
        #lmp-overall-wrap .tabulator-row.tabulator-selected { background: #e7f1ff !important; }
        .lmp-overall-thumb { width: 36px; height: 36px; object-fit: contain; border-radius: 4px; background: #fff; }
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent,
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent .tabulator-cell { background: #fff3cd !important; font-weight: 600; }
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent.tabulator-selected,
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent.tabulator-selected .tabulator-cell { background: #ffe08a !important; }
        .lmp-overall-price { font-weight: 700; color: #198754; }
        .lmp-std-tri { font-size: 11px; margin-left: 3px; vertical-align: middle; }
        .lmp-std-tri-high { color: #6f42c1; }
        .lmp-std-tri-low { color: #dc3545; }
        .lmp-overall-count { color: #007bff; font-weight: 700; text-decoration: none; cursor: pointer; }
        .lmp-overall-count:hover { text-decoration: underline; }
        .lmp-overall-edit { position: relative; z-index: 2; line-height: 1; }
        #lmp-overall-wrap .tabulator-cell.lmp-diff-magenta,
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent .tabulator-cell.lmp-diff-magenta,
        #lmp-overall-wrap .tabulator-row.tabulator-selected .tabulator-cell.lmp-diff-magenta,
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent.tabulator-selected .tabulator-cell.lmp-diff-magenta {
            background: #ff00ff !important; color: #000 !important; font-weight: 700;
        }
        #lmp-overall-wrap .tabulator-cell.lmp-diff-red,
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent .tabulator-cell.lmp-diff-red,
        #lmp-overall-wrap .tabulator-row.tabulator-selected .tabulator-cell.lmp-diff-red,
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent.tabulator-selected .tabulator-cell.lmp-diff-red {
            background: #dc3545 !important; color: #000 !important; font-weight: 700;
        }
        #lmp-overall-wrap .tabulator-cell.lmp-diff-green,
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent .tabulator-cell.lmp-diff-green,
        #lmp-overall-wrap .tabulator-row.tabulator-selected .tabulator-cell.lmp-diff-green,
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent.tabulator-selected .tabulator-cell.lmp-diff-green {
            background: #28a745 !important; color: #000 !important; font-weight: 700;
        }
        #lmp-overall-wrap .tabulator-cell.lmp-diff-yellow,
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent .tabulator-cell.lmp-diff-yellow,
        #lmp-overall-wrap .tabulator-row.tabulator-selected .tabulator-cell.lmp-diff-yellow,
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent.tabulator-selected .tabulator-cell.lmp-diff-yellow {
            background: #ffc107 !important; color: #000 !important; font-weight: 700;
        }
        #lmpOverallStdModal { z-index: 20000; }
        #lmpOverallLmpModal .modal-dialog { max-width: min(1680px, calc(100vw - 1rem)); }
        #lmpOverallLmpModal .lmp-overall-comp-img { width: 42px; height: 42px; object-fit: contain; background: #fff; border-radius: 4px; }
        #lmpOverallLmpModal tr.lmp-ignored-row { opacity: 0.55; background: #f1f3f5 !important; }
        #lmpOverallLmpModal tr.lmp-ignored-row td { text-decoration: line-through; text-decoration-color: #adb5bd; }
        #lmpOverallLmpModal tr.lmp-ignored-row td.lmp-ignore-cell,
        #lmpOverallLmpModal tr.lmp-ignored-row td.lmp-actions-cell,
        #lmpOverallLmpModal tr.lmp-ignored-row .lmp-ov-ignore { text-decoration: none; }
        #lmpOverallLmpModal tr.lmp-lowest-row,
        #lmpOverallLmpModal tr.lmp-lowest-row > td { background: #fff8c5 !important; }
        #lmpOverallLmpModal #lmp-overall-lmp-list thead th {
            background: #334155; color: #fff; font-size: 11px; white-space: nowrap; vertical-align: middle;
        }
        #lmpOverallLmpModal #lmp-overall-lmp-list { font-size: 12px; }
        #lmpOverallLmpModal .lmp-ov-preview-btn {
            border: 0; background: none; color: #0d6efd; padding: 0 2px; cursor: pointer; line-height: 1;
        }
        #lmp-overall-text-preview {
            position: fixed; inset: 0; z-index: 20050; background: rgba(15, 23, 42, 0.45);
            display: flex; align-items: center; justify-content: center; padding: 1rem;
        }
        #lmp-overall-text-preview[hidden] { display: none !important; }
        #lmp-overall-text-preview .card { max-width: 640px; width: 100%; }
        #lmp-overall-play { flex-shrink: 0; }
        #lmp-overall-play .btn i { font-size: 1.1rem; }
        #lmp-play-auto { color: #28a745; }
        #lmp-play-auto:hover { background-color: #28a745 !important; color: #fff !important; }
        #lmp-play-pause { color: #ffc107; }
        #lmp-play-pause:hover { background-color: #ffc107 !important; color: #fff !important; }
        #lmp-play-backward,
        #lmp-play-forward { color: #007bff; }
        #lmp-play-backward:hover,
        #lmp-play-forward:hover { background-color: #007bff !important; color: #fff !important; }
        #lmp-play-backward:disabled,
        #lmp-play-forward:disabled,
        #lmp-play-backward:disabled:hover,
        #lmp-play-forward:disabled:hover { color: #adb5bd; background-color: #f8f9fa !important; }
        .lmp-std-filter,
        .lmp-missing-filter { cursor: pointer; }
        .lmp-std-filter.is-active,
        .lmp-missing-filter.is-active { outline: 3px solid #ffc107; outline-offset: 2px; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'LMP Overall',
        'sub_title' => "LMP's Master",
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-column gap-2 mb-2" id="lmp-overall-toolbar">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                        <div class="btn-group" role="group" aria-label="Parent navigation" id="lmp-overall-play">
                            <button type="button" id="lmp-play-backward" class="btn btn-sm btn-light" title="Previous parent" disabled>
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button type="button" id="lmp-play-pause" class="btn btn-sm btn-light" title="Stop navigation and show all" style="display: none;">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button type="button" id="lmp-play-auto" class="btn btn-sm btn-light" title="Start parent navigation">
                                <i class="fas fa-play"></i>
                            </button>
                            <button type="button" id="lmp-play-forward" class="btn btn-sm btn-light" title="Next parent" disabled>
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>
                        <span id="lmp-overall-total" class="badge fs-6 p-2 bg-secondary">Total: —</span>
                        <span id="lmp-overall-selected" class="badge fs-6 p-2 bg-primary">Selected: 0</span>
                        <input type="search" id="lmp-overall-search-parent" class="form-control form-control-sm"
                            placeholder="Search parent" autocomplete="off" style="width: 150px;">
                        <input type="search" id="lmp-overall-search-sku" class="form-control form-control-sm"
                            placeholder="Search SKU" autocomplete="off" style="width: 140px;">
                        <select id="lmp-overall-inv-filter" class="form-select form-select-sm" aria-label="INV"
                            style="width: 110px;">
                            <option value="lt1">inv &lt; 1</option>
                            <option value="gt0">Inv &gt; 0</option>
                            <option value="all" selected>Inv = All</option>
                        </select>
                        <select id="lmp-overall-row-filter" class="form-select form-select-sm" aria-label="Row type"
                            style="width: 100px;">
                            <option value="sku">SKU</option>
                            <option value="parent">Parent</option>
                            <option value="both" selected>Both</option>
                        </select>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="badge fs-6 p-2 lmp-std-filter" data-band="high" data-field="lmp_amz" role="button" tabindex="0"
                            style="background-color:#6f42c1;color:#fff;font-weight:700;"
                            title="SKU rows where LMP amz is above 120% of Std Price. Click to filter. Click again to clear.">
                            <i class="ri-alert-fill"></i> LMP amz <span id="lmp-badge-high-lmp_amz">0</span>
                        </span>
                        <span class="badge fs-6 p-2 lmp-std-filter" data-band="high" data-field="lmp_ebay" role="button" tabindex="0"
                            style="background-color:#6f42c1;color:#fff;font-weight:700;"
                            title="SKU rows where LMP ebay is above 120% of Std Price. Click to filter. Click again to clear.">
                            <i class="ri-alert-fill"></i> LMP ebay <span id="lmp-badge-high-lmp_ebay">0</span>
                        </span>
                        <span class="badge fs-6 p-2 lmp-std-filter" data-band="high" data-field="lmp_temu" role="button" tabindex="0"
                            style="background-color:#6f42c1;color:#fff;font-weight:700;"
                            title="SKU rows where LMP temu is above 120% of Std Price. Click to filter. Click again to clear.">
                            <i class="ri-alert-fill"></i> LMP temu <span id="lmp-badge-high-lmp_temu">0</span>
                        </span>
                        <span class="badge fs-6 p-2 lmp-std-filter" data-band="high" data-field="lmp_google" role="button" tabindex="0"
                            style="background-color:#6f42c1;color:#fff;font-weight:700;"
                            title="SKU rows where LMP Google is above 120% of Std Price. Click to filter. Click again to clear.">
                            <i class="ri-alert-fill"></i> LMP Google <span id="lmp-badge-high-lmp_google">0</span>
                        </span>
                        <span class="badge fs-6 p-2 lmp-std-filter" data-band="low" data-field="lmp_amz" role="button" tabindex="0"
                            style="background-color:#dc3545;color:#fff;font-weight:700;"
                            title="SKU rows where LMP amz is below 80% of Std Price. Click to filter. Click again to clear.">
                            <i class="ri-alert-fill"></i> LMP amz <span id="lmp-badge-low-lmp_amz">0</span>
                        </span>
                        <span class="badge fs-6 p-2 lmp-std-filter" data-band="low" data-field="lmp_ebay" role="button" tabindex="0"
                            style="background-color:#dc3545;color:#fff;font-weight:700;"
                            title="SKU rows where LMP ebay is below 80% of Std Price. Click to filter. Click again to clear.">
                            <i class="ri-alert-fill"></i> LMP ebay <span id="lmp-badge-low-lmp_ebay">0</span>
                        </span>
                        <span class="badge fs-6 p-2 lmp-std-filter" data-band="low" data-field="lmp_temu" role="button" tabindex="0"
                            style="background-color:#dc3545;color:#fff;font-weight:700;"
                            title="SKU rows where LMP temu is below 80% of Std Price. Click to filter. Click again to clear.">
                            <i class="ri-alert-fill"></i> LMP temu <span id="lmp-badge-low-lmp_temu">0</span>
                        </span>
                        <span class="badge fs-6 p-2 lmp-std-filter" data-band="low" data-field="lmp_google" role="button" tabindex="0"
                            style="background-color:#dc3545;color:#fff;font-weight:700;"
                            title="SKU rows where LMP Google is below 80% of Std Price. Click to filter. Click again to clear.">
                            <i class="ri-alert-fill"></i> LMP Google <span id="lmp-badge-low-lmp_google">0</span>
                        </span>
                        <span class="badge fs-6 p-2 lmp-missing-filter" data-field="lmp_amz" role="button" tabindex="0"
                            style="background-color:#dc3545;color:#fff;font-weight:700;"
                            title="INV &gt; 0 SKU rows with no LMP amz. Click to filter. Click again to clear.">
                            <i class="ri-alert-fill"></i> LMP M. amz <span id="lmp-missing-lmp_amz">0</span>
                        </span>
                        <span class="badge fs-6 p-2 lmp-missing-filter" data-field="lmp_ebay" role="button" tabindex="0"
                            style="background-color:#dc3545;color:#fff;font-weight:700;"
                            title="INV &gt; 0 SKU rows with no LMP ebay. Click to filter. Click again to clear.">
                            <i class="ri-alert-fill"></i> LMP M. ebay <span id="lmp-missing-lmp_ebay">0</span>
                        </span>
                        <span class="badge fs-6 p-2 lmp-missing-filter" data-field="lmp_temu" role="button" tabindex="0"
                            style="background-color:#dc3545;color:#fff;font-weight:700;"
                            title="INV &gt; 0 SKU rows with no LMP temu. Click to filter. Click again to clear.">
                            <i class="ri-alert-fill"></i> LMP M. temu <span id="lmp-missing-lmp_temu">0</span>
                        </span>
                        <span class="badge fs-6 p-2 lmp-missing-filter" data-field="lmp_google" role="button" tabindex="0"
                            style="background-color:#dc3545;color:#fff;font-weight:700;"
                            title="INV &gt; 0 SKU rows with no LMP Google. Click to filter. Click again to clear.">
                            <i class="ri-alert-fill"></i> LMP M. Google <span id="lmp-missing-lmp_google">0</span>
                        </span>
                        </div>
                    </div>
                    <div id="lmp-overall-wrap">
                        <div id="lmp-overall-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lmpOverallStdModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Std Price — <span id="lmp-overall-std-sku"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label small fw-bold" for="lmp-overall-std-input">Std Price</label>
                    <input type="number" class="form-control" id="lmp-overall-std-input" step="0.01" min="0.01" placeholder="0.00">
                    <label class="form-label small fw-bold mt-2" for="lmp-overall-my-lmp-input">My LMP</label>
                    <input type="number" class="form-control" id="lmp-overall-my-lmp-input" step="0.01" min="0.01" placeholder="0.00">
                    <div class="form-text">Saves Std Price and My LMP for this SKU and its Sku Link LMP siblings.</div>
                    <div class="small mt-2" id="lmp-overall-std-msg"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="lmp-overall-std-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lmpOverallLmpModal" data-skip-lmp-sp="1" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title mb-0">
                        <i class="ri-shopping-cart-2-line me-1"></i>
                        <span id="lmp-overall-lmp-title">LMP</span>
                    </h5>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <button type="button" id="lmp-ov-pull" class="btn btn-sm btn-light" title="Pull live prices for this SKU">
                            <i class="ri-download-cloud-2-line"></i> Pull
                        </button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="card mb-3 border-primary">
                        <div class="card-body py-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-auto">
                                    <label class="form-label mb-0 small fw-bold" for="lmp-ov-std">Std Prc</label>
                                    <input type="number" class="form-control form-control-sm text-end fw-bold" id="lmp-ov-std"
                                        step="0.01" min="0.01" placeholder="0.00" style="width: 7rem;"
                                        title="Manual Standard Price. Saves to Std Prc for this SKU and Sku Link LMP siblings.">
                                </div>
                                <div class="col-auto">
                                    <div class="small text-muted mb-0">GROI %</div>
                                    <div id="lmp-ov-groi" class="fs-5 fw-bold" style="min-width: 3.5rem;">—</div>
                                </div>
                                <div class="col-auto">
                                    <div class="small text-muted mb-0">NROI %</div>
                                    <div id="lmp-ov-nroi" class="fs-5 fw-bold" style="min-width: 3.5rem;">—</div>
                                </div>
                                <div class="col small text-muted pb-1">
                                    Standard Price (manual). Saves to <strong>Std Prc</strong> for this SKU and all
                                    <strong>Sku Link LMP</strong> siblings. Use when LMP cannot be determined.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3 border-success">
                        <div class="card-body">
                            <form id="lmp-ov-form" class="row g-3 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label mb-1"><strong>SKU</strong></label>
                                    <div id="lmp-ov-sku-control"></div>
                                </div>
                                <div class="col-md-2" id="lmp-ov-id-wrap">
                                    <label class="form-label mb-1" for="lmp-ov-id"><strong id="lmp-ov-id-label">ASIN</strong> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="lmp-ov-id" placeholder="B07ABC123" autocomplete="off">
                                </div>
                                <div class="col-md-2" id="lmp-ov-source-wrap" hidden>
                                    <label class="form-label mb-1" for="lmp-ov-source"><strong>Source</strong></label>
                                    <input type="text" class="form-control" id="lmp-ov-source" placeholder="Store" autocomplete="off">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1" for="lmp-ov-price"><strong>Price</strong> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="lmp-ov-price" placeholder="29.99" step="0.01" min="0.01">
                                </div>
                                <div class="col-md-2" id="lmp-ov-extra-wrap" hidden>
                                    <label class="form-label mb-1" for="lmp-ov-extra"><strong id="lmp-ov-extra-label">Shipping</strong></label>
                                    <input type="number" class="form-control" id="lmp-ov-extra" placeholder="0.00" step="0.01" min="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-1" for="lmp-ov-link"><strong>Product Link</strong></label>
                                    <input type="url" class="form-control" id="lmp-ov-link" placeholder="https://amazon.com/dp/...">
                                </div>
                                <div class="col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary" id="lmp-ov-submit">
                                        <i class="ri-add-line"></i> Add Competitor
                                    </button>
                                    <button type="button" class="btn btn-light" id="lmp-ov-cancel" hidden>Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="small mb-2" id="lmp-ov-msg"></div>
                    <div id="lmp-overall-lmp-list"></div>
                </div>
            </div>
        </div>
    </div>
    <div id="lmp-overall-text-preview" hidden>
        <div class="card shadow">
            <div class="card-header bg-primary text-white d-flex align-items-center py-2">
                <strong id="lmp-ov-preview-title" class="me-auto">Details</strong>
                <button type="button" class="btn-close btn-close-white" id="lmp-ov-preview-close" aria-label="Close"></button>
            </div>
            <div class="card-body" id="lmp-ov-preview-body" style="white-space: pre-wrap;"></div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            function money(value) {
                const n = parseFloat(value);
                if (!isFinite(n) || n <= 0) {
                    return '<span class="text-muted">—</span>';
                }
                return '<span class="lmp-overall-price">$' + n.toFixed(2) + '</span>';
            }

            function countCell(value) {
                return Math.round(parseFloat(value) || 0).toLocaleString('en-US');
            }

            function escHtml(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            const lmpBadgeCols = [
                { field: 'lmp_amz', label: 'LMP amz' },
                { field: 'lmp_ebay', label: 'LMP ebay' },
                { field: 'lmp_temu', label: 'LMP temu' },
                { field: 'lmp_google', label: 'LMP Google' },
            ];

            function lmpStdBand(lmp, std) {
                const price = parseFloat(lmp);
                const base = parseFloat(std);
                if (!isFinite(price) || price <= 0 || !isFinite(base) || base <= 0) return '';
                if (price > base * 1.2) return 'high';
                if (price < base * 0.8) return 'low';
                return '';
            }

            function lmpTriangle(band, title, price, std) {
                const shown = isFinite(price) ? ('$' + price.toFixed(2)) : '';
                const base = parseFloat(std);
                const stdText = isFinite(base) ? ('$' + base.toFixed(2)) : '';
                if (band === 'high') {
                    return ' <i class="ri-alert-fill lmp-std-tri lmp-std-tri-high" title="'
                        + escHtml(title + ' ' + shown + ' is above 120% of Std Price ' + stdText)
                        + '"></i>';
                }
                if (band === 'low') {
                    return ' <i class="ri-alert-fill lmp-std-tri lmp-std-tri-low" title="'
                        + escHtml(title + ' ' + shown + ' is below 80% of Std Price ' + stdText)
                        + '"></i>';
                }
                return '';
            }

            function lmpCell(price, count, std, title) {
                const n = parseFloat(price);
                const c = parseInt(count, 10) || 0;
                if ((!isFinite(n) || n <= 0) && c === 0) {
                    return '<span class="text-muted">—</span>';
                }
                let html = '';
                if (isFinite(n) && n > 0) {
                    html += '<span class="lmp-overall-price">$' + n.toFixed(2) + '</span>';
                    html += lmpTriangle(lmpStdBand(n, std), title, n, std);
                }
                if (c > 0) {
                    html += ' <a href="#" class="lmp-overall-count" title="View ' + c
                        + ' competitor' + (c === 1 ? '' : 's') + '">(' + c + ')</a>';
                }
                return html;
            }

            function lmpSiteColumn(title, field, countField) {
                return {
                    title: title,
                    field: field,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    minWidth: 108,
                    sorter: 'number',
                    formatter: function (cell) {
                        const row = cell.getRow().getData();
                        return lmpCell(cell.getValue(), row[countField], row.std_price, title);
                    },
                    cellClick: function (e, cell) {
                        e.preventDefault();
                        e.stopPropagation();
                        openLmpModal(cell.getRow().getData(), field);
                    },
                };
            }

            const lmpModalEl = document.getElementById('lmpOverallLmpModal');

            function showBsModal(el) {
                if (!el || !window.bootstrap || !bootstrap.Modal) return null;
                if (el.parentElement !== document.body) document.body.appendChild(el);
                const modal = bootstrap.Modal.getOrCreateInstance(el);
                modal.show();
                return modal;
            }

            function competitorPrice(row) {
                const landed = parseFloat(row.landed_price);
                if (isFinite(landed) && landed > 0) return landed;
                const total = parseFloat(row.total_price);
                if (isFinite(total) && total > 0) return total;
                const base = parseFloat(row.price) || 0;
                const ship = parseFloat(row.shipping_cost);
                if (isFinite(ship) && ship > 0) return base + ship;
                return base;
            }

            const LMP_OV_ADS_PCT = {{ (float) ($amazonAdsPercent ?? 0) }};
            const lmpSites = {
                lmp_amz: {
                    label: 'LMP amz', kind: 'amazon', url: '/amazon/competitors', pull: true,
                    idLabel: 'ASIN', idPlaceholder: 'B07ABC123', linkPlaceholder: 'https://amazon.com/dp/...',
                },
                lmp_ebay: {
                    label: 'LMP ebay', kind: 'ebay', url: '/ebay-lmp-data', pull: true,
                    idLabel: 'Item ID', idPlaceholder: '123456789012', linkPlaceholder: 'https://www.ebay.com/itm/...',
                    extraLabel: 'Shipping',
                },
                lmp_temu: {
                    label: 'LMP temu', kind: 'temu', url: '/cvr-master-temu-lmp', pull: false,
                    linkPlaceholder: 'https://www.temu.com/...', extraLabel: 'Delivery',
                },
                lmp_google: {
                    label: 'LMP Google', kind: 'google', url: '/google-lmp-data', pull: true,
                    idLabel: 'Product ID', idPlaceholder: 'Product ID', linkPlaceholder: 'https://www.google.com/shopping/...',
                },
            };
            const lmpState = { field: null, row: null, bySku: {}, competitors: [], editing: null, loadGen: 0, stdSilent: false };
            const lmpCountFields = {
                lmp_amz: 'lmp_amz_count',
                lmp_ebay: 'lmp_ebay_count',
                lmp_temu: 'lmp_temu_count',
                lmp_google: 'lmp_google_count',
            };

            function csrfToken() {
                const meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.getAttribute('content') : '';
            }

            function round2(n) {
                return Math.round(n * 100) / 100;
            }

            function dash() {
                return '<span class="text-muted">—</span>';
            }

            function setMsg(text, kind) {
                const el = document.getElementById('lmp-ov-msg');
                if (!el) return;
                el.className = 'small mb-2 ' + (kind === 'error' ? 'text-danger' : (kind === 'ok' ? 'text-success' : 'text-muted'));
                el.textContent = text || '';
            }

            function postJson(url, body) {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body),
                }).then(function (r) {
                    return r.json().catch(function () { return {}; }).then(function (data) {
                        if (!r.ok || (data && data.success === false)) {
                            let msg = (data && (data.error || data.message)) || 'Request failed';
                            const bag = data && (data.messages || data.errors);
                            if (bag && typeof bag === 'object') {
                                const first = Object.values(bag).flat()[0];
                                if (first) msg = first;
                            }
                            if (r.status === 409 && data && data.error) msg = data.error;
                            throw new Error(msg);
                        }
                        return data;
                    });
                });
            }

            function activeSku() {
                const el = document.getElementById('lmp-ov-sku');
                return el ? String(el.value || '').trim() : '';
            }

            function metricRow() {
                const sku = activeSku();
                if (sku && typeof table !== 'undefined' && table) {
                    const row = table.getRow(sku);
                    if (row) return row.getData();
                }
                return lmpState.row || {};
            }

            function groiAt(sp, row) {
                const price = parseFloat(sp);
                const lp = parseFloat(row && row.lp);
                if (!(price > 0) || !(lp > 0)) return null;
                const ship = parseFloat(row.ship) || 0;
                return ((price * 0.80 - ship - lp) / lp) * 100;
            }

            function nroiAt(sp, row) {
                const price = parseFloat(sp);
                const lp = parseFloat(row && row.lp);
                if (!(price > 0) || !(lp > 0)) return null;
                const ship = parseFloat(row.ship) || 0;
                const gross = (price * 0.80) - ship - lp;
                const ads = price * ((parseFloat(LMP_OV_ADS_PCT) || 0) / 100);
                return ((gross - ads) / lp) * 100;
            }

            function stdNroiAt(sp, row) {
                const price = parseFloat(sp);
                const lp = parseFloat(row && row.lp);
                if (!(price > 0) || !(lp > 0)) return null;
                const ship = parseFloat(row.ship) || 0;
                const gross = (price * 0.70) - ship - lp;
                return (gross / lp) * 100;
            }

            function stdNpftAt(sp, row) {
                const price = parseFloat(sp);
                if (!(price > 0)) return null;
                const lp = parseFloat(row && row.lp);
                const ship = parseFloat(row && row.ship) || 0;
                const lpVal = (isFinite(lp) && lp > 0) ? lp : 0;
                return ((price * 0.70 - ship - lpVal) / price) * 100;
            }

            function stdMarginFields(std, row) {
                const nroi = stdNroiAt(std, row);
                const npft = stdNpftAt(std, row);
                return {
                    std_nroi: (nroi == null || !isFinite(nroi)) ? null : round2(nroi),
                    std_npft: (npft == null || !isFinite(npft)) ? null : round2(npft),
                };
            }

            function pctHtml(kind, value) {
                if (value == null || !isFinite(value)) return dash();
                if (window.MetricPctColors && typeof MetricPctColors.htmlFor === 'function') {
                    const html = MetricPctColors.htmlFor(kind, value, { decimals: 0, empty: '' });
                    if (html) return html;
                }
                return '<span style="font-weight:600;">' + Math.round(value) + '%</span>';
            }

            function refreshStdMetrics() {
                const sp = parseFloat(document.getElementById('lmp-ov-std').value);
                const row = metricRow();
                const groi = groiAt(sp, row);
                const nroi = nroiAt(sp, row);
                document.getElementById('lmp-ov-groi').innerHTML = pctHtml('groi', groi);
                document.getElementById('lmp-ov-nroi').innerHTML = pctHtml('nroi', nroi);
                const text = (isFinite(sp) && sp > 0) ? ('$' + sp.toFixed(2)) : '—';
                const groiHtml = pctHtml('groi', groi);
                const nroiHtml = pctHtml('nroi', nroi);
                document.querySelectorAll('#lmpOverallLmpModal .lmp-sp-cell').forEach(function (el) { el.textContent = text; });
                document.querySelectorAll('#lmpOverallLmpModal .lmp-groi-cell').forEach(function (el) { el.innerHTML = groiHtml; });
                document.querySelectorAll('#lmpOverallLmpModal .lmp-nroi-cell').forEach(function (el) { el.innerHTML = nroiHtml; });
            }

            function setStdInput(value) {
                lmpState.stdSilent = true;
                const n = parseFloat(value);
                document.getElementById('lmp-ov-std').value = (isFinite(n) && n > 0) ? n.toFixed(2) : '';
                refreshStdMetrics();
                lmpState.stdSilent = false;
            }

            function skuChoices(row) {
                if (!row || !row.is_parent_summary) {
                    const sku = String((row && row.sku) || '').trim();
                    return sku ? [sku] : [];
                }
                return table.getData().filter(function (data) {
                    return !data.is_parent_summary && data.parent === row.parent;
                }).map(function (data) {
                    return String(data.sku || '').trim();
                }).filter(Boolean);
            }

            function competitorSkus(row, field) {
                if (!row.is_parent_summary) {
                    const sku = String(row.sku || '').trim();
                    return sku ? [sku] : [];
                }
                const countField = lmpCountFields[field];
                return table.getData().filter(function (data) {
                    return !data.is_parent_summary
                        && data.parent === row.parent
                        && (parseInt(data[countField], 10) || 0) > 0;
                }).map(function (data) {
                    return String(data.sku || '').trim();
                }).filter(Boolean);
            }

            function fillSkuControl(row) {
                const wrap = document.getElementById('lmp-ov-sku-control');
                const choices = skuChoices(row);
                if (row.is_parent_summary) {
                    wrap.innerHTML = '<select class="form-select" id="lmp-ov-sku">'
                        + (choices.length ? choices.map(function (sku) {
                            return '<option value="' + escHtml(sku) + '">' + escHtml(sku) + '</option>';
                        }).join('') : '<option value="">No child SKU</option>')
                        + '</select>';
                    return;
                }
                wrap.innerHTML = '<input type="text" class="form-control" id="lmp-ov-sku" readonly value="'
                    + escHtml(row.sku || '') + '">';
            }

            function configureSite(site) {
                document.getElementById('lmp-ov-id-label').textContent = site.idLabel || 'ID';
                document.getElementById('lmp-ov-id').placeholder = site.idPlaceholder || '';
                document.getElementById('lmp-ov-link').placeholder = site.linkPlaceholder || 'https://';
                document.getElementById('lmp-ov-id-wrap').hidden = site.kind === 'temu';
                document.getElementById('lmp-ov-source-wrap').hidden = site.kind !== 'google';
                const extra = document.getElementById('lmp-ov-extra-wrap');
                extra.hidden = !(site.kind === 'ebay' || site.kind === 'temu');
                if (site.extraLabel) document.getElementById('lmp-ov-extra-label').textContent = site.extraLabel;
                document.getElementById('lmp-ov-pull').hidden = !site.pull;
            }

            function resetForm() {
                lmpState.editing = null;
                ['lmp-ov-id', 'lmp-ov-price', 'lmp-ov-link', 'lmp-ov-extra', 'lmp-ov-source'].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                document.getElementById('lmp-ov-submit').innerHTML = '<i class="ri-add-line"></i> Add Competitor';
                document.getElementById('lmp-ov-cancel').hidden = true;
            }

            function enterEdit(item) {
                lmpState.editing = item;
                const skuEl = document.getElementById('lmp-ov-sku');
                const sku = item._sku || item.sku || item.source_sku || '';
                if (skuEl && skuEl.tagName === 'SELECT' && sku) {
                    Array.from(skuEl.options).forEach(function (opt) {
                        if (opt.value === sku) skuEl.value = sku;
                    });
                }
                document.getElementById('lmp-ov-id').value = item.asin || item.item_id || item.product_id || '';
                const price = parseFloat(item.price);
                document.getElementById('lmp-ov-price').value = price > 0 ? price.toFixed(2) : '';
                document.getElementById('lmp-ov-link').value = item.product_link || item.link || '';
                const extra = item.shipping_cost != null && item.shipping_cost !== '' ? item.shipping_cost : item.delivery;
                const extraN = parseFloat(extra);
                document.getElementById('lmp-ov-extra').value = extraN > 0 ? extraN.toFixed(2) : '';
                document.getElementById('lmp-ov-source').value = item.source || '';
                document.getElementById('lmp-ov-submit').innerHTML = '<i class="ri-pencil-line"></i> Update Competitor';
                document.getElementById('lmp-ov-cancel').hidden = false;
                document.getElementById('lmp-ov-price').focus();
            }

            function fetchCompetitors(site, sku, linked, refresh) {
                const params = new URLSearchParams();
                params.set('sku', sku);
                if (refresh) params.set('refresh', '1');
                (Array.isArray(linked) ? linked : []).forEach(function (item) {
                    params.append('linked_lmp_skus[]', item);
                });
                return fetch(site.url + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                }).then(function (r) {
                    return r.json().then(function (body) {
                        if (!r.ok) throw new Error((body && (body.error || body.message)) || 'Failed to load competitors');
                        return body;
                    });
                }).then(function (body) {
                    const raw = body && (body.competitors || body.data) ? (body.competitors || body.data) : [];
                    return Array.isArray(raw) ? raw : Object.values(raw || {});
                });
            }

            function lowestPrice(list) {
                let best = null;
                (list || []).forEach(function (item) {
                    if (item.ignored) return;
                    const price = competitorPrice(item);
                    if (price > 0 && (best === null || price < best)) best = price;
                });
                return best;
            }

            function refreshParentSummaries() {
                if (typeof table === 'undefined' || !table) return;
                const kidsByParent = {};
                table.getData().forEach(function (row) {
                    if (row.is_parent_summary || !row.parent) return;
                    (kidsByParent[row.parent] = kidsByParent[row.parent] || []).push(row);
                });
                const updates = [];
                table.getData().forEach(function (row) {
                    if (!row.is_parent_summary) return;
                    const kids = kidsByParent[row.parent] || [];
                    const next = { sku: row.sku };
                    ['std_price', 'my_lmp', 'lmp_amz', 'lmp_ebay', 'lmp_temu', 'lmp_google', 'lp'].forEach(function (field) {
                        const vals = kids.map(function (kid) { return parseFloat(kid[field]); })
                            .filter(function (n) { return isFinite(n) && n > 0; });
                        next[field] = vals.length ? round2(vals.reduce(function (a, b) { return a + b; }, 0) / vals.length) : null;
                    });
                    ['std_nroi', 'std_npft'].forEach(function (field) {
                        const vals = kids.map(function (kid) { return parseFloat(kid[field]); })
                            .filter(function (n) { return isFinite(n); });
                        next[field] = vals.length ? round2(vals.reduce(function (a, b) { return a + b; }, 0) / vals.length) : null;
                    });
                    ['lmp_amz_count', 'lmp_ebay_count', 'lmp_temu_count', 'lmp_google_count'].forEach(function (field) {
                        next[field] = kids.reduce(function (sum, kid) { return sum + (parseInt(kid[field], 10) || 0); }, 0);
                    });
                    const prices = ['lmp_amz', 'lmp_ebay', 'lmp_temu', 'lmp_google']
                        .map(function (field) { return parseFloat(next[field]); })
                        .filter(function (n) { return isFinite(n) && n > 0; });
                    next.ov_lmp = prices.length ? round2(Math.min.apply(null, prices)) : null;
                    next.avg_lmp = prices.length ? round2(prices.reduce(function (a, b) { return a + b; }, 0) / prices.length) : null;
                    updates.push(next);
                });
                if (updates.length) table.updateData(updates);
                updateLmpStdBadges();
            }

            function syncGrid() {
                if (typeof table === 'undefined' || !table || !lmpState.field) return;
                const field = lmpState.field;
                const countField = lmpCountFields[field];
                const updates = [];
                Object.keys(lmpState.bySku).forEach(function (sku) {
                    const row = table.getRow(sku);
                    const data = row ? row.getData() : null;
                    if (!data || data.is_parent_summary) return;
                    const l1 = lowestPrice(lmpState.bySku[sku]);
                    const prices = ['lmp_amz', 'lmp_ebay', 'lmp_temu', 'lmp_google'].map(function (key) {
                        return key === field ? l1 : parseFloat(data[key]);
                    }).filter(function (n) { return isFinite(n) && n > 0; });
                    const patch = { sku: data.sku };
                    patch[field] = l1;
                    patch[countField] = (lmpState.bySku[sku] || []).length;
                    patch.ov_lmp = prices.length ? round2(Math.min.apply(null, prices)) : null;
                    patch.avg_lmp = prices.length ? round2(prices.reduce(function (a, b) { return a + b; }, 0) / prices.length) : null;
                    updates.push(patch);
                });
                if (updates.length) table.updateData(updates);
                refreshParentSummaries();
            }

            function loadLists(refresh) {
                const site = lmpSites[lmpState.field];
                const gen = ++lmpState.loadGen;
                const skus = competitorSkus(lmpState.row, lmpState.field);
                const linked = lmpState.row && lmpState.row.is_parent_summary ? [] : ((lmpState.row && lmpState.row.linked_lmp_skus) || []);
                if (!skus.length) {
                    lmpState.bySku = {};
                    lmpState.competitors = [];
                    renderList();
                    return Promise.resolve();
                }
                document.getElementById('lmp-overall-lmp-list').innerHTML =
                    '<div class="text-center text-muted py-4">' + (refresh ? 'Pulling live prices…' : 'Loading competitors…') + '</div>';
                return Promise.all(skus.map(function (sku) {
                    return fetchCompetitors(site, sku, linked, refresh).then(function (list) {
                        list.forEach(function (item) {
                            if (!item._sku) item._sku = item.sku || item.source_sku || sku;
                        });
                        return { sku: sku, list: list };
                    }).catch(function () { return { sku: sku, list: [] }; });
                })).then(function (groups) {
                    if (gen !== lmpState.loadGen) return;
                    const bySku = {};
                    const seen = {};
                    const merged = [];
                    groups.forEach(function (group) {
                        bySku[group.sku] = group.list;
                        group.list.forEach(function (item) {
                            const asin = String(item.asin || '').toUpperCase();
                            const itemId = String(item.item_id || '');
                            const productId = String(item.product_id || '');
                            const key = asin ? ('asin:' + asin)
                                : (itemId ? ('item:' + itemId)
                                    : (productId ? ('pid:' + productId + '|' + String(item.source || ''))
                                        : [item._sku, item.id, item.product_link || item.link, competitorPrice(item)].join('|')));
                            if (seen[key]) return;
                            seen[key] = true;
                            merged.push(item);
                        });
                    });
                    lmpState.bySku = bySku;
                    lmpState.competitors = merged.sort(function (a, b) { return competitorPrice(a) - competitorPrice(b); });
                    renderList();
                    syncGrid();
                    if (refresh) setMsg('Pulled live prices.', 'ok');
                });
            }

            function previewBtn(label, text) {
                const raw = String(text == null ? '' : text).trim();
                if (!raw || raw === '—' || raw === 'N/A') return dash();
                return '<button type="button" class="lmp-ov-preview-btn" data-label="' + escHtml(label)
                    + '" data-text="' + escHtml(raw) + '" title="View full ' + escHtml(label) + '"><i class="ri-search-line"></i></button>';
            }

            function amazonShipCost(item) {
                if (!item || !item.delivery) return 0;
                const delText = String(item.delivery);
                if (/\bfree\b/i.test(delText)) return 0;
                const paid = delText.match(/\$\s*([\d,]+\.?\d*)\s*delivery/i) || delText.match(/\$\s*([\d,]+\.?\d*)/);
                return paid ? (parseFloat(String(paid[1]).replace(/,/g, '')) || 0) : 0;
            }

            function priceCell(item, isLowest, ignored) {
                const site = lmpSites[lmpState.field];
                const base = parseFloat(item.price) || 0;
                let ship = 0;
                if (site.kind === 'amazon') ship = amazonShipCost(item);
                else if (site.kind === 'ebay') ship = parseFloat(item.shipping_cost) || 0;
                else if (site.kind === 'temu') ship = parseFloat(item.delivery != null ? item.delivery : item.shipping_cost) || 0;
                const total = competitorPrice(item);
                const shown = total > 0 ? total : base;
                const inner = ship > 0
                    ? ('$' + shown.toFixed(2) + '<br><small class="fw-normal">$' + base.toFixed(2) + ' + $' + ship.toFixed(2) + ' ship</small>')
                    : ('$' + shown.toFixed(2));
                if (isLowest) return '<span class="badge bg-success">' + inner + ' <i class="ri-trophy-line"></i></span>';
                if (ignored) return '<strong>' + inner + '</strong> <span class="badge bg-secondary">Ignored</span>';
                return '<strong class="text-success">' + inner + '</strong>';
            }

            function deliveryCell(item) {
                if (item.delivery == null || item.delivery === '') return dash();
                const delText = String(item.delivery);
                if (siteIsAmazon() && /\bfree\b/i.test(delText)) {
                    return '<span class="text-success fw-bold" title="' + escHtml(delText) + '">0</span>';
                }
                const paid = delText.match(/\$\s*([\d,]+\.?\d*)\s*delivery/i);
                if (paid) return '<span class="text-danger fw-bold" title="' + escHtml(delText) + '">$' + escHtml(paid[1]) + ' ship</span>';
                const n = parseFloat(delText);
                if (isFinite(n) && n >= 0 && String(delText).trim() === String(n)) {
                    return n === 0 ? '<span class="text-success fw-bold">0</span>' : '<span class="fw-bold">$' + n.toFixed(2) + '</span>';
                }
                const short = delText.length > 22 ? delText.slice(0, 22) + '…' : delText;
                return '<span title="' + escHtml(delText) + '">' + escHtml(short) + '</span>';
            }

            function siteIsAmazon() {
                return lmpSites[lmpState.field] && lmpSites[lmpState.field].kind === 'amazon';
            }

            function stockCell(item) {
                const stockText = item.stock != null ? String(item.stock).trim() : '';
                const qty = item.stock_quantity != null && item.stock_quantity !== '' ? parseInt(item.stock_quantity, 10) : NaN;
                if (!stockText && !isFinite(qty)) return dash();
                const tip = escHtml(stockText || (isFinite(qty) ? String(qty) : ''));
                if (isFinite(qty)) {
                    const color = qty === 0 ? '#dc3545' : (qty <= 5 ? '#dc3545' : (qty <= 20 ? '#ffc107' : '#198754'));
                    return '<span style="color:' + color + ';font-weight:700;" title="' + tip + '">' + qty + '</span>';
                }
                if (/\bout\s+of\s+stock\b/i.test(stockText)) return '<span class="text-danger fw-bold" title="' + tip + '">OOS</span>';
                if (/\bin\s+stock\b/i.test(stockText)) return '<span class="text-success fw-bold" title="' + tip + '">In Stock</span>';
                return '<span title="' + tip + '">' + escHtml(stockText.length > 18 ? stockText.slice(0, 18) + '…' : stockText) + '</span>';
            }

            function actionCells(index, item) {
                const link = item.product_link || item.link || '';
                const ignored = !!item.ignored;
                const linkHtml = link
                    ? '<a href="' + escHtml(link) + '" target="_blank" rel="noopener" class="btn btn-sm btn-info" title="Open product"><i class="ri-external-link-line"></i></a>'
                    : dash();
                return '<td class="text-center">' + linkHtml + '</td>'
                    + '<td class="text-center lmp-ignore-cell"><input type="checkbox" class="form-check-input lmp-ov-ignore" data-idx="'
                    + index + '"' + (ignored ? ' checked' : '') + ' title="Ignore for L1"></td>'
                    + '<td class="text-center text-nowrap lmp-actions-cell">'
                    + '<button type="button" class="btn btn-sm btn-warning lmp-ov-edit me-1" data-idx="' + index + '" title="Edit this competitor"><i class="ri-pencil-line"></i></button>'
                    + '<button type="button" class="btn btn-sm btn-danger lmp-ov-delete" data-idx="' + index + '" title="Delete this competitor"><i class="ri-delete-bin-line"></i></button>'
                    + '</td>';
            }

            function metricCells() {
                const sp = parseFloat(document.getElementById('lmp-ov-std').value);
                const row = metricRow();
                const text = (isFinite(sp) && sp > 0) ? ('$' + sp.toFixed(2)) : '—';
                return '<td class="text-center fw-bold lmp-sp-cell">' + text + '</td>'
                    + '<td class="text-center lmp-groi-cell">' + pctHtml('groi', groiAt(sp, row)) + '</td>'
                    + '<td class="text-center lmp-nroi-cell">' + pctHtml('nroi', nroiAt(sp, row)) + '</td>';
            }

            function renderList() {
                const list = lmpState.competitors || [];
                const box = document.getElementById('lmp-overall-lmp-list');
                const kind = (lmpSites[lmpState.field] || {}).kind;
                if (!list.length) {
                    box.innerHTML = '<div class="alert alert-warning mb-0"><i class="ri-information-line"></i> No competitors found yet. Add your first competitor above.</div>';
                    return;
                }
                const l1 = lowestPrice(list);
                const head = {
                    amazon: ['#', 'Image', 'ASIN', 'Title', 'Seller', 'Price', 'Std Prc', 'GROI %', 'NROI %', 'Revenue (30d)', 'Units (30d)', 'Buy Box', 'Type', 'Rating', 'Reviews', 'Delivery', 'Inv', 'Link', 'Ignore', 'Actions'],
                    ebay: ['#', 'Image', 'Item ID', 'Title', 'Price', 'Ship', 'Std Prc', 'GROI %', 'NROI %', 'Link', 'Ignore', 'Actions'],
                    google: ['#', 'Image', 'Product ID', 'Source', 'Title', 'Price', 'Rating', 'Reviews', 'Std Prc', 'GROI %', 'NROI %', 'Link', 'Ignore', 'Actions'],
                    temu: ['#', 'Price', 'Delivery', 'Std Prc', 'GROI %', 'NROI %', 'Link', 'Ignore', 'Actions'],
                }[kind] || [];
                let html = '';
                if (l1 != null) {
                    html += '<div class="small text-muted mb-2">L1 (lowest non-ignored): <strong>$' + Number(l1).toFixed(2) + '</strong></div>';
                }
                html += '<div class="table-responsive"><table class="table table-bordered table-sm align-middle mb-0"><thead><tr>'
                    + head.map(function (label) { return '<th class="text-center">' + label + '</th>'; }).join('')
                    + '</tr></thead><tbody>';
                list.forEach(function (item, index) {
                    const ignored = !!item.ignored;
                    const total = competitorPrice(item);
                    const isLowest = !ignored && l1 != null && Math.abs(total - l1) < 0.01;
                    const rowClass = (ignored ? 'lmp-ignored-row ' : '') + (isLowest ? 'lmp-lowest-row' : '');
                    const img = item.image
                        ? '<img class="lmp-overall-comp-img" src="' + escHtml(item.image) + '" alt="">'
                        : dash();
                    let cells = '<td class="text-center"><strong>' + (index + 1) + '</strong></td>';
                    if (kind === 'amazon') {
                        const revenue = item.monthly_revenue ? '<span class="text-success fw-bold">$' + Math.round(parseFloat(item.monthly_revenue)) + '</span>' : dash();
                        const units = item.monthly_units_sold ? '<span class="text-primary fw-bold">' + parseInt(item.monthly_units_sold, 10) + '</span>' : dash();
                        const rating = item.rating ? '<span class="text-warning">' + parseFloat(item.rating).toFixed(1) + '</span>' : dash();
                        const reviews = item.reviews ? parseInt(item.reviews, 10).toLocaleString() : dash();
                        const type = item.seller_type
                            ? '<span class="badge bg-' + (item.seller_type === 'FBA' ? 'warning' : 'secondary') + '">' + escHtml(item.seller_type) + '</span>'
                            : dash();
                        cells += '<td class="text-center">' + img + '</td>'
                            + '<td><span class="text-primary fw-bold">' + escHtml(item.asin || 'N/A') + '</span></td>'
                            + '<td class="text-center">' + previewBtn('Product Title', item.product_title || item.title) + '</td>'
                            + '<td class="text-center">' + previewBtn('Seller', item.seller_name) + '</td>'
                            + '<td>' + priceCell(item, isLowest, ignored) + '</td>'
                            + metricCells()
                            + '<td class="text-center">' + revenue + '</td>'
                            + '<td class="text-center">' + units + '</td>'
                            + '<td class="text-center">' + (item.buy_box_owner ? escHtml(item.buy_box_owner) : dash()) + '</td>'
                            + '<td class="text-center">' + type + '</td>'
                            + '<td class="text-center">' + rating + '</td>'
                            + '<td class="text-center">' + reviews + '</td>'
                            + '<td class="text-center">' + deliveryCell(item) + '</td>'
                            + '<td class="text-center">' + stockCell(item) + '</td>';
                    } else if (kind === 'ebay') {
                        const ship = parseFloat(item.shipping_cost) || 0;
                        cells += '<td class="text-center">' + img + '</td>'
                            + '<td>' + escHtml(item.item_id || '—') + '</td>'
                            + '<td class="text-center">' + previewBtn('Product Title', item.title || item.product_title) + '</td>'
                            + '<td>' + priceCell(item, isLowest, ignored) + '</td>'
                            + '<td class="text-center">' + (ship > 0 ? ('$' + ship.toFixed(2)) : '<span class="badge bg-info">FREE</span>') + '</td>'
                            + metricCells();
                    } else if (kind === 'google') {
                        const rating = item.rating ? parseFloat(item.rating).toFixed(1) : dash();
                        const reviews = item.reviews ? parseInt(item.reviews, 10).toLocaleString() : dash();
                        cells += '<td class="text-center">' + img + '</td>'
                            + '<td>' + escHtml(item.product_id || '—') + '</td>'
                            + '<td>' + escHtml(item.source || '—') + '</td>'
                            + '<td class="text-center">' + previewBtn('Product Title', item.title || item.product_title) + '</td>'
                            + '<td>' + priceCell(item, isLowest, ignored) + '</td>'
                            + '<td class="text-center">' + rating + '</td>'
                            + '<td class="text-center">' + reviews + '</td>'
                            + metricCells();
                    } else {
                        cells += '<td>' + priceCell(item, isLowest, ignored) + '</td>'
                            + '<td class="text-center">' + deliveryCell(item) + '</td>'
                            + metricCells();
                    }
                    cells += actionCells(index, item);
                    html += '<tr class="' + rowClass + '">' + cells + '</tr>';
                });
                html += '</tbody></table></div>';
                box.innerHTML = html;
            }

            function openPreview(label, text) {
                document.getElementById('lmp-ov-preview-title').textContent = label || 'Details';
                document.getElementById('lmp-ov-preview-body').textContent = text || '';
                document.getElementById('lmp-overall-text-preview').hidden = false;
            }

            function closePreview() {
                document.getElementById('lmp-overall-text-preview').hidden = true;
            }

            function sameCompetitor(a, b) {
                if (String(a.id) === String(b.id) && String(a._sku || a.sku || '') === String(b._sku || b.sku || '')) return true;
                const asin = String(b.asin || '').toUpperCase();
                if (asin && String(a.asin || '').toUpperCase() === asin) return true;
                const itemId = String(b.item_id || '');
                if (itemId && String(a.item_id || '') === itemId) return true;
                return false;
            }

            function applyIgnoreLocal(item, ignored) {
                function mark(list) {
                    (list || []).forEach(function (row) {
                        if (sameCompetitor(row, item)) row.ignored = ignored ? 1 : 0;
                    });
                }
                mark(lmpState.competitors);
                Object.keys(lmpState.bySku).forEach(function (sku) { mark(lmpState.bySku[sku]); });
                renderList();
                syncGrid();
            }

            function temuEntries(list) {
                return (list || []).map(function (item) {
                    return {
                        price: parseFloat(item.price) || 0,
                        delivery: parseFloat(item.delivery != null ? item.delivery : item.shipping_cost) || 0,
                        link: item.link || item.product_link || null,
                        ignored: !!item.ignored,
                        source_sku: item.source_sku || item._sku || null,
                    };
                });
            }

            function mutateTemu(item, mutator) {
                const sku = item._sku || item.source_sku || activeSku();
                const site = lmpSites.lmp_temu;
                return fetchCompetitors(site, sku, [], false).then(function (list) {
                    const entries = temuEntries(list);
                    const idx = entries.findIndex(function (row, i) {
                        const id = 'temu-' + (i + 1);
                        return id === String(item.id)
                            || (Math.abs((parseFloat(row.price) || 0) - (parseFloat(item.price) || 0)) < 0.01
                                && String(row.link || '') === String(item.link || item.product_link || ''));
                    });
                    const next = mutator(entries, idx);
                    if (next === false) throw new Error('Temu competitor was not found. Reload and try again.');
                    return postJson('/temu-lmp/save', { sku: sku, lmp_entries: next });
                });
            }

            function saveCompetitor(event) {
                event.preventDefault();
                const site = lmpSites[lmpState.field];
                if (!site) return;
                const sku = activeSku();
                if (!sku || /^PARENT\b/i.test(sku)) {
                    setMsg('Pick a SKU before adding or updating a competitor.', 'error');
                    return;
                }
                const price = parseFloat(document.getElementById('lmp-ov-price').value);
                const idVal = document.getElementById('lmp-ov-id').value.trim();
                const link = document.getElementById('lmp-ov-link').value.trim();
                const extraRaw = document.getElementById('lmp-ov-extra').value.trim();
                const extra = extraRaw === '' ? 0 : parseFloat(extraRaw);
                const source = document.getElementById('lmp-ov-source').value.trim();
                if (!(price > 0)) {
                    setMsg('Valid price is required.', 'error');
                    return;
                }
                if (site.kind !== 'temu' && !idVal) {
                    setMsg(site.idLabel + ' is required.', 'error');
                    return;
                }
                if (extraRaw !== '' && (!isFinite(extra) || extra < 0)) {
                    setMsg('Shipping must be 0 or more.', 'error');
                    return;
                }
                const editing = lmpState.editing;
                const btn = document.getElementById('lmp-ov-submit');
                btn.disabled = true;
                let req;
                if (site.kind === 'amazon') {
                    req = editing
                        ? postJson('/amazon/lmp/update', { id: editing.id, price: price, product_link: link || null, asin: idVal })
                        : postJson('/amazon/lmp/add', { sku: sku, asin: idVal, price: price, product_link: link || null, marketplace: 'US' });
                } else if (site.kind === 'ebay') {
                    req = editing
                        ? postJson('/ebay-lmp-update', { id: editing.id, price: price, shipping_cost: extra, product_link: link || null, item_id: idVal })
                        : postJson('/ebay-lmp-add', { sku: sku, item_id: idVal, price: price, shipping_cost: extra, product_link: link || null });
                } else if (site.kind === 'google') {
                    req = editing
                        ? postJson('/google-lmp-update', { id: editing.id, price: price, product_link: link || null, product_id: idVal })
                        : postJson('/google-lmp-add', { sku: sku, product_id: idVal, source: source || null, price: price, product_link: link || null });
                } else {
                    const draft = { price: price, delivery: extra, link: link || null, ignored: false, source_sku: sku };
                    req = editing
                        ? mutateTemu(editing, function (entries, idx) {
                            if (idx < 0) return false;
                            entries[idx] = Object.assign({}, entries[idx], draft, { ignored: entries[idx].ignored });
                            return entries;
                        })
                        : fetchCompetitors(lmpSites.lmp_temu, sku, [], false).then(function (list) {
                            const entries = temuEntries(list);
                            entries.push(draft);
                            return postJson('/temu-lmp/save', { sku: sku, lmp_entries: entries });
                        });
                }
                req.then(function () {
                    setMsg(editing ? 'Competitor updated.' : 'Competitor added.', 'ok');
                    resetForm();
                    return loadLists(false);
                }).catch(function (err) {
                    setMsg(err.message || 'Save failed', 'error');
                }).finally(function () {
                    btn.disabled = false;
                });
            }

            function deleteCompetitor(item) {
                const site = lmpSites[lmpState.field];
                const label = item.asin || item.item_id || item.product_id || 'this competitor';
                const price = competitorPrice(item);
                if (!confirm('Delete competitor ' + label + (price > 0 ? ' ($' + price.toFixed(2) + ')' : '') + ' from tracking?')) return;
                let req;
                if (site.kind === 'amazon') req = postJson('/amazon/lmp/delete', { id: item.id });
                else if (site.kind === 'ebay') req = postJson('/ebay-lmp-delete', { id: item.id });
                else if (site.kind === 'google') req = postJson('/google-lmp-delete', { id: item.id });
                else {
                    req = mutateTemu(item, function (entries, idx) {
                        if (idx < 0) return false;
                        entries.splice(idx, 1);
                        return entries;
                    });
                }
                setMsg('Deleting…', '');
                req.then(function () {
                    setMsg('Competitor deleted.', 'ok');
                    if (lmpState.editing && String(lmpState.editing.id) === String(item.id)) resetForm();
                    return loadLists(false);
                }).catch(function (err) {
                    setMsg(err.message || 'Delete failed', 'error');
                });
            }

            function ignoreCompetitor(item, ignored) {
                const site = lmpSites[lmpState.field];
                applyIgnoreLocal(item, ignored);
                const sku = item._sku || item.sku || item.source_sku || activeSku();
                let req;
                if (site.kind === 'amazon') {
                    req = postJson('/amazon/lmp/ignore', { id: item.id, sku: sku, ignored: ignored ? 1 : 0 });
                } else if (site.kind === 'temu') {
                    req = mutateTemu(item, function (entries, idx) {
                        if (idx < 0) return false;
                        entries[idx].ignored = !!ignored;
                        return entries;
                    });
                } else {
                    req = postJson('/cvr-master-lmp-ignore', {
                        marketplace: site.kind,
                        id: item.id,
                        sku: sku,
                        ignored: ignored ? 1 : 0,
                    });
                }
                req.then(function (res) {
                    setMsg((res && res.message) || (ignored ? 'Ignored for L1' : 'Included in L1'), 'ok');
                }).catch(function (err) {
                    applyIgnoreLocal(item, !ignored);
                    setMsg(err.message || 'Failed to update ignore', 'error');
                });
            }

            function saveStd() {
                if (lmpState.stdSilent || !lmpState.row) return;
                const row = metricRow();
                const sku = String(row.sku || '').trim();
                if (!sku || /^PARENT\b/i.test(sku)) {
                    setMsg('Pick a child SKU before saving Std Price.', 'error');
                    return;
                }
                const std = parseFloat(document.getElementById('lmp-ov-std').value);
                if (!(std > 0)) {
                    setMsg('Enter a Std Price greater than 0.', 'error');
                    return;
                }
                const current = parseFloat(row.std_price);
                if (isFinite(current) && Math.abs(current - std) < 0.001) return;
                const my = parseFloat(row.my_lmp);
                const body = { sku: sku, std_price: std };
                if (isFinite(my) && my > 0) body.my_lmp = my;
                setMsg('Saving Std Price…', '');
                postJson(@json(route('lmp.overall.save')), body).then(function (res) {
                    applyEditToLinkedRows(sku, {
                        std_price: parseFloat(res.std_price) || std,
                        my_lmp: res.my_lmp,
                    }, res.applied_skus);
                    refreshParentSummaries();
                    refreshStdMetrics();
                    setMsg('Std Price saved.', 'ok');
                }).catch(function (err) {
                    setMsg(err.message || 'Save failed', 'error');
                });
            }

            function openLmpModal(row, field) {
                const site = lmpSites[field];
                if (!site) return;
                lmpState.field = field;
                lmpState.row = row;
                lmpState.bySku = {};
                lmpState.competitors = [];
                lmpState.editing = null;
                document.getElementById('lmp-overall-lmp-title').textContent = site.label + ' — ' + (row.sku || '');
                configureSite(site);
                fillSkuControl(row);
                setStdInput(parseFloat(metricRow().std_price));
                resetForm();
                setMsg('', '');
                document.getElementById('lmp-overall-lmp-list').innerHTML = '<div class="text-center text-muted py-4">Loading competitors…</div>';
                if (!showBsModal(lmpModalEl)) return;
                loadLists(false);
            }

            document.getElementById('lmp-ov-form').addEventListener('submit', saveCompetitor);
            document.getElementById('lmp-ov-cancel').addEventListener('click', function () {
                resetForm();
                setMsg('', '');
            });
            document.getElementById('lmp-ov-pull').addEventListener('click', function () {
                const btn = this;
                btn.disabled = true;
                const html = btn.innerHTML;
                btn.innerHTML = '<i class="ri-loader-4-line"></i> Pulling…';
                loadLists(true).finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = html;
                });
            });
            document.getElementById('lmp-ov-std').addEventListener('input', refreshStdMetrics);
            document.getElementById('lmp-ov-std').addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                }
            });
            document.getElementById('lmp-ov-std').addEventListener('change', saveStd);
            document.getElementById('lmp-ov-sku-control').addEventListener('change', function (e) {
                if (!e.target || e.target.id !== 'lmp-ov-sku') return;
                const row = table.getRow(e.target.value);
                if (row) setStdInput(parseFloat(row.getData().std_price));
            });
            document.getElementById('lmp-overall-lmp-list').addEventListener('click', function (e) {
                const preview = e.target.closest('.lmp-ov-preview-btn');
                if (preview) {
                    e.preventDefault();
                    openPreview(preview.getAttribute('data-label'), preview.getAttribute('data-text'));
                    return;
                }
                const edit = e.target.closest('.lmp-ov-edit');
                if (edit) {
                    const item = lmpState.competitors[parseInt(edit.getAttribute('data-idx'), 10)];
                    if (item) enterEdit(item);
                    return;
                }
                const del = e.target.closest('.lmp-ov-delete');
                if (del) {
                    const item = lmpState.competitors[parseInt(del.getAttribute('data-idx'), 10)];
                    if (item) deleteCompetitor(item);
                }
            });
            document.getElementById('lmp-overall-lmp-list').addEventListener('change', function (e) {
                const box = e.target.closest('.lmp-ov-ignore');
                if (!box) return;
                const item = lmpState.competitors[parseInt(box.getAttribute('data-idx'), 10)];
                if (!item) return;
                ignoreCompetitor(item, box.checked);
            });
            document.getElementById('lmp-ov-preview-close').addEventListener('click', closePreview);
            document.getElementById('lmp-overall-text-preview').addEventListener('click', function (e) {
                if (e.target === this) closePreview();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !document.getElementById('lmp-overall-text-preview').hidden) closePreview();
            });
            lmpModalEl.addEventListener('hidden.bs.modal', closePreview);

            function pctCell(value, styleName) {
                if (value == null || value === '') return '<span class="text-muted">—</span>';
                const pct = parseFloat(value);
                if (!isFinite(pct)) return '<span class="text-muted">—</span>';
                const fn = window.MetricPctColors && MetricPctColors[styleName];
                const style = typeof fn === 'function' ? (fn(pct) || '') : '';
                return '<span style="' + style + '">' + Math.round(pct) + '%</span>';
            }

            function lmpPriceDiffPct(data) {
                const lmp = parseFloat(data && data.avg_lmp);
                const price = parseFloat(data && data.avg_price);
                if (!isFinite(lmp) || lmp <= 0 || !isFinite(price) || price <= 0) return null;
                return ((lmp - price) / price) * 100;
            }

            function lmpPriceDiffClass(pct) {
                if (pct == null || !isFinite(pct)) return '';
                if (pct < 0) return 'lmp-diff-red';
                if (pct < 10) return 'lmp-diff-green';
                if (pct <= 20) return 'lmp-diff-yellow';
                return 'lmp-diff-magenta';
            }

            function percentColumn(title, field, styleName, tip) {
                return {
                    title: title,
                    field: field,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    minWidth: 52,
                    sorter: 'number',
                    headerTooltip: tip,
                    formatter: function (cell) {
                        return pctCell(cell.getValue(), styleName);
                    },
                };
            }

            let stdEditRow = null;
            const stdModalEl = document.getElementById('lmpOverallStdModal');

            function openStdModal(row) {
                stdEditRow = row;
                const data = row.getData();
                document.getElementById('lmp-overall-std-sku').textContent = data.sku || '';
                const current = parseFloat(data.std_price);
                const mine = parseFloat(data.my_lmp);
                document.getElementById('lmp-overall-std-input').value =
                    (isFinite(current) && current > 0) ? current.toFixed(2) : '';
                document.getElementById('lmp-overall-my-lmp-input').value =
                    (isFinite(mine) && mine > 0) ? mine.toFixed(2) : '';
                document.getElementById('lmp-overall-std-msg').textContent = '';
                showBsModal(stdModalEl);
            }

            function applyEditToLinkedRows(sku, fields, appliedSkus) {
                const target = String(sku || '').trim().toUpperCase();
                const applied = new Set((appliedSkus || []).map(function (s) {
                    return String(s || '').trim().toUpperCase();
                }).filter(Boolean));
                if (target) applied.add(target);
                const updates = [];
                table.getData().forEach(function (data) {
                    const rowSku = String(data.sku || '').trim().toUpperCase();
                    const linked = Array.isArray(data.linked_lmp_skus) ? data.linked_lmp_skus : [];
                    const inGroup = applied.has(rowSku)
                        || linked.some(function (s) { return String(s || '').trim().toUpperCase() === target; });
                    if (!inGroup) return;
                    const patch = Object.assign({ sku: data.sku }, fields);
                    if (Object.prototype.hasOwnProperty.call(fields, 'std_price')) {
                        Object.assign(patch, stdMarginFields(fields.std_price, data));
                    }
                    updates.push(patch);
                });
                if (updates.length) table.updateData(updates);
                updateLmpStdBadges();
            }

            const table = new Tabulator('#lmp-overall-table', {
                index: 'sku',
                height: '70vh',
                layout: 'fitData',
                columnDefaults: { resizable: true },
                placeholder: 'Loading…',
                selectableRows: 'highlight',
                selectableRowsRollingSelection: true,
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [50, 100, 250, 500],
                ajaxURL: @json(route('lmp.overall.data')),
                ajaxConfig: 'GET',
                ajaxResponse: function (url, params, response) {
                    return (response && response.data) ? response.data : [];
                },
                rowFormatter: function (row) {
                    const el = row.getElement();
                    if (!el) return;
                    if (row.getData().is_parent_summary) el.classList.add('lmp-overall-parent');
                    else el.classList.remove('lmp-overall-parent');
                },
                columns: [
                    {
                        title: 'Select',
                        formatter: 'rowSelection',
                        titleFormatter: 'rowSelection',
                        titleFormatterParams: { rowRange: 'active' },
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        cssClass: 'lmp-header-flat',
                        width: 42,
                        minWidth: 42,
                        frozen: true,
                    },
                    {
                        title: 'image',
                        field: 'image',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 46,
                        minWidth: 46,
                        frozen: true,
                        formatter: function (cell) {
                            const url = cell.getValue();
                            if (!url) return '<span class="text-muted">—</span>';
                            const safe = String(url).replace(/"/g, '&quot;');
                            return '<img class="lmp-overall-thumb" src="' + safe + '" alt="">';
                        },
                    },
                    { title: 'parent', field: 'parent', minWidth: 64, frozen: true },
                    { title: 'sku', field: 'sku', minWidth: 72, frozen: true },
                    {
                        title: 'inv',
                        field: 'inv',
                        hozAlign: 'center',
                        minWidth: 44,
                        sorter: 'number',
                        formatter: function (cell) { return countCell(cell.getValue()); },
                    },
                    {
                        title: 'ovl30',
                        field: 'ovl30',
                        hozAlign: 'center',
                        minWidth: 48,
                        sorter: 'number',
                        headerTooltip: 'Overall L30 units from Shopify',
                        formatter: function (cell) { return countCell(cell.getValue()); },
                    },
                    {
                        title: 'dil',
                        field: 'dil',
                        hozAlign: 'center',
                        minWidth: 48,
                        sorter: 'number',
                        headerTooltip: 'dil = ovl30 / inv × 100',
                        formatter: function (cell) {
                            const row = cell.getRow().getData();
                            const inv = parseFloat(row.inv) || 0;
                            const ov = parseFloat(row.ovl30) || 0;
                            if (inv <= 0) return '<span style="color:#6c757d;">0%</span>';
                            const dil = (ov / inv) * 100;
                            let color = '#e83e8c';
                            if (dil < 25) color = '#dc3545';
                            else if (dil < 50) color = '#28a745';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                        },
                    },
                    percentColumn('Avg GPFT%', 'gpft', 'gpftStyle', 'Avg GPFT% from /pricing-master-cvr'),
                    percentColumn('Avg GROI%', 'groi', 'groiStyle', 'Avg GROI% from /pricing-master-cvr'),
                    percentColumn('Avg NPFT%', 'npft', 'npftStyle', 'Avg NPFT% from /pricing-master-cvr'),
                    percentColumn('Avg NROI%', 'nroi', 'nroiStyle', 'Avg NROI% from /pricing-master-cvr'),
                    lmpSiteColumn('LMP amz', 'lmp_amz', 'lmp_amz_count'),
                    lmpSiteColumn('LMP ebay', 'lmp_ebay', 'lmp_ebay_count'),
                    lmpSiteColumn('LMP temu', 'lmp_temu', 'lmp_temu_count'),
                    lmpSiteColumn('LMP Google', 'lmp_google', 'lmp_google_count'),
                    {
                        title: 'OV LMP',
                        field: 'ov_lmp',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 72,
                        sorter: 'number',
                        headerTooltip: 'Lowest LMP across Amazon, eBay, Temu, and Google',
                        formatter: function (cell) { return money(cell.getValue()); },
                    },
                    {
                        title: 'Avg LMP',
                        field: 'avg_lmp',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 72,
                        sorter: 'number',
                        headerTooltip: 'Average LMP across Amazon, eBay, Temu, and Google',
                        formatter: function (cell) { return money(cell.getValue()); },
                    },
                    {
                        title: 'My LMP',
                        field: 'my_lmp',
                        hozAlign: 'center',
                        minWidth: 72,
                        sorter: 'number',
                        headerTooltip: 'Manual My LMP (amazon_data_view.MY_LMP)',
                        formatter: function (cell) { return money(cell.getValue()); },
                    },
                    {
                        title: 'Diff',
                        field: 'lmp_diff',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 56,
                        headerTooltip: '(Avg LMP − Avg Price) / Avg Price',
                        sorter: function (a, b, aRow, bRow) {
                            const av = lmpPriceDiffPct(aRow.getData());
                            const bv = lmpPriceDiffPct(bRow.getData());
                            if (av == null && bv == null) return 0;
                            if (av == null) return -1;
                            if (bv == null) return 1;
                            return av - bv;
                        },
                        formatter: function (cell) {
                            const el = cell.getElement();
                            if (el) {
                                el.classList.remove('lmp-diff-magenta', 'lmp-diff-red', 'lmp-diff-green', 'lmp-diff-yellow');
                            }
                            const pct = lmpPriceDiffPct(cell.getRow().getData());
                            const cls = lmpPriceDiffClass(pct);
                            if (el && cls) el.classList.add(cls);
                            if (pct == null) return '<span class="text-muted">—</span>';
                            return Math.round(pct) + '%';
                        },
                    },
                    {
                        title: 'Std Prc',
                        field: 'std_price',
                        hozAlign: 'center',
                        minWidth: 72,
                        sorter: 'number',
                        headerTooltip: 'Amazon Standard Price (amazon_data_view.STANDARD_PRICE)',
                        formatter: function (cell) { return money(cell.getValue()); },
                    },
                    percentColumn('Std NROI%', 'std_nroi', 'nroiStyle', 'NROI% at Std Price with 70% margin. ((Std Prc × 0.70 − ship − LP) / LP) × 100'),
                    percentColumn('Std NPFT%', 'std_npft', 'npftStyle', 'NPFT% at Std Price with 70% margin. ((Std Prc × 0.70 − ship − LP) / Std Prc) × 100'),
                    {
                        title: 'Avg Price',
                        field: 'avg_price',
                        hozAlign: 'center',
                        minWidth: 72,
                        sorter: 'number',
                        headerTooltip: 'Avg Price from /pricing-master-cvr',
                        formatter: function (cell) { return money(cell.getValue()); },
                    },
                    {
                        title: 'Edit',
                        field: 'row_edit',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 40,
                        minWidth: 40,
                        formatter: function (cell) {
                            const data = cell.getRow().getData();
                            if (data.is_parent_summary) return '';
                            return '<button type="button" class="btn btn-sm btn-link text-primary p-0 lmp-overall-edit" data-sku="'
                                + escHtml(data.sku || '') + '" title="Edit Std Price and My LMP">'
                                + '<i class="ri-pencil-line" style="font-size:16px;pointer-events:none;"></i></button>';
                        },
                    },
                ],
            });

            let badgeFilter = { type: '', field: '' };

            function isMissingChannelLmp(row, field) {
                if (!row || row.is_parent_summary) return false;
                const inv = parseFloat(row.inv);
                if (!isFinite(inv) || inv <= 0) return false;
                const price = parseFloat(row[field]);
                const count = parseInt(row[field + '_count'], 10) || 0;
                return !(isFinite(price) && price > 0) && count === 0;
            }

            function skuMatchesBadge(row) {
                if (!badgeFilter.type || !badgeFilter.field) return true;
                if (!row || row.is_parent_summary) return false;
                if (badgeFilter.type === 'missing') return isMissingChannelLmp(row, badgeFilter.field);
                return lmpStdBand(row[badgeFilter.field], row.std_price) === badgeFilter.type;
            }

            function paintBadgeActive(badge, on) {
                if (badge) badge.classList.toggle('is-active', on);
            }

            function updateMissingLmpCounts() {
                const counts = {};
                lmpBadgeCols.forEach(function (col) { counts[col.field] = 0; });
                table.getData().forEach(function (row) {
                    lmpBadgeCols.forEach(function (col) {
                        if (isMissingChannelLmp(row, col.field)) counts[col.field]++;
                    });
                });
                lmpBadgeCols.forEach(function (col) {
                    const n = counts[col.field];
                    const numEl = document.getElementById('lmp-missing-' + col.field);
                    const badge = numEl ? numEl.closest('.lmp-missing-filter') : null;
                    if (numEl) numEl.textContent = n.toLocaleString('en-US');
                    if (!badge) return;
                    badge.style.backgroundColor = n === 0 ? '#28a745' : '#dc3545';
                    paintBadgeActive(badge, badgeFilter.type === 'missing' && badgeFilter.field === col.field);
                });
            }

            function updateLmpStdBadges() {
                const counts = {};
                lmpBadgeCols.forEach(function (col) {
                    counts[col.field] = { high: 0, low: 0 };
                });
                table.getData().forEach(function (row) {
                    if (row.is_parent_summary) return;
                    lmpBadgeCols.forEach(function (col) {
                        const band = lmpStdBand(row[col.field], row.std_price);
                        if (band) counts[col.field][band]++;
                    });
                });
                lmpBadgeCols.forEach(function (col) {
                    ['high', 'low'].forEach(function (band) {
                        const numEl = document.getElementById('lmp-badge-' + band + '-' + col.field);
                        const badge = numEl ? numEl.closest('.lmp-std-filter') : null;
                        if (numEl) numEl.textContent = counts[col.field][band].toLocaleString('en-US');
                        paintBadgeActive(badge, badgeFilter.type === band && badgeFilter.field === col.field);
                    });
                });
            }

            function updateCounts() {
                const selected = table.getSelectedRows().length;
                const active = table.getDataCount('active');
                document.getElementById('lmp-overall-total').textContent = 'Total: ' + active.toLocaleString();
                document.getElementById('lmp-overall-selected').textContent = 'Selected: ' + selected.toLocaleString();
                updateLmpStdBadges();
                updateMissingLmpCounts();
            }

            document.getElementById('lmp-overall-wrap').addEventListener('click', function (e) {
                const btn = e.target.closest('.lmp-overall-edit');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                const sku = btn.getAttribute('data-sku');
                const row = sku ? table.getRow(sku) : null;
                if (row) openStdModal(row);
            }, true);

            table.on('dataProcessed', updateCounts);
            table.on('dataFiltered', function () {
                updateLmpStdBadges();
                updateMissingLmpCounts();
            });
            table.on('rowSelectionChanged', updateCounts);

            document.getElementById('lmp-overall-toolbar').addEventListener('click', function (e) {
                const badge = e.target.closest('.lmp-std-filter, .lmp-missing-filter');
                if (!badge) return;
                const field = badge.getAttribute('data-field') || '';
                const type = badge.classList.contains('lmp-missing-filter')
                    ? 'missing'
                    : (badge.getAttribute('data-band') || '');
                if (!field || !type) return;
                if (badgeFilter.type === type && badgeFilter.field === field) {
                    badgeFilter = { type: '', field: '' };
                } else {
                    badgeFilter = { type: type, field: field };
                }
                applySearch();
            });

            let searchTimer = null;
            function invMatches(data, invFilter) {
                if (invFilter === 'all') return true;
                const inv = parseFloat(data.inv);
                const n = isFinite(inv) ? inv : 0;
                if (invFilter === 'lt1') return n < 1;
                if (invFilter === 'gt0') return n > 0;
                return true;
            }
            function rowMatches(data, rowFilter) {
                if (rowFilter === 'sku') return !data.is_parent_summary;
                if (rowFilter === 'parent') return !!data.is_parent_summary;
                return true;
            }
            function applySearch() {
                const parentTerm = (document.getElementById('lmp-overall-search-parent').value || '').trim().toLowerCase();
                const skuTerm = (document.getElementById('lmp-overall-search-sku').value || '').trim().toLowerCase();
                const invFilter = document.getElementById('lmp-overall-inv-filter').value || 'all';
                const rowFilter = document.getElementById('lmp-overall-row-filter').value || 'both';
                const textAndInv = function (data) {
                    const parentOk = parentTerm === ''
                        || String(data.parent || '').toLowerCase().indexOf(parentTerm) !== -1;
                    const skuOk = skuTerm === ''
                        || String(data.sku || '').toLowerCase().indexOf(skuTerm) !== -1;
                    return parentOk && skuOk && invMatches(data, invFilter);
                };
                if (parentTerm === '' && skuTerm === '' && invFilter === 'all' && rowFilter === 'both' && !badgeFilter.type && !playActive) {
                    table.clearFilter();
                    return;
                }
                const parentsWithMatch = {};
                if (badgeFilter.type) {
                    table.getData().forEach(function (data) {
                        if (!skuMatchesBadge(data) || !textAndInv(data) || !data.parent) return;
                        if (!playParentMatches(data)) return;
                        parentsWithMatch[data.parent] = true;
                    });
                }
                table.setFilter(function (data) {
                    if (!playParentMatches(data)) return false;
                    if (!textAndInv(data) || !rowMatches(data, rowFilter)) return false;
                    if (!badgeFilter.type) return true;
                    if (data.is_parent_summary) return !!parentsWithMatch[data.parent];
                    return skuMatchesBadge(data);
                });
            }

            let playParents = [];
            let playIndex = -1;
            let playActive = false;

            function collectPlayParents() {
                const seen = {};
                const list = [];
                table.getData().forEach(function (row) {
                    const parent = String(row.parent || '').trim();
                    if (!parent || seen[parent]) return;
                    seen[parent] = true;
                    list.push(parent);
                });
                return list;
            }

            function playParentMatches(data) {
                if (!playActive || playIndex < 0 || !playParents.length) return true;
                return String((data && data.parent) || '').trim() === playParents[playIndex];
            }

            function updatePlayButtons() {
                const back = document.getElementById('lmp-play-backward');
                const next = document.getElementById('lmp-play-forward');
                const play = document.getElementById('lmp-play-auto');
                const pause = document.getElementById('lmp-play-pause');
                if (back) back.disabled = !playActive || playIndex <= 0;
                if (next) next.disabled = !playActive || playIndex >= playParents.length - 1;
                if (play) {
                    play.style.display = playActive ? 'none' : '';
                    play.title = playActive ? 'Show all products' : 'Start parent navigation';
                }
                if (pause) pause.style.display = playActive ? '' : 'none';
                [back, next].forEach(function (btn) {
                    if (!btn) return;
                    btn.classList.toggle('btn-primary', playActive);
                    btn.classList.toggle('btn-light', !playActive);
                });
            }

            function startPlayNavigation() {
                playParents = collectPlayParents();
                if (!playParents.length) return;
                playActive = true;
                playIndex = 0;
                updatePlayButtons();
                applySearch();
            }

            function stopPlayNavigation() {
                playActive = false;
                playIndex = -1;
                updatePlayButtons();
                applySearch();
            }

            function stepPlayParent(delta) {
                if (!playActive) return;
                const next = playIndex + delta;
                if (next < 0 || next >= playParents.length) return;
                playIndex = next;
                updatePlayButtons();
                applySearch();
            }

            document.getElementById('lmp-play-auto').addEventListener('click', startPlayNavigation);
            document.getElementById('lmp-play-pause').addEventListener('click', stopPlayNavigation);
            document.getElementById('lmp-play-forward').addEventListener('click', function () { stepPlayParent(1); });
            document.getElementById('lmp-play-backward').addEventListener('click', function () { stepPlayParent(-1); });
            updatePlayButtons();
            ['lmp-overall-search-parent', 'lmp-overall-search-sku'].forEach(function (id) {
                document.getElementById(id).addEventListener('input', function () {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(applySearch, 200);
                });
            });
            document.getElementById('lmp-overall-inv-filter').addEventListener('change', applySearch);
            document.getElementById('lmp-overall-row-filter').addEventListener('change', applySearch);

            document.getElementById('lmp-overall-std-save').addEventListener('click', function () {
                const data = stdEditRow ? stdEditRow.getData() : null;
                const sku = data ? String(data.sku || '').trim() : '';
                const std = parseFloat(document.getElementById('lmp-overall-std-input').value);
                const myRaw = document.getElementById('lmp-overall-my-lmp-input').value.trim();
                const myLmp = myRaw === '' ? null : parseFloat(myRaw);
                const msg = document.getElementById('lmp-overall-std-msg');
                if (!sku || !isFinite(std) || std <= 0) {
                    msg.className = 'small mt-2 text-danger';
                    msg.textContent = 'Enter a Std Price greater than 0.';
                    return;
                }
                if (myLmp !== null && (!isFinite(myLmp) || myLmp <= 0)) {
                    msg.className = 'small mt-2 text-danger';
                    msg.textContent = 'My LMP must be greater than 0, or left blank.';
                    return;
                }
                const btn = this;
                btn.disabled = true;
                msg.className = 'small mt-2 text-muted';
                msg.textContent = 'Saving…';
                fetch(@json(route('lmp.overall.save')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        sku: sku,
                        std_price: std,
                        my_lmp: myLmp,
                    }),
                }).then(function (r) {
                    return r.json().then(function (body) {
                        if (!r.ok) throw new Error((body && body.error) ? body.error : 'Save failed');
                        return body;
                    });
                }).then(function (body) {
                    applyEditToLinkedRows(sku, {
                        std_price: parseFloat(body.std_price) || std,
                        my_lmp: body.my_lmp,
                    }, body.applied_skus);
                    refreshParentSummaries();
                    msg.className = 'small mt-2 text-success';
                    msg.textContent = 'Saved.';
                    setTimeout(function () {
                        if (stdModalEl && window.bootstrap) {
                            bootstrap.Modal.getInstance(stdModalEl)?.hide();
                        }
                    }, 400);
                }).catch(function (err) {
                    msg.className = 'small mt-2 text-danger';
                    msg.textContent = err.message || 'Save failed';
                }).finally(function () {
                    btn.disabled = false;
                });
            });
        })();
    </script>
@endsection
