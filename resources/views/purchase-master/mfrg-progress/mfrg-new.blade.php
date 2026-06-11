@extends('layouts.vertical', ['title' => 'MIP'])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-header {
            background: #D8F3F3;
            border-bottom: 1px solid #403f3f;
        }
        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #3bc0c3;
            border-right: 1px solid #fff;
            padding: 10px 6px;
            font-weight: 700;
            color: #fff;
            font-size: 0.9rem;
        }
        .tabulator-row { background-color: #fff !important; }
        .tabulator-row:nth-child(even) { background-color: #f8fafc !important; }
        .tabulator-row:hover { background-color: #dbeafe !important; }
        .tabulator .tabulator-cell {
            text-align: center;
            padding: 6px;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.85rem;
        }
        .tabulator .tabulator-cell.mip-new-image-cell { padding: 0 !important; line-height: 0; }
        .mip-new-img-aspect { width: 44px; height: 44px; margin: 0 auto; }
        .mip-new-img-aspect img { width: 100%; height: 100%; object-fit: contain; cursor: pointer; display: block; }

        /* Executive colored select */
        .toa-exec-select { border: none; border-radius: 6px; padding: 3px 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; outline: none; width: 100%; }

        /* Stage dot + invisible select overlay */
        .mip-stage-dot { position: relative; width: 44px; height: 30px; margin: 0 auto; }
        .mip-stage-marker { width: 100%; height: 100%; display: inline-flex; align-items: center; justify-content: center; pointer-events: none; }
        .mip-stage-dot .stage-status-dot { display: inline-block; width: 16px; height: 16px; border-radius: 50%; }
        .mip-stage-dot .stage-transit-icon { color: #0ea5e9; font-size: 15px; }
        .mip-stage-dot .stage-stage-select { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }

        /* Status dot toggles (Pkg/U-Manual/Compliance) */
        .mip-status-dot { display: inline-block; width: 16px; height: 16px; border-radius: 50%; cursor: pointer; border: 1px solid rgba(0,0,0,0.1); }

        /* Communication logos */
        .mip-plat-icon-link { display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: transform 0.12s; }
        .mip-plat-icon-link:hover { transform: scale(1.2); }
        .mip-plat-menu { padding: 6px; min-width: auto; }

        /* Footer / pagination */
        .tabulator .tabulator-footer { background: #f4f7fa; border-top: 1px solid #cbd5e1; padding: 6px; }
        .tabulator .tabulator-footer .tabulator-page { padding: 6px 12px; margin: 0 3px; border-radius: 6px; }
        .tabulator .tabulator-footer .tabulator-page.active { background: #3bc0c3; color: #fff; }
        .tabulator .tabulator-cell { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Column show/hide menu */
        .mip-columns-wrap { position: relative; }
        .mip-columns-menu {
            position: absolute; z-index: 4000; top: 100%; left: 0; margin-top: 4px;
            background: #fff; border: 1px solid #cbd5e1; border-radius: 8px;
            padding: 8px 10px; min-width: 210px; max-height: 340px; overflow: auto;
            box-shadow: 0 6px 18px rgba(0,0,0,0.12);
        }
        .mip-columns-menu .mip-columns-head {
            display: flex; justify-content: space-between; align-items: center;
            gap: 8px; margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0;
        }
        .mip-columns-menu .form-check { margin-bottom: 3px; }
        .mip-columns-menu .form-check-label { cursor: pointer; }

        /* SKU copy icon */
        .mip-sku-copy { margin-left: 6px; cursor: pointer; color: #3bc0c3; font-size: 0.8rem; opacity: 0.75; transition: opacity 0.12s, color 0.12s; }
        .mip-sku-copy:hover { opacity: 1; color: #2563eb; }
        .mip-sku-copy.copied { color: #16a34a; opacity: 1; }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'MIP', 'sub_title' => 'MIP'])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'mip'])

                        <div>
                            <label class="form-label small fw-semibold mb-1 d-block">Stage</label>
                            <select id="mip-stage-filter" class="form-select form-select-sm" style="width: 130px;">
                                <option value="both">MIP + R2S</option>
                                <option value="mip">MIP only</option>
                                <option value="r2s">R2S only</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label small fw-semibold mb-1 d-block">Bulk Stage</label>
                            <div class="d-flex gap-1">
                                <select id="mip-bulk-stage-select" class="form-select form-select-sm" style="width: 120px;">
                                    <option value="">— Choose —</option>
                                    <option value="appr_req">Appr. Req</option>
                                    <option value="mip">MIP</option>
                                    <option value="r2s">R2S</option>
                                    <option value="transit">Transit</option>
                                    <option value="all_good">😊 All Good</option>
                                    <option value="to_order_analysis">2 Order</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-primary" id="mip-bulk-stage-apply">Apply</button>
                            </div>
                        </div>

                        <div>
                            <label class="form-label small fw-semibold mb-1 d-block">👤 Bulk Exec</label>
                            <div class="d-flex gap-1">
                                <select id="mip-bulk-exec-select" class="form-select form-select-sm" style="width: 130px;">
                                    <option value="">— Select exec —</option>
                                    <option value="">— Unassigned —</option>
                                    <option value="Atin">Atin</option>
                                    <option value="Jack">Jack</option>
                                    <option value="Nitish">Nitish</option>
                                    <option value="Ajay">Ajay</option>
                                    <option value="Candy">Candy</option>
                                    <option value="Sruti">Sruti</option>
                                </select>
                                <button type="button" class="btn btn-sm" style="background:#4db6ac;color:#fff;" id="mip-bulk-exec-apply">Apply</button>
                            </div>
                        </div>

                        <div>
                            <label class="form-label small fw-semibold mb-1 d-block">💰 Amount</label>
                            <div id="totalAmount" class="fw-bold text-primary">0</div>
                        </div>
                        <div>
                            <label class="form-label small fw-semibold mb-1 d-block">📦 CBM</label>
                            <div id="totalCBM" class="fw-bold text-success">0</div>
                        </div>
                        <div>
                            <label class="form-label small fw-semibold mb-1 d-block">🔢 Items</label>
                            <div id="totalItems" class="fw-bold">0</div>
                        </div>

                        <div>
                            <label for="search-input" class="form-label small fw-semibold mb-1 d-block">🔍 Search All</label>
                            <input type="text" id="search-input" class="form-control form-control-sm" placeholder="Search..." style="width: 150px;">
                        </div>

                        <div class="mip-columns-wrap">
                            <label class="form-label small fw-semibold mb-1 d-block">Columns</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="mip-columns-btn">
                                <i class="fas fa-table-columns me-1"></i> Show / Hide
                            </button>
                            <div id="mip-columns-menu" class="mip-columns-menu" style="display:none;"></div>
                        </div>

                        <div class="d-flex align-items-end">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="show-archived-toggle">
                                <label class="form-check-label fw-semibold small" for="show-archived-toggle">Show archived</label>
                            </div>
                        </div>
                        <div class="d-flex align-items-end gap-1">
                            <button type="button" class="btn btn-sm btn-info text-white" id="mip-followup-btn"><i class="fas fa-comment-dots me-1"></i> Follow-Up</button>
                            <button type="button" class="btn btn-sm btn-warning d-none" id="archive-selected-btn"><i class="fas fa-archive me-1"></i> Archive</button>
                            <button type="button" class="btn btn-sm btn-success d-none" id="restore-selected-btn"><i class="fas fa-undo me-1"></i> Restore</button>
                        </div>
                    </div>

                    <div id="mfrg-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit All Fields Modal (president@5core.com only) --}}
    <div class="modal fade" id="mipEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-pen me-2"></i> Edit Row <span id="mip-edit-sku" class="ms-2 fw-normal small"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="mip-edit-form" class="row g-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="mip-edit-save"><i class="fas fa-save me-1"></i> Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Follow-Up / Current Status Modal --}}
    <div class="modal fade" id="mipFollowupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-comment-dots me-2"></i> Current Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Supplier</label>
                        <select id="followup-supplier-select" class="form-select">
                            <option value="">-- Select supplier --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Status / Remark</label>
                        <textarea id="followup-remark-input" class="form-control" rows="3" placeholder="Report the current status..."></textarea>
                    </div>
                    <div class="text-end mb-3">
                        <button type="button" id="followup-save-btn" class="btn btn-primary"><i class="fas fa-save me-1"></i> Submit</button>
                    </div>
                    <hr>
                    <h6 class="fw-semibold mb-2">History</h6>
                    <div id="followup-history-list"><p class="text-muted small mb-0">Select a supplier to view history.</p></div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.body.style.zoom = "96%";
            document.documentElement.setAttribute("data-sidenav-size", "condensed");

            const CSRF = '{{ csrf_token() }}';
            const USER_EMAIL = '{{ strtolower(trim(auth()->user()->email ?? "")) }}';
            const CAN_EDIT_ALL = USER_EMAIL === 'president@5core.com';
            const CAN_ARCHIVE = USER_EMAIL === 'president@5core.com' || USER_EMAIL === 'purchase@5core.com';
            let uniqueSuppliers = [];
            let showArchived = false;
            let table;

            const EXEC_OPTIONS = ['Atin', 'Jack', 'Nitish', 'Ajay', 'Candy', 'Sruti'];
            const EXEC_COLORS = {
                'Atin':   { bg: '#3b82f6', text: '#fff' },
                'Jack':   { bg: '#10b981', text: '#fff' },
                'Nitish': { bg: '#8b5cf6', text: '#fff' },
                'Ajay':   { bg: '#f59e0b', text: '#fff' },
                'Candy':  { bg: '#ec4899', text: '#fff' },
                'Sruti':  { bg: '#14b8a6', text: '#fff' },
            };
            const STAGE_COLORS = {
                'appr_req': '#facc15', 'mip': '#2563eb', 'to_order_analysis': '#c2410c',
                'r2s': '#16a34a', 'all_good': '#22c55e', '': '#94a3b8',
            };
            const PLAT_ICON = { 'Website': 'fas fa-globe', 'Email': 'fas fa-envelope', 'WhatsApp': 'fab fa-whatsapp', 'WeChat': 'fab fa-weixin', 'Alibaba': 'fas fa-store' };
            const PLAT_COLOR = { 'Website': '#2563eb', 'Email': '#dc3545', 'WhatsApp': '#25d366', 'WeChat': '#09b83e', 'Alibaba': '#ff6a00' };

            function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }

            // Per-unit cost price: price_from_po, then rate, then product_master CP.
            function rowCp(d) {
                return parseFloat(d.price_from_po) || parseFloat(d.rate) || parseFloat(d.product_cp) || 0;
            }
            // Per-row Amount = CP * qty — same math as the Amount badge.
            function rowAmount(d) {
                const qty = parseFloat(d.qty) || 0;
                return rowCp(d) * qty;
            }

            function postInline(sku, mipId, column, value) {
                return fetch('/mfrg-progresses/inline-update-by-sku', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ sku: sku, mip_id: mipId, column: column, value: value })
                }).then(r => r.json());
            }
            function postUpdateLink(sku, column, value) {
                return fetch('/update-link', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ sku: sku, row_id: 0, column: column, value: value })
                }).then(r => r.json());
            }
            function postStage(sku, parent, value) {
                return $.post('/update-forecast-data', { sku: sku, parent: parent || '', column: 'Stage', value: value, _token: CSRF });
            }
            function postForecastData(sku, parent, column, value) {
                return $.post('/update-forecast-data', { sku: sku, parent: parent || '', column: column, value: value, _token: CSRF });
            }

            // ---- formatters ----
            function execFormatter(cell) {
                const row = cell.getRow().getData();
                const val = (cell.getValue() || '').trim();
                const c = EXEC_COLORS[val] || { bg: '#e5e7eb', text: '#6b7280' };
                let opts = '<option value=""' + (val === '' ? ' selected' : '') + '>— Unassigned —</option>';
                EXEC_OPTIONS.forEach(function (n) { opts += '<option value="' + n + '"' + (n === val ? ' selected' : '') + '>' + n + '</option>'; });
                return '<select class="toa-exec-select" data-sku="' + esc(row.sku) + '" style="background:' + c.bg + ';color:' + c.text + ';">' + opts + '</select>';
            }
            function stageFormatter(cell) {
                const d = cell.getRow().getData();
                // Archived rows: show a dedicated red "Archived" stage dot (read-only)
                if (showArchived || d.deleted_at) {
                    return '<div class="mip-stage-dot" title="Archived"><span class="mip-stage-marker"><span class="stage-status-dot" style="background-color:#dc3545;"></span></span></div>';
                }
                const v = (cell.getValue() || '').toLowerCase().trim();
                const color = STAGE_COLORS[v] !== undefined ? STAGE_COLORS[v] : '#94a3b8';
                const marker = v === 'transit'
                    ? '<i class="fas fa-truck stage-transit-icon"></i>'
                    : '<span class="stage-status-dot" style="background-color:' + color + ';"></span>';
                const mk = function (val, label) { return '<option value="' + val + '"' + (v === val ? ' selected' : '') + '>' + label + '</option>'; };
                return '<div class="mip-stage-dot"><span class="mip-stage-marker">' + marker + '</span>' +
                    '<select class="stage-stage-select editable-stage">' +
                    '<option value="">Select</option>' + mk('appr_req', 'Appr. Req') + mk('mip', 'MIP') + mk('r2s', 'R2S') +
                    mk('transit', 'Transit') + mk('all_good', '😊 All Good') + mk('to_order_analysis', '2 Order') +
                    '</select></div>';
            }
            function dotToggleFormatter(column) {
                return function (cell) {
                    const on = String(cell.getValue() || '').toLowerCase() === 'yes';
                    const color = on ? '#22c55e' : '#dc3545';
                    return '<span class="mip-status-dot mip-dot-toggle" data-column="' + column + '" style="background-color:' + color + ';"></span>';
                };
            }
            function commFormatter(cell) {
                const list = cell.getRow().getData().supplier_platform_links || [];
                if (!list.length) return '<span class="text-muted">-</span>';
                let items = '';
                list.forEach(function (p) {
                    const icon = PLAT_ICON[p.label] || 'fas fa-link';
                    const color = PLAT_COLOR[p.label] || '#6b7280';
                    const title = esc(p.label + (p.display ? ': ' + p.display : ''));
                    if (p.url) {
                        const ext = p.external ? ' target="_blank" rel="noopener noreferrer"' : '';
                        items += '<a class="mip-plat-icon-link" href="' + esc(p.url) + '"' + ext + ' title="' + title + '" style="color:' + color + ';font-size:16px;"><i class="' + icon + '"></i></a>';
                    } else {
                        items += '<span class="mip-plat-icon-link" title="' + title + '" style="color:' + color + ';font-size:16px;"><i class="' + icon + '"></i></span>';
                    }
                });
                return '<div class="dropdown d-inline-block"><button class="btn btn-sm btn-light py-0 px-1" type="button" data-bs-toggle="dropdown" style="font-size:11px;" title="Communication">' + list.length + '</button>' +
                    '<ul class="dropdown-menu dropdown-menu-end mip-plat-menu"><li class="d-flex align-items-center gap-2 px-2">' + items + '</li></ul></div>';
            }
            function inputFormatter(column, type, width) {
                return function (cell) {
                    const v = cell.getValue() == null ? '' : cell.getValue();
                    return '<input type="' + type + '" class="form-control form-control-sm mip-inline-input" data-column="' + column + '" value="' + esc(v) + '" style="width:' + (width || 80) + 'px;text-align:center;">';
                };
            }
            // Display dates as "1 Apr"; empty -> red dot. Editable via Tabulator date editor.
            function dateDisplayFormatter(cell) {
                const raw = cell.getValue();
                if (!raw) return '<span class="mip-status-dot" style="background-color:#dc3545;" title="No date"></span>';
                const d = new Date(raw);
                if (isNaN(d.getTime())) return '<span class="mip-status-dot" style="background-color:#dc3545;" title="No date"></span>';
                const short = d.getDate() + ' ' + d.toLocaleString('en-US', { month: 'short' });
                const full = short + ' ' + d.getFullYear();
                return '<span title="' + full + '">' + short + '</span>';
            }
            function supplierFormatter(cell) {
                const val = cell.getValue() || '';
                let opts = '<option value=""></option>';
                uniqueSuppliers.forEach(function (s) { opts += '<option value="' + esc(s) + '"' + (s === val ? ' selected' : '') + '>' + esc(s) + '</option>'; });
                return '<select class="form-select form-select-sm mip-supplier-select" style="width:110px;">' + opts + '</select>';
            }

            table = new Tabulator("#mfrg-table", {
                ajaxURL: "/mfrg-in-progress/data",
                ajaxParams: function () { return { archived: showArchived ? 1 : 0 }; },
                ajaxConfig: "GET",
                selectableRows: true,
                rowHeader: { formatter: "rowSelection", titleFormatter: "rowSelection", headerSort: false, frozen: true, hozAlign: "center", width: 45 },
                layout: "fitColumns",
                columnDefaults: { minWidth: 60, resizable: true },
                height: "70vh",
                pagination: true,
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200],
                paginationCounter: "rows",
                columns: [
                    {
                        title: "#", field: "Image", headerSort: false, cssClass: "mip-new-image-cell", width: 50,
                        formatter: function (cell) {
                            const url = cell.getValue();
                            return url ? '<div class="mip-new-img-aspect"><img src="' + esc(url) + '"></div>' : '<span class="text-muted">N/A</span>';
                        }
                    },
                    { title: "Executive", field: "exec", width: 120, hozAlign: "center", headerFilter: "list",
                      headerFilterParams: { values: { "": "— All —", "__un__": "Unassigned", "Atin": "Atin", "Jack": "Jack", "Nitish": "Nitish", "Ajay": "Ajay", "Candy": "Candy", "Sruti": "Sruti" } },
                      headerFilterFunc: function (h, rv) { if (!h) return true; const r = (rv || '').trim(); return h === '__un__' ? r === '' : r === h; },
                      formatter: execFormatter },
                    { title: "SKU", field: "sku", width: 190, headerFilter: "input", headerFilterPlaceholder: " Filter SKU...", headerFilterLiveFilter: true,
                      formatter: function (cell) {
                          const v = cell.getValue() || '';
                          if (!v) return '';
                          return '<span class="mip-sku-text">' + esc(v) + '</span>' +
                              '<i class="far fa-copy mip-sku-copy" data-sku="' + esc(v) + '" title="Copy SKU"></i>';
                      } },
                    { title: "QTY", field: "qty", width: 90, hozAlign: "center", formatter: inputFormatter('qty', 'number', 70) },
                    { title: "O Date", field: "created_at", width: 90, hozAlign: "center", formatter: dateDisplayFormatter,
                      editor: "date", editorParams: { format: "yyyy-MM-dd" },
                      cellEdited: function (cell) { const d = cell.getRow().getData(); postInline(d.sku || '', d.id || 0, 'created_at', cell.getValue()).then(r => { if (!r.success) alert(r.message || 'Save failed'); }); } },
                    { title: "D Date", field: "delivery_date", width: 90, hozAlign: "center", formatter: dateDisplayFormatter,
                      editor: "date", editorParams: { format: "yyyy-MM-dd" },
                      cellEdited: function (cell) { const d = cell.getRow().getData(); postInline(d.sku || '', d.id || 0, 'delivery_date', cell.getValue()).then(r => { if (!r.success) alert(r.message || 'Save failed'); }); } },
                    { title: "Supplier", field: "supplier", width: 120, hozAlign: "center", headerFilter: "input", headerFilterPlaceholder: " Filter...", formatter: supplierFormatter },
                    { title: '<i class="fas fa-comments" title="Communication"></i>', field: "supplier_platform_links", width: 56, headerSort: false, formatter: commFormatter },
                    { title: "PO", field: "mip_po_number", width: 80, hozAlign: "center", formatter: function (c) { const v = c.getValue(); return v ? '<span class="badge bg-info">' + esc(v) + '</span>' : '<span class="mip-status-dot" style="background-color:#dc3545;" title="No PO"></span>'; } },
                    { title: "T-CBM", field: "total_cbm", width: 90, hozAlign: "center", formatter: function (cell) {
                        const d = cell.getRow().getData();
                        const cbm = parseFloat(d.CBM) || 0;
                        const qty = parseFloat(d.qty) || 0;
                        const total = cbm * qty;
                        return total > 0 ? total.toFixed(2) : '<span class="text-muted">-</span>';
                    } },
                    { title: "CBM", field: "CBM", width: 80, hozAlign: "center", formatter: function (cell) {
                        const v = parseFloat(cell.getValue());
                        return (!isNaN(v) && v > 0) ? v.toFixed(2) : '<span class="text-muted">-</span>';
                    } },
                    { title: "CP", field: "row_cp", width: 90, hozAlign: "center",
                      sorter: function (a, b, aRow, bRow) { return rowCp(aRow.getData()) - rowCp(bRow.getData()); },
                      formatter: function (cell) {
                          const cp = rowCp(cell.getRow().getData());
                          return cp > 0 ? cp.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '<span class="text-muted">-</span>';
                      } },
                    { title: "Amount", field: "row_amount", width: 100, hozAlign: "center",
                      sorter: function (a, b, aRow, bRow) {
                          const av = rowAmount(aRow.getData()); const bv = rowAmount(bRow.getData());
                          return av - bv;
                      },
                      formatter: function (cell) {
                          const amt = rowAmount(cell.getRow().getData());
                          return amt > 0 ? Math.round(amt).toLocaleString() : '<span class="text-muted">-</span>';
                      } },
                    { title: "Pkg Inst", field: "pkg_inst", width: 80, hozAlign: "center", formatter: dotToggleFormatter('pkg_inst') },
                    { title: "U-Manual", field: "u_manual", width: 90, hozAlign: "center", formatter: dotToggleFormatter('u_manual') },
                    { title: "Compliance", field: "compliance", width: 100, hozAlign: "center", formatter: dotToggleFormatter('compliance') },
                    { title: "Stage", field: "stage", width: 80, hozAlign: "center", formatter: stageFormatter },
                    ...(CAN_EDIT_ALL ? [{
                        title: "Action", field: "row_action", width: 80, hozAlign: "center", headerSort: false,
                        formatter: function () {
                            return '<button type="button" class="btn btn-sm btn-outline-primary mip-action-btn" title="Edit all fields"><i class="fas fa-pen"></i></button>';
                        }
                    }] : []),
                ],
                ajaxResponse: function (url, params, response) {
                    let data = response.data || [];
                    const normSku = function (s) { return String(s == null ? '' : s).trim().toUpperCase(); };
                    // SKUs that already exist as real MIP rows (anything not from ready_to_ship).
                    const mipSkus = new Set(
                        data.filter(i => (i.source_table || '').toString() !== 'ready_to_ship')
                            .map(i => normSku(i.sku))
                            .filter(Boolean)
                    );
                    // Match the old MIP page: skip genuine NR rows (never skip RTS for the NR rule),
                    // and drop the bare Ready-to-Ship duplicate when the same SKU is already a MIP row.
                    let filtered = data.filter(function (item) {
                        const isRts = (item.source_table || '').toString() === 'ready_to_ship';
                        const nr = (item.nr || '').toString().trim().toUpperCase();
                        if (!isRts && nr === 'NR') return false;
                        if (isRts && mipSkus.has(normSku(item.sku))) return false;
                        return true;
                    });
                    uniqueSuppliers = [...new Set(filtered.map(i => i.supplier))].filter(Boolean).sort();
                    return filtered;
                },
            });

            // ---- Column show/hide menu ----
            const colBtn = document.getElementById('mip-columns-btn');
            const colMenu = document.getElementById('mip-columns-menu');
            function columnLabel(col) {
                const def = col.getDefinition() || {};
                const field = col.getField();
                let label = def.title || field || '';
                const tmp = document.createElement('div');
                tmp.innerHTML = label;
                label = (tmp.textContent || tmp.innerText || '').trim();
                return label || field || '(column)';
            }
            function buildColumnsMenu() {
                let rows = '';
                table.getColumns().forEach(function (col) {
                    const field = col.getField();
                    if (!field) return; // skip row-selection / non-data columns
                    const checked = col.isVisible() ? 'checked' : '';
                    rows += '<div class="form-check">' +
                        '<input class="form-check-input mip-col-toggle" type="checkbox" data-field="' + esc(field) + '" id="mipcol-' + esc(field) + '" ' + checked + '>' +
                        '<label class="form-check-label small" for="mipcol-' + esc(field) + '">' + esc(columnLabel(col)) + '</label>' +
                        '</div>';
                });
                colMenu.innerHTML =
                    '<div class="mip-columns-head">' +
                        '<span class="fw-semibold small">Toggle columns</span>' +
                        '<button type="button" class="btn btn-sm btn-link p-0 small" id="mip-columns-all">Show all</button>' +
                    '</div>' + rows;
            }
            colBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (colMenu.style.display === 'none' || colMenu.style.display === '') {
                    buildColumnsMenu();
                    colMenu.style.display = 'block';
                } else {
                    colMenu.style.display = 'none';
                }
            });
            colMenu.addEventListener('click', function (e) { e.stopPropagation(); });
            colMenu.addEventListener('change', function (e) {
                const t = e.target;
                if (!t.classList.contains('mip-col-toggle')) return;
                const field = t.dataset.field;
                if (t.checked) table.showColumn(field); else table.hideColumn(field);
                table.redraw(true);
            });
            colMenu.addEventListener('click', function (e) {
                if (e.target && e.target.id === 'mip-columns-all') {
                    table.getColumns().forEach(function (col) { if (col.getField()) table.showColumn(col.getField()); });
                    table.redraw(true);
                    buildColumnsMenu();
                }
            });
            document.addEventListener('click', function (e) {
                if (colMenu.style.display === 'block' && !colMenu.contains(e.target) && e.target !== colBtn) {
                    colMenu.style.display = 'none';
                }
            });

            // ---- combined filtering (stage dropdown + global search) ----
            function applyFilters() {
                const stage = (document.getElementById('mip-stage-filter').value || 'both').toLowerCase();
                const search = (document.getElementById('search-input').value || '').trim().toLowerCase();
                const pending = (document.getElementById('row-data-pending-status')?.value || '');
                table.setFilter(function (row) {
                    let keep = true;
                    const rs = (row.stage || '').toLowerCase().trim();
                    if (stage === 'mip') keep = keep && rs === 'mip';
                    else if (stage === 'r2s') keep = keep && rs === 'r2s';
                    if (search) keep = keep && Object.values(row).some(v => v && v.toString().toLowerCase().includes(search));
                    return keep;
                });
                setTimeout(updateStats, 0);
            }

            function updateStats() {
                const rows = table.getRows(true).filter(r => r.getElement().offsetParent !== null);
                let amount = 0, cbm = 0;
                const activeData = table.getData("active");
                const items = activeData.length;
                activeData.forEach(function (d) {
                    const qty = parseFloat(d.qty) || 0;
                    cbm += (parseFloat(d.CBM) || 0) * qty;
                    amount += rowAmount(d);
                });
                document.getElementById('totalAmount').textContent = Math.round(amount).toLocaleString();
                document.getElementById('totalCBM').textContent = Math.round(cbm).toLocaleString();
                document.getElementById('totalItems').textContent = items;
            }

            table.on("dataLoaded", function () { applyFilters(); updateMfrgArchiveButtons(); });
            table.on("dataFiltered", updateStats);
            document.getElementById('mip-stage-filter').addEventListener('change', applyFilters);
            document.getElementById('search-input').addEventListener('input', function () { clearTimeout(window._mipS); window._mipS = setTimeout(applyFilters, 300); });

            // ---- delegated inline-edit handlers on the table element ----
            const tableEl = document.getElementById('mfrg-table');

            tableEl.addEventListener('change', function (e) {
                const t = e.target;
                const tr = t.closest('.tabulator-row');
                if (!tr) return;
                const row = table.getRow(tr);
                if (!row) return;
                const d = row.getData();
                const sku = d.sku || '';
                const mipId = d.id || 0;

                if (t.classList.contains('toa-exec-select')) {
                    const v = t.value;
                    const c = EXEC_COLORS[v] || { bg: '#e5e7eb', text: '#6b7280' };
                    t.style.background = c.bg; t.style.color = c.text;
                    row.update({ exec: v });
                    postUpdateLink(sku, 'Exec', v || null).then(r => { if (!r.success) alert(r.message || 'Save failed'); });
                } else if (t.classList.contains('editable-stage')) {
                    const v = t.value;
                    postStage(sku, d.parent, v).done(function () {
                        row.update({ stage: v });
                        if (v === 'mip') {
                            fetch('/mfrg-progresses/insert', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                                body: JSON.stringify({ parent: d.parent || '', sku: sku, order_qty: d.qty || '', supplier: d.supplier || '', adv_date: '' }) });
                        }
                        applyFilters();
                    }).fail(function () { alert('Failed to save stage.'); });
                } else if (t.classList.contains('mip-supplier-select')) {
                    const v = t.value;
                    row.update({ supplier: v });
                    postInline(sku, mipId, 'supplier', v).then(r => { if (!r.success) alert(r.message || 'Save failed'); });
                } else if (t.classList.contains('mip-inline-input')) {
                    const col = t.dataset.column;
                    const v = t.value;
                    row.update({ [col === 'created_at' ? 'created_at' : col]: v });
                    postInline(sku, mipId, col, v).then(r => { if (!r.success) alert(r.message || 'Save failed'); else updateStats(); });
                }
            });

            tableEl.addEventListener('click', function (e) {
                const actBtn = e.target.closest('.mip-action-btn');
                if (actBtn) {
                    const tr = actBtn.closest('.tabulator-row');
                    const row = tr ? table.getRow(tr) : null;
                    if (row) openEditModal(row);
                    return;
                }

                const copyIcon = e.target.closest('.mip-sku-copy');
                if (copyIcon) {
                    const sku = copyIcon.dataset.sku || '';
                    const done = function () {
                        copyIcon.classList.remove('far'); copyIcon.classList.add('fas', 'fa-check', 'copied');
                        setTimeout(function () {
                            copyIcon.classList.remove('fas', 'fa-check', 'copied'); copyIcon.classList.add('far');
                        }, 1200);
                    };
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(sku).then(done).catch(function () {});
                    } else {
                        const ta = document.createElement('textarea');
                        ta.value = sku; document.body.appendChild(ta); ta.select();
                        try { document.execCommand('copy'); done(); } catch (err) {}
                        document.body.removeChild(ta);
                    }
                    return;
                }

                const dot = e.target.closest('.mip-dot-toggle');
                if (!dot) return;
                const tr = dot.closest('.tabulator-row');
                const row = table.getRow(tr);
                if (!row) return;
                const d = row.getData();
                const col = dot.dataset.column;
                const next = String(d[col] || '').toLowerCase() === 'yes' ? 'No' : 'Yes';
                dot.style.backgroundColor = next === 'Yes' ? '#22c55e' : '#dc3545';
                row.update({ [col]: next });
                postInline(d.sku || '', d.id || 0, col, next).then(r => { if (!r.success) alert(r.message || 'Save failed'); });
            });

            // Communication dropdown: move to body so it isn't clipped
            document.addEventListener('shown.bs.dropdown', function (e) {
                const toggle = e.target.closest('.mip-plat-menu') ? null : e.target;
                const menu = toggle && toggle.parentElement ? toggle.parentElement.querySelector('.mip-plat-menu') : null;
                if (!menu) return;
                if (!menu._home) menu._home = menu.parentElement;
                document.body.appendChild(menu);
                menu.classList.add('show');
                const rect = toggle.getBoundingClientRect();
                menu.style.position = 'fixed';
                menu.style.zIndex = '20000';
                menu.style.top = (rect.bottom + 2) + 'px';
                menu.style.left = Math.max(8, rect.left - 40) + 'px';
            });
            document.addEventListener('hide.bs.dropdown', function (e) {
                const menu = document.querySelector('body > .mip-plat-menu.show');
                if (!menu) return;
                menu.classList.remove('show');
                menu.style = '';
                if (menu._home) menu._home.appendChild(menu);
            });

            // ---- Bulk Stage ----
            document.getElementById('mip-bulk-stage-apply').addEventListener('click', async function () {
                const stageVal = document.getElementById('mip-bulk-stage-select').value.trim();
                if (!stageVal) { alert('Choose a stage.'); return; }
                const rows = table.getSelectedRows();
                if (!rows.length) { alert('Select at least one row.'); return; }
                let ok = 0;
                for (const row of rows) {
                    const d = row.getData();
                    if (!d.sku || (parseInt(d.qty, 10) || 0) === 0) continue;
                    try { await postStage(d.sku, d.parent, stageVal); row.update({ stage: stageVal }); ok++; } catch (e) {}
                }
                table.deselectRow();
                applyFilters();
                alert('Stage applied to ' + ok + ' row(s).');
            });

            // ---- Bulk Exec ----
            document.getElementById('mip-bulk-exec-apply').addEventListener('click', async function () {
                const sel = document.getElementById('mip-bulk-exec-select');
                if (sel.selectedIndex === 0) { alert('Select an executive.'); return; }
                const v = sel.value;
                const rows = table.getSelectedRows();
                if (!rows.length) { alert('Select at least one row.'); return; }
                let ok = 0;
                for (const row of rows) {
                    const d = row.getData();
                    if (!d.sku) continue;
                    try { const r = await postUpdateLink(d.sku, 'Exec', v || null); if (r.success) { row.update({ exec: v }); ok++; } } catch (e) {}
                }
                table.deselectRow();
                alert('Updated ' + ok + ' row(s) to "' + (v || 'Unassigned') + '".');
            });

            // ---- Archive / Restore ----
            function updateMfrgArchiveButtons() {
                // Archive/Restore is restricted to president@5core.com and purchase@5core.com.
                if (!CAN_ARCHIVE) {
                    $('#archive-selected-btn').addClass('d-none');
                    $('#restore-selected-btn').addClass('d-none');
                    return;
                }
                const n = (table.getSelectedRows() || []).length;
                if (showArchived) {
                    $('#archive-selected-btn').addClass('d-none');
                    $('#restore-selected-btn').removeClass('d-none').prop('disabled', n === 0);
                } else {
                    $('#restore-selected-btn').addClass('d-none');
                    $('#archive-selected-btn').toggleClass('d-none', n === 0);
                }
            }
            table.on("rowSelectionChanged", updateMfrgArchiveButtons);

            $('#show-archived-toggle').on('change', function () {
                showArchived = this.checked;
                table.deselectRow();
                table.replaceData();
            });

            function bulkArchiveRestore(endpoint, confirmMsg) {
                // Only act on rows that are BOTH selected AND currently visible under the active
                // filter — a "select all" header check can otherwise include filtered-out rows.
                const activeSet = new Set(table.getRows("active"));
                const selectedRows = table.getSelectedRows().filter(r => activeSet.has(r));
                const skus = [...new Set(
                    selectedRows.map(r => (r.getData().sku || '').trim()).filter(Boolean)
                )];
                if (!skus.length) { alert('No rows selected in the current view.'); return; }
                if (!confirm(confirmMsg.replace('{n}', skus.length))) return;
                fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify({ skus: skus }) })
                    .then(r => r.json())
                    .then(r => { if (r.success) { table.deselectRow(); table.replaceData(); if (r.message) alert(r.message); } else alert(r.message || 'Failed.'); })
                    .catch(() => alert('Network error.'));
            }
            $('#archive-selected-btn').on('click', function () { bulkArchiveRestore('/mfrg-progresses/delete', 'Archive {n} row(s)?'); });
            $('#restore-selected-btn').on('click', function () { bulkArchiveRestore('/mfrg-progresses/restore', 'Restore {n} row(s)?'); });

            // ---- Edit All Fields modal (president@5core.com only) ----
            function toDateInput(raw) {
                if (!raw) return '';
                const d = new Date(raw);
                if (isNaN(d.getTime())) {
                    const m = String(raw).match(/^\d{4}-\d{2}-\d{2}/);
                    return m ? m[0] : '';
                }
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const dd = String(d.getDate()).padStart(2, '0');
                return d.getFullYear() + '-' + mm + '-' + dd;
            }
            const YESNO = [['Yes', 'Yes'], ['No', 'No']];
            const EDIT_FIELDS = [
                { key: 'sku', label: 'SKU', type: 'text', readonly: true },
                { key: 'exec', label: 'Executive', type: 'select', options: function () { return [['', '— Unassigned —']].concat(EXEC_OPTIONS.map(function (n) { return [n, n]; })); } },
                { key: 'qty', label: 'QTY', type: 'number' },
                { key: 'created_at', label: 'O Date', type: 'date' },
                { key: 'delivery_date', label: 'D Date', type: 'date' },
                { key: 'supplier', label: 'Supplier', type: 'select', options: function () { return [['', '']].concat(uniqueSuppliers.map(function (s) { return [s, s]; })); } },
                { key: 'supplier_sku', label: 'Supplier SKU', type: 'text' },
                { key: 'rate', label: 'Rate (CP)', type: 'number' },
                { key: 'CBM', label: 'CBM', type: 'number', note: 'Saved to product master' },
                { key: 'mip_po_number', label: 'PO Number', type: 'text', readonly: true },
                { key: 'pkg_inst', label: 'Pkg Inst', type: 'select', options: function () { return YESNO; } },
                { key: 'u_manual', label: 'U-Manual', type: 'select', options: function () { return YESNO; } },
                { key: 'compliance', label: 'Compliance', type: 'select', options: function () { return YESNO; } },
                { key: 'ready_to_ship', label: 'Ready To Ship', type: 'select', options: function () { return YESNO; } },
                { key: 'barcode_sku', label: 'Barcode SKU', type: 'text' },
                { key: 'artwork_manual_book', label: 'Artwork Manual Book', type: 'text' },
                { key: 'o_links', label: 'O Links', type: 'text' },
                { key: 'notes', label: 'Notes', type: 'textarea' },
                { key: 'stage', label: 'Stage', type: 'select', options: function () {
                    return [['', 'Select'], ['appr_req', 'Appr. Req'], ['mip', 'MIP'], ['r2s', 'R2S'], ['transit', 'Transit'], ['all_good', 'All Good'], ['to_order_analysis', '2 Order']];
                } },
            ];
            function openEditModal(row) {
                window._mipEditRow = row;
                const d = row.getData();
                document.getElementById('mip-edit-sku').textContent = d.sku || '';
                let html = '';
                EDIT_FIELDS.forEach(function (f) {
                    const id = 'medit-' + f.key;
                    let val = d[f.key] == null ? '' : d[f.key];
                    let input = '';
                    if (f.type === 'select') {
                        const opts = (f.options ? f.options() : []).map(function (o) {
                            return '<option value="' + esc(o[0]) + '"' + (String(o[0]) === String(val) ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
                        }).join('');
                        input = '<select class="form-select form-select-sm" id="' + id + '" data-key="' + esc(f.key) + '"' + (f.readonly ? ' disabled' : '') + '>' + opts + '</select>';
                    } else if (f.type === 'textarea') {
                        input = '<textarea class="form-control form-control-sm" id="' + id + '" data-key="' + esc(f.key) + '" rows="2"' + (f.readonly ? ' readonly' : '') + '>' + esc(val) + '</textarea>';
                    } else {
                        if (f.type === 'date') val = toDateInput(val);
                        input = '<input type="' + f.type + '" class="form-control form-control-sm" id="' + id + '" data-key="' + esc(f.key) + '" value="' + esc(val) + '"' + (f.readonly ? ' readonly' : '') + '>';
                    }
                    html += '<div class="col-md-4">' +
                        '<label class="form-label small fw-semibold mb-1" for="' + id + '">' + esc(f.label) + '</label>' +
                        input +
                        (f.note ? '<div class="text-muted" style="font-size:0.7rem;">' + esc(f.note) + '</div>' : '') +
                        '</div>';
                });
                document.getElementById('mip-edit-form').innerHTML = html;
                new bootstrap.Modal(document.getElementById('mipEditModal')).show();
            }
            document.getElementById('mip-edit-save').addEventListener('click', async function () {
                const row = window._mipEditRow;
                if (!row) return;
                const d = row.getData();
                const sku = d.sku || '';
                const mipId = d.id || 0;
                const btn = this;
                btn.disabled = true;
                const updates = {};
                const tasks = [];
                document.querySelectorAll('#mip-edit-form [data-key]').forEach(function (el) {
                    const key = el.dataset.key;
                    const field = EDIT_FIELDS.find(function (f) { return f.key === key; });
                    if (!field || field.readonly) return;
                    let newVal = el.value;
                    let oldVal = d[key] == null ? '' : d[key];
                    if (field.type === 'date') oldVal = toDateInput(oldVal);
                    if (String(newVal) === String(oldVal)) return; // unchanged
                    if (key === 'exec') {
                        tasks.push(postUpdateLink(sku, 'Exec', newVal || null));
                        updates.exec = newVal;
                    } else if (key === 'stage') {
                        tasks.push(Promise.resolve(postStage(sku, d.parent, newVal)));
                        updates.stage = newVal;
                    } else if (key === 'CBM') {
                        tasks.push(Promise.resolve(postForecastData(sku, d.parent, 'CBM', newVal)));
                        updates.CBM = newVal;
                    } else {
                        tasks.push(postInline(sku, mipId, key, newVal));
                        updates[key] = newVal;
                    }
                });
                if (tasks.length === 0) {
                    btn.disabled = false;
                    bootstrap.Modal.getInstance(document.getElementById('mipEditModal')).hide();
                    return;
                }
                // Reflect changes in the grid immediately (optimistic), then persist.
                try { row.update(updates); row.reformat(); } catch (e) {}
                updateStats();
                try {
                    await Promise.all(tasks);
                    // Re-apply + reformat after server confirms, so computed columns are accurate.
                    row.update(updates); row.reformat();
                    updateStats();
                } catch (err) {
                    alert('Some changes could not be saved. Please retry.');
                }
                btn.disabled = false;
                bootstrap.Modal.getInstance(document.getElementById('mipEditModal')).hide();
            });

            // ---- Follow-Up / Current Status ----
            function fmtFollowupDate(raw) {
                if (!raw) return '';
                const d = new Date(raw);
                if (isNaN(d.getTime())) return '';
                return d.getDate() + ' ' + d.toLocaleString('en-US', { month: 'short' });
            }
            function loadFollowupHistory(supplier) {
                const box = document.getElementById('followup-history-list');
                if (!supplier) { box.innerHTML = '<p class="text-muted small mb-0">Select a supplier to view history.</p>'; return; }
                box.innerHTML = '<p class="text-muted small mb-0"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
                fetch('/purchase-master/follow-up-history/supplier/' + encodeURIComponent(supplier))
                    .then(r => r.json())
                    .then(res => {
                        const list = (res && res.data) ? res.data : [];
                        if (!list.length) { box.innerHTML = '<p class="text-muted small mb-0">No history yet.</p>'; return; }
                        let html = '<div class="list-group">';
                        list.forEach(function (it) {
                            html += '<div class="list-group-item py-2">' +
                                '<div class="d-flex justify-content-between"><span class="fw-semibold small">' + esc(it.created_by || 'Unknown') + '</span>' +
                                '<span class="text-muted small">' + fmtFollowupDate(it.created_at) + '</span></div>' +
                                '<div class="small mt-1">' + esc(it.remark || '') + '</div></div>';
                        });
                        html += '</div>';
                        box.innerHTML = html;
                    })
                    .catch(() => { box.innerHTML = '<p class="text-danger small mb-0">Failed to load history.</p>'; });
            }
            function populateFollowupSuppliers() {
                const sel = document.getElementById('followup-supplier-select');
                const cur = sel.value;
                sel.innerHTML = '<option value="">-- Select supplier --</option>';
                uniqueSuppliers.forEach(function (s) {
                    const o = document.createElement('option');
                    o.value = s; o.textContent = s;
                    if (s === cur) o.selected = true;
                    sel.appendChild(o);
                });
            }
            document.getElementById('mip-followup-btn').addEventListener('click', function () {
                populateFollowupSuppliers();
                document.getElementById('followup-remark-input').value = '';
                loadFollowupHistory(document.getElementById('followup-supplier-select').value);
                new bootstrap.Modal(document.getElementById('mipFollowupModal')).show();
            });
            document.getElementById('followup-supplier-select').addEventListener('change', function () {
                loadFollowupHistory(this.value);
            });
            document.getElementById('followup-save-btn').addEventListener('click', function () {
                const supplier = document.getElementById('followup-supplier-select').value;
                const remark = document.getElementById('followup-remark-input').value.trim();
                if (!supplier) { alert('Please select a supplier.'); return; }
                if (!remark) { alert('Please enter the current status.'); return; }
                const btn = this;
                btn.disabled = true;
                fetch('/purchase-master/follow-up-history/store', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ supplier_name: supplier, remark: remark })
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            document.getElementById('followup-remark-input').value = '';
                            loadFollowupHistory(supplier);
                        } else {
                            alert(res.message || 'Failed to save.');
                        }
                    })
                    .catch(() => alert('Network error.'))
                    .finally(() => { btn.disabled = false; });
            });
        });
    </script>
@endsection
