@extends('layouts.vertical', ['title' => 'PLS Listing Variation Verify'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #pls-lvv-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
        }
        #pls-lvv-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #pls-lvv-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #pls-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #pls-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #pls-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important; display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #pls-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #pls-lvv-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #pls-lvv-wrap .tabulator .tabulator-row { min-height: 32px; }
        #pls-lvv-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #pls-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }

        #pls-lvv-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
        }
        #pls-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #pls-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #pls-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #pls-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #pls-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #pls-lvv-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        #pls-lvv-wrap { overflow-x: auto; overflow-y: visible; }

        #pls-lvv-wrap .tabulator-row.pls-lvv-parent-row,
        #pls-lvv-wrap .tabulator-row.pls-lvv-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            color: #664d03;
        }
        #pls-lvv-wrap .tabulator-row.pls-lvv-parent-row:hover,
        #pls-lvv-wrap .tabulator-row.pls-lvv-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        #pls-lvv-filter-bar {
            background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;
        }
        #pls-lvv-filter-bar .pls-lvv-filter-label {
            display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px; letter-spacing: 0.01em;
        }
        #pls-lvv-filter-bar .pls-lvv-filter-select {
            min-width: 120px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;
            color: #64748b; font-size: 0.8125rem; padding-top: 0.35rem; padding-bottom: 0.35rem;
        }

        .pls-stat-badge {
            display: inline-flex; align-items: center; flex-shrink: 0; color: #fff; font-size: 15px; font-weight: 700;
            padding: 9px 16px; border-radius: 8px; white-space: nowrap; line-height: 1.25; letter-spacing: 0.2px;
        }
        .pls-stat-badge > span { margin-left: 4px; font-size: 16px; font-weight: 800; }
        .pls-stat-badge--parents { background: #4c7ed8; }
        .pls-stat-badge--children { background: #8b5cf6; }
        .pls-stat-badge--listed { background: #16a34a; }
        .pls-stat-badge--mismatch { background: #dc2626; }
        .pls-stat-badge--mismatch-inv { background: #dc2626; cursor: pointer; }
        .pls-stat-badge--mismatch-inv:hover { filter: brightness(0.92); }
        .pls-stat-badge--mismatch { cursor: pointer; }
        .pls-stat-badge--mismatch:hover { filter: brightness(0.92); }
        .pls-stat-badge.is-active { outline: 2px solid #0f172a; outline-offset: 2px; }
        .pls-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .pls-raw-icon-btn > i { font-size: 14px; }

        .pls-lvv-avail-yes { color: #16a34a; font-weight: 700; }
        .pls-lvv-avail-no { color: #dc2626; font-weight: 700; }
        .pls-lvv-avail-na { color: #94a3b8; }
        .pls-lvv-avail-partial { color: #ea580c; font-weight: 700; }

        .pls-lvv-diff { display: block; margin-top: 4px; line-height: 1.35; text-align: left; font-weight: 500; }
        .pls-lvv-diff-missing { color: #dc2626; }
        .pls-lvv-diff-extra { color: #2563eb; }
        .pls-lvv-diff-label { font-weight: 700; margin-right: 4px; }
        .pls-lvv-sku-chip {
            display: inline-block; margin: 1px 3px 1px 0; padding: 1px 6px; border-radius: 4px;
            font-size: 11.5px; font-weight: 600; line-height: 1.4;
        }
        .pls-lvv-sku-chip--missing { background: #fee2e2; color: #b91c1c; }
        .pls-lvv-sku-chip--extra { background: #dbeafe; color: #1d4ed8; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'PLS Listing Variation Verify',
        'sub_title'  => 'PLS Listings',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
                            <span class="pls-stat-badge pls-stat-badge--parents" title="Parents from CP Master">PARENTS:<span id="pls-lvv-badge-parents">0</span></span>
                            <span class="pls-stat-badge pls-stat-badge--children" title="Required child SKUs from CP Master">REQUIRED:<span id="pls-lvv-badge-children">0</span></span>
                            <span class="pls-stat-badge pls-stat-badge--listed" title="PLS listings (pls_products)">LISTED:<span id="pls-lvv-badge-listed">0</span></span>
                            <span class="pls-stat-badge pls-stat-badge--mismatch" id="pls-lvv-badge-mismatch-btn" role="button" tabindex="0" title="Filter: mismatch only">MISMATCH:<span id="pls-lvv-badge-mismatch">0</span></span>
                            <span class="pls-stat-badge pls-stat-badge--mismatch-inv" id="pls-lvv-badge-mismatch-inv-btn" role="button" tabindex="0" title="Filter: mismatch parents with Shopify INV &gt; 0">MISMATCH INV&gt;0:<span id="pls-lvv-badge-mismatch-inv">0</span></span>
                        </div>
                        <span id="pls-lvv-total" class="badge bg-secondary">Total: —</span>
                        <span id="pls-lvv-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="pls-lvv-refresh-btn" class="btn btn-sm btn-outline-primary pls-raw-icon-btn" title="Refresh" aria-label="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="pls-lvv-pull-btn" class="btn btn-sm btn-warning text-dark" title="Refresh PLS listings cache">
                            <i class="fas fa-sync-alt me-1"></i> Refresh Listings
                        </button>
                        <button type="button" id="pls-lvv-export-btn" class="btn btn-sm btn-success" title="Export filtered rows to Excel">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </button>
                        <span class="text-muted small" id="pls-lvv-status-line"></span>
                    </div>

                    <div id="pls-lvv-filter-bar" class="mb-2">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="pls-lvv-filter-label" for="pls-lvv-listed-filter">Listed</label>
                                <select id="pls-lvv-listed-filter" class="form-select form-select-sm pls-lvv-filter-select">
                                    <option value="all">All</option>
                                    <option value="mismatch" selected>Mismatch Only</option>
                                    <option value="mismatch_inv">Mismatch INV&gt;0</option>
                                    <option value="match">Match Only</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="pls-lvv-filter-apply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="pls-lvv-filter-clear">Clear</button>
                            </div>
                            <div class="ms-auto d-flex flex-wrap align-items-center gap-2 small">
                                <span class="pls-lvv-sku-chip pls-lvv-sku-chip--missing">Missing</span>
                                <span class="text-muted">in CP Master, not on parent listing</span>
                                <span class="pls-lvv-sku-chip pls-lvv-sku-chip--extra">Excess</span>
                                <span class="text-muted">on parent listing, not in CP Master</span>
                            </div>
                        </div>
                    </div>

                    <div id="pls-lvv-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="pls-lvv-search" class="form-control" placeholder="Search Parent..." autocomplete="off" aria-label="Search Parent" maxlength="100">
                            <span id="pls-lvv-source-label" class="badge bg-dark text-nowrap">CP Master</span>
                        </div>
                        <div id="pls-listing-variation-verify-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        let plsLvvTable = null;
        let plsLvvAllData = [];

        function plsLvvEscapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function plsLvvDash(val) {
            if (val === null || val === undefined || val === '') {
                return '<span class="text-muted">--</span>';
            }
            return val;
        }

        function plsLvvUpdateMeta(meta) {
            if (!meta) return;
            $('#pls-lvv-badge-parents').text((meta.required_parent_count || 0).toLocaleString());
            $('#pls-lvv-badge-children').text((meta.required_child_count || 0).toLocaleString());
            $('#pls-lvv-badge-listed').text((meta.listings_count || 0).toLocaleString());
            $('#pls-lvv-badge-mismatch').text((meta.mismatch_count || 0).toLocaleString());
            $('#pls-lvv-badge-mismatch-inv').text((meta.mismatch_inv_gt0_count || 0).toLocaleString());

            const parts = [];
            if (meta.required_refreshed_at) parts.push('CP Master · ' + meta.required_refreshed_at);
            if (meta.last_pulled_at) parts.push('Listings · ' + meta.last_pulled_at);
            $('#pls-lvv-status-line').text(parts.join(' · '));
            $('#pls-lvv-source-label').text(meta.has_listings_cache ? 'CP Master + Listings' : 'CP Master');
        }

        function plsLvvUpdateRowCount() {
            if (!plsLvvTable) return;
            const shown = plsLvvTable.getDataCount('active');
            const total = plsLvvTable.getDataCount();
            $('#pls-lvv-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            try {
                $('#pls-lvv-page-info').text('Page: ' + plsLvvTable.getPage() + ' / ' + plsLvvTable.getPageMax());
            } catch (e) {
                $('#pls-lvv-page-info').text('Page: —');
            }
        }

        function plsLvvSyncBadgeActive() {
            const v = $('#pls-lvv-listed-filter').val();
            $('#pls-lvv-badge-mismatch-btn').toggleClass('is-active', v === 'mismatch');
            $('#pls-lvv-badge-mismatch-inv-btn').toggleClass('is-active', v === 'mismatch_inv');
        }

        function plsLvvApplyFilters() {
            if (!plsLvvTable) return;
            if (window.ParentExpand) {
                ParentExpand.beforeFilters(function () { plsLvvApplyFiltersBody(); });
                return;
            }
            plsLvvApplyFiltersBody();
        }

        function plsLvvApplyFiltersBody() {
            if (!plsLvvTable) return;
            plsLvvTable.clearFilter();

            const listedFilter = $('#pls-lvv-listed-filter').val();
            const q = ($('#pls-lvv-search').val() || '').trim().toLowerCase();

            if (listedFilter === 'mismatch') {
                plsLvvTable.addFilter(d => d.match_status === false);
            } else if (listedFilter === 'mismatch_inv') {
                plsLvvTable.addFilter(d => d.match_status === false && (parseFloat(d.INV) || 0) > 0);
            } else if (listedFilter === 'match') {
                plsLvvTable.addFilter(d => d.match_status === true);
            }

            if (q) {
                plsLvvTable.addFilter(d => String(d.parent || '').toLowerCase().includes(q));
            }

            plsLvvSyncBadgeActive();
            plsLvvUpdateRowCount();
        }

        function plsLvvFormatRequired(cell) {
            const label = cell.getRow().getData().child_sku_required_label;
            if (label === null || label === undefined || label === '') return plsLvvDash(null);
            return `<span class="fw-semibold pls-lvv-avail-yes">${plsLvvEscapeHtml(label)}</span>`;
        }

        function plsLvvSkuChips(skus, type) {
            if (!Array.isArray(skus) || skus.length === 0) return '';
            const chipCls = type === 'extra' ? 'pls-lvv-sku-chip--extra' : 'pls-lvv-sku-chip--missing';
            return skus.map(s =>
                `<span class="pls-lvv-sku-chip ${chipCls}">${plsLvvEscapeHtml(s)}</span>`
            ).join('');
        }

        function plsLvvFormatAvailable(cell) {
            const d = cell.getRow().getData();
            const label = d.child_sku_available_label || '';
            if (!label || label === '—') return plsLvvDash(null);

            const avail = parseInt(d.child_sku_available_count, 10) || 0;
            const total = parseInt(d.child_sku_total, 10) || 0;
            const extraCount = parseInt(d.extra_count, 10) || 0;

            let cls = 'pls-lvv-avail-partial';
            if (total > 0 && avail === total && extraCount === 0) cls = 'pls-lvv-avail-yes';
            else if (avail === 0) cls = 'pls-lvv-avail-no';

            return `<span class="fw-semibold ${cls}">${plsLvvEscapeHtml(label)}</span>`;
        }

        function plsLvvFormatMissingExcess(cell) {
            const d = cell.getRow().getData();
            const missingSkus = Array.isArray(d.missing_skus) ? d.missing_skus : [];
            const extraSkus = Array.isArray(d.extra_skus) ? d.extra_skus : [];

            if (missingSkus.length === 0 && extraSkus.length === 0) {
                return plsLvvDash(null);
            }

            let html = '';
            if (missingSkus.length > 0) {
                html += `<span class="pls-lvv-diff pls-lvv-diff-missing">`
                    + `<span class="pls-lvv-diff-label">Missing:</span>`
                    + plsLvvSkuChips(missingSkus, 'missing')
                    + `</span>`;
            }
            if (extraSkus.length > 0) {
                html += `<span class="pls-lvv-diff pls-lvv-diff-extra">`
                    + `<span class="pls-lvv-diff-label">Excess:</span>`
                    + plsLvvSkuChips(extraSkus, 'extra')
                    + `</span>`;
            }
            return html;
        }

        function plsLvvExportExcel() {
            if (!plsLvvTable || typeof XLSX === 'undefined') {
                alert('Export library not loaded. Please refresh and try again.');
                return;
            }

            const rows = plsLvvTable.getData('active') || [];
            if (rows.length === 0) {
                alert('No data to export.');
                return;
            }

            const exportData = rows.map(function (d) {
                const missingSkus = Array.isArray(d.missing_skus) ? d.missing_skus : [];
                const extraSkus = Array.isArray(d.extra_skus) ? d.extra_skus : [];
                let matchLabel = '—';
                if (d.match_status === true) matchLabel = 'Match';
                else if (d.match_status === false) matchLabel = 'Mismatch';

                return {
                    'Parent': d.parent || '',
                    'INV': d.INV ?? 0,
                    'Required': d.child_sku_required_label ?? '',
                    'Parent Vs Listed SKU': d.child_sku_available_label || '',
                    'Listed Count': d.child_sku_available_count ?? '',
                    'Required Count': d.child_sku_total ?? '',
                    'Missing Count': d.missing_count ?? missingSkus.length,
                    'Excess Count': d.extra_count ?? extraSkus.length,
                    'Missing SKUs': missingSkus.join(', '),
                    'Excess SKUs': extraSkus.join(', '),
                    'Match Status': matchLabel,
                };
            });

            const ws = XLSX.utils.json_to_sheet(exportData);
            ws['!cols'] = [
                { wch: 22 }, { wch: 10 }, { wch: 18 }, { wch: 12 }, { wch: 14 },
                { wch: 12 }, { wch: 12 }, { wch: 50 }, { wch: 50 }, { wch: 12 },
            ];

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'PLS Variation Verify');

            const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            XLSX.writeFile(wb, 'pls_listing_variation_verify_' + stamp + '.xlsx');
        }

        $(document).ready(function () {
            plsLvvTable = new Tabulator('#pls-listing-variation-verify-table', {
                ajaxURL: '{{ route("pls.listing.variation.verify.data") }}',
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    plsLvvAllData = rows;
                    if (window.ParentExpand) ParentExpand.captureDataset(rows);
                    if (response && response.meta) plsLvvUpdateMeta(response.meta);
                    return rows;
                },
                height: '650px',
                layout: 'fitColumns',
                layoutColumnsOnNewData: true,
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 250, 500],
                paginationCounter: 'rows',
                paginationButtonCount: 10,
                placeholder: 'No parents found in CP Master',
                rowFormatter: function (row) {
                    const el = row.getElement();
                    const d = row.getData() || {};
                    const isParent = d.is_parent === true || (window.isPmParentRowData && window.isPmParentRowData(d));
                    if (isParent) {
                        el.classList.add('pls-lvv-parent-row', 'parent-row', 'pm-parent-row');
                        el.classList.remove('pls-lvv-child-row');
                    } else {
                        el.classList.remove('pls-lvv-parent-row', 'parent-row', 'pm-parent-row');
                        el.classList.add('pls-lvv-child-row');
                    }
                },
                columns: [
                    {
                        title: 'Parent',
                        field: 'parent',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 160,
                        widthGrow: 2,
                        formatter: function (cell) {
                            const d = cell.getRow().getData() || {};
                            const isParent = d.is_parent === true || (window.isPmParentRowData && window.isPmParentRowData(d));
                            if (!isParent) {
                                const sku = d.sku || '';
                                if (!sku) return plsLvvDash(null);
                                return `<span class="fw-semibold text-primary">${plsLvvEscapeHtml(sku)}</span>`;
                            }
                            const v = cell.getValue() || '';
                            if (!v) return plsLvvDash(null);
                            return `<span class="fw-semibold">${plsLvvEscapeHtml(v)}</span>`;
                        }
                    },
                    (window.ParentExpand
                        ? ParentExpand.columnDef({ frozen: false })
                        : { title: 'P', field: '_parent_expand', width: 36, headerSort: false, hozAlign: 'center' }),
                    {
                        title: 'INV',
                        field: 'INV',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        sorter: 'number',
                        width: 70,
                        minWidth: 60,
                        headerTooltip: 'Shopify INV — sum of child SKU inventory for this parent',
                        formatter: function (cell) {
                            const value = parseFloat(cell.getValue());
                            if (!isFinite(value)) return plsLvvDash(null);
                            return `<span class="fw-semibold">${Math.round(value)}</span>`;
                        }
                    },
                    {
                        title: 'Required',
                        field: 'child_sku_required_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 100,
                        widthGrow: 1,
                        formatter: plsLvvFormatRequired
                    },
                    {
                        title: 'Parent Vs Listed SKU',
                        field: 'child_sku_available_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 140,
                        widthGrow: 1,
                        formatter: plsLvvFormatAvailable
                    },
                    {
                        title: 'Missing / Excess SKU',
                        field: 'missing_skus',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 320,
                        widthGrow: 4,
                        formatter: plsLvvFormatMissingExcess,
                        variableHeight: true
                    }
                ]
            });

            if (window.ParentExpand) {
                ParentExpand.configure({
                    parentField: 'parent',
                    skuField: 'sku',
                    getTable: function () { return plsLvvTable; },
                    getDataset: function () { return plsLvvAllData; },
                    setDataset: function (rows) { plsLvvAllData = rows; },
                    onCollapse: function () { plsLvvApplyFilters(); },
                    onAfterExpand: function () { plsLvvUpdateRowCount(); },
                });
                ParentExpand.bind();
            }

            plsLvvTable.on('dataProcessed', plsLvvApplyFilters);
            plsLvvTable.on('dataFiltered', plsLvvUpdateRowCount);
            plsLvvTable.on('pageLoaded', plsLvvUpdateRowCount);

            $('#pls-lvv-filter-apply').on('click', plsLvvApplyFilters);
            $('#pls-lvv-listed-filter').on('change', plsLvvApplyFilters);
            $('#pls-lvv-badge-mismatch-btn').on('click keypress', function (e) {
                if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
                e.preventDefault();
                const cur = $('#pls-lvv-listed-filter').val();
                $('#pls-lvv-listed-filter').val(cur === 'mismatch' ? 'all' : 'mismatch');
                plsLvvApplyFilters();
            });
            $('#pls-lvv-badge-mismatch-inv-btn').on('click keypress', function (e) {
                if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
                e.preventDefault();
                const cur = $('#pls-lvv-listed-filter').val();
                $('#pls-lvv-listed-filter').val(cur === 'mismatch_inv' ? 'all' : 'mismatch_inv');
                plsLvvApplyFilters();
            });
            $('#pls-lvv-filter-clear').on('click', function () {
                $('#pls-lvv-listed-filter').val('all');
                $('#pls-lvv-search').val('');
                plsLvvApplyFilters();
            });

            let searchTimer = null;
            $('#pls-lvv-search').on('keyup search', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(plsLvvApplyFilters, 200);
            });

            $('#pls-lvv-refresh-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                plsLvvTable.setData('{{ route("pls.listing.variation.verify.data") }}')
                    .finally(function () { $btn.prop('disabled', false); });
            });

            $('#pls-lvv-export-btn').on('click', plsLvvExportExcel);

            $('#pls-lvv-pull-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;

                if (!confirm("Refresh PLS listings from pls_products cache?\n\nUpdate data on PLS Pricing (/pls-pricing) if the cache is empty.")) {
                    return;
                }

                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Refreshing…');

                $.ajax({
                    url: '{{ route("pls.listing.variation.verify.pull") }}',
                    method: 'POST',
                    timeout: 0,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function (res) {
                        if (res.status === 200) {
                            $('#pls-lvv-status-line').text(res.message || 'Pull completed.');
                            plsLvvTable.setData('{{ route("pls.listing.variation.verify.data") }}');
                        } else {
                            alert(res.message || 'Pull failed.');
                        }
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : ('Pull failed (' + (xhr.status || 'network') + ')');
                        alert(msg);
                    },
                    complete: function () {
                        $btn.prop('disabled', false)
                            .html('<i class="fas fa-sync-alt me-1"></i> Refresh Listings');
                    }
                });
            });
        });
    </script>
@endsection
