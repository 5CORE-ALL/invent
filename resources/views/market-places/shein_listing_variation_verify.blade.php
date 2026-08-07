@extends('layouts.vertical', ['title' => 'Shein Listing Variation Verify'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #shein-lvv-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
        }
        #shein-lvv-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #shein-lvv-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #shein-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #shein-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #shein-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important; display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #shein-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #shein-lvv-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #shein-lvv-wrap .tabulator .tabulator-row { min-height: 32px; }
        #shein-lvv-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #shein-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }

        #shein-lvv-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
        }
        #shein-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #shein-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #shein-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #shein-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #shein-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #shein-lvv-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        #shein-lvv-wrap { overflow-x: auto; overflow-y: visible; }

        #shein-lvv-wrap .tabulator-row.shein-lvv-parent-row,
        #shein-lvv-wrap .tabulator-row.shein-lvv-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            color: #664d03;
        }
        #shein-lvv-wrap .tabulator-row.shein-lvv-parent-row:hover,
        #shein-lvv-wrap .tabulator-row.shein-lvv-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        #shein-lvv-filter-bar {
            background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;
        }
        #shein-lvv-filter-bar .shein-lvv-filter-label {
            display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px; letter-spacing: 0.01em;
        }
        #shein-lvv-filter-bar .shein-lvv-filter-select {
            min-width: 120px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;
            color: #64748b; font-size: 0.8125rem; padding-top: 0.35rem; padding-bottom: 0.35rem;
        }

        .shein-stat-badge {
            display: inline-flex; align-items: center; flex-shrink: 0; color: #fff; font-size: 15px; font-weight: 700;
            padding: 9px 16px; border-radius: 8px; white-space: nowrap; line-height: 1.25; letter-spacing: 0.2px;
        }
        .shein-stat-badge > span { margin-left: 4px; font-size: 16px; font-weight: 800; }
        .shein-stat-badge--parents { background: #4c7ed8; }
        .shein-stat-badge--children { background: #8b5cf6; }
        .shein-stat-badge--listed { background: #16a34a; }
        .shein-stat-badge--mismatch { background: #dc2626; }
        .shein-stat-badge--mismatch-inv { background: #dc2626; cursor: pointer; }
        .shein-stat-badge--mismatch-inv:hover { filter: brightness(0.92); }
        .shein-stat-badge--mismatch { cursor: pointer; }
        .shein-stat-badge--mismatch:hover { filter: brightness(0.92); }
        .shein-stat-badge.is-active { outline: 2px solid #0f172a; outline-offset: 2px; }
        .shein-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .shein-raw-icon-btn > i { font-size: 14px; }

        .shein-lvv-avail-yes { color: #16a34a; font-weight: 700; }
        .shein-lvv-avail-no { color: #dc2626; font-weight: 700; }
        .shein-lvv-avail-na { color: #94a3b8; }
        .shein-lvv-avail-partial { color: #ea580c; font-weight: 700; }

        .shein-lvv-diff { display: block; margin-top: 4px; line-height: 1.35; text-align: left; font-weight: 500; }
        .shein-lvv-diff-missing { color: #dc2626; }
        .shein-lvv-diff-extra { color: #2563eb; }
        .shein-lvv-diff-label { font-weight: 700; margin-right: 4px; }
        .shein-lvv-sku-chip {
            display: inline-block; margin: 1px 3px 1px 0; padding: 1px 6px; border-radius: 4px;
            font-size: 11.5px; font-weight: 600; line-height: 1.4;
        }
        .shein-lvv-sku-chip--missing { background: #fee2e2; color: #b91c1c; }
        .shein-lvv-sku-chip--extra { background: #dbeafe; color: #1d4ed8; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shein Listing Variation Verify',
        'sub_title'  => 'Shein Listings',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
                            <span class="shein-stat-badge shein-stat-badge--parents" title="Parents from CP Master">PARENTS:<span id="shein-lvv-badge-parents">0</span></span>
                            <span class="shein-stat-badge shein-stat-badge--children" title="Required child SKUs from CP Master">REQUIRED:<span id="shein-lvv-badge-children">0</span></span>
                            <span class="shein-stat-badge shein-stat-badge--listed" title="Shein listings (shein_pricing_prices)">LISTED:<span id="shein-lvv-badge-listed">0</span></span>
                            <span class="shein-stat-badge shein-stat-badge--mismatch" id="shein-lvv-badge-mismatch-btn" role="button" tabindex="0" title="Filter: mismatch only">MISMATCH:<span id="shein-lvv-badge-mismatch">0</span></span>
                            <span class="shein-stat-badge shein-stat-badge--mismatch-inv" id="shein-lvv-badge-mismatch-inv-btn" role="button" tabindex="0" title="Filter: mismatch parents with Shopify INV &gt; 0">MISMATCH INV&gt;0:<span id="shein-lvv-badge-mismatch-inv">0</span></span>
                        </div>
                        <span id="shein-lvv-total" class="badge bg-secondary">Total: —</span>
                        <span id="shein-lvv-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="shein-lvv-refresh-btn" class="btn btn-sm btn-outline-primary shein-raw-icon-btn" title="Refresh" aria-label="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="shein-lvv-pull-btn" class="btn btn-sm btn-warning text-dark" title="Refresh Shein listings cache">
                            <i class="fas fa-sync-alt me-1"></i> Refresh Listings
                        </button>
                        <button type="button" id="shein-lvv-export-btn" class="btn btn-sm btn-success" title="Export filtered rows to Excel">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </button>
                        <span class="text-muted small" id="shein-lvv-status-line"></span>
                    </div>

                    <div id="shein-lvv-filter-bar" class="mb-2">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="shein-lvv-filter-label" for="shein-lvv-listed-filter">Listed</label>
                                <select id="shein-lvv-listed-filter" class="form-select form-select-sm shein-lvv-filter-select">
                                    <option value="all">All</option>
                                    <option value="mismatch" selected>Mismatch Only</option>
                                    <option value="mismatch_inv">Mismatch INV&gt;0</option>
                                    <option value="match">Match Only</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="shein-lvv-filter-apply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="shein-lvv-filter-clear">Clear</button>
                            </div>
                            <div class="ms-auto d-flex flex-wrap align-items-center gap-2 small">
                                <span class="shein-lvv-sku-chip shein-lvv-sku-chip--missing">Missing</span>
                                <span class="text-muted">in CP Master, not on parent listing</span>
                                <span class="shein-lvv-sku-chip shein-lvv-sku-chip--extra">Excess</span>
                                <span class="text-muted">on parent listing, not in CP Master</span>
                            </div>
                        </div>
                    </div>

                    <div id="shein-lvv-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="shein-lvv-search" class="form-control" placeholder="Search Parent..." autocomplete="off" aria-label="Search Parent" maxlength="100">
                            <span id="shein-lvv-source-label" class="badge bg-dark text-nowrap">CP Master</span>
                        </div>
                        <div id="shein-listing-variation-verify-table"></div>
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
        let sheinLvvTable = null;
        let sheinLvvAllData = [];

        function sheinLvvEscapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function sheinLvvDash(val) {
            if (val === null || val === undefined || val === '') {
                return '<span class="text-muted">--</span>';
            }
            return val;
        }

        function sheinLvvUpdateMeta(meta) {
            if (!meta) return;
            $('#shein-lvv-badge-parents').text((meta.required_parent_count || 0).toLocaleString());
            $('#shein-lvv-badge-children').text((meta.required_child_count || 0).toLocaleString());
            $('#shein-lvv-badge-listed').text((meta.listings_count || 0).toLocaleString());
            $('#shein-lvv-badge-mismatch').text((meta.mismatch_count || 0).toLocaleString());
            $('#shein-lvv-badge-mismatch-inv').text((meta.mismatch_inv_gt0_count || 0).toLocaleString());

            const parts = [];
            if (meta.required_refreshed_at) parts.push('CP Master · ' + meta.required_refreshed_at);
            if (meta.last_pulled_at) parts.push('Listings · ' + meta.last_pulled_at);
            $('#shein-lvv-status-line').text(parts.join(' · '));
            $('#shein-lvv-source-label').text(meta.has_listings_cache ? 'CP Master + Listings' : 'CP Master');
        }

        function sheinLvvUpdateRowCount() {
            if (!sheinLvvTable) return;
            const shown = sheinLvvTable.getDataCount('active');
            const total = sheinLvvTable.getDataCount();
            $('#shein-lvv-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            try {
                $('#shein-lvv-page-info').text('Page: ' + sheinLvvTable.getPage() + ' / ' + sheinLvvTable.getPageMax());
            } catch (e) {
                $('#shein-lvv-page-info').text('Page: —');
            }
        }

        function sheinLvvSyncBadgeActive() {
            const v = $('#shein-lvv-listed-filter').val();
            $('#shein-lvv-badge-mismatch-btn').toggleClass('is-active', v === 'mismatch');
            $('#shein-lvv-badge-mismatch-inv-btn').toggleClass('is-active', v === 'mismatch_inv');
        }

        function sheinLvvApplyFilters() {
            if (!sheinLvvTable) return;
            if (window.ParentExpand) {
                ParentExpand.beforeFilters(function () { sheinLvvApplyFiltersBody(); });
                return;
            }
            sheinLvvApplyFiltersBody();
        }

        function sheinLvvApplyFiltersBody() {
            if (!sheinLvvTable) return;
            sheinLvvTable.clearFilter();

            const listedFilter = $('#shein-lvv-listed-filter').val();
            const q = ($('#shein-lvv-search').val() || '').trim().toLowerCase();

            if (listedFilter === 'mismatch') {
                sheinLvvTable.addFilter(d => d.match_status === false);
            } else if (listedFilter === 'mismatch_inv') {
                sheinLvvTable.addFilter(d => d.match_status === false && (parseFloat(d.INV) || 0) > 0);
            } else if (listedFilter === 'match') {
                sheinLvvTable.addFilter(d => d.match_status === true);
            }

            if (q) {
                sheinLvvTable.addFilter(d => String(d.parent || '').toLowerCase().includes(q));
            }

            sheinLvvSyncBadgeActive();
            sheinLvvUpdateRowCount();
        }

        function sheinLvvFormatRequired(cell) {
            const label = cell.getRow().getData().child_sku_required_label;
            if (label === null || label === undefined || label === '') return sheinLvvDash(null);
            return `<span class="fw-semibold shein-lvv-avail-yes">${sheinLvvEscapeHtml(label)}</span>`;
        }

        function sheinLvvSkuChips(skus, type) {
            if (!Array.isArray(skus) || skus.length === 0) return '';
            const chipCls = type === 'extra' ? 'shein-lvv-sku-chip--extra' : 'shein-lvv-sku-chip--missing';
            return skus.map(s =>
                `<span class="shein-lvv-sku-chip ${chipCls}">${sheinLvvEscapeHtml(s)}</span>`
            ).join('');
        }

        function sheinLvvFormatAvailable(cell) {
            const d = cell.getRow().getData();
            const label = d.child_sku_available_label || '';
            if (!label || label === '—') return sheinLvvDash(null);

            const avail = parseInt(d.child_sku_available_count, 10) || 0;
            const total = parseInt(d.child_sku_total, 10) || 0;
            const extraCount = parseInt(d.extra_count, 10) || 0;

            let cls = 'shein-lvv-avail-partial';
            if (total > 0 && avail === total && extraCount === 0) cls = 'shein-lvv-avail-yes';
            else if (avail === 0) cls = 'shein-lvv-avail-no';

            return `<span class="fw-semibold ${cls}">${sheinLvvEscapeHtml(label)}</span>`;
        }

        function sheinLvvFormatMissingExcess(cell) {
            const d = cell.getRow().getData();
            const missingSkus = Array.isArray(d.missing_skus) ? d.missing_skus : [];
            const extraSkus = Array.isArray(d.extra_skus) ? d.extra_skus : [];

            if (missingSkus.length === 0 && extraSkus.length === 0) {
                return sheinLvvDash(null);
            }

            let html = '';
            if (missingSkus.length > 0) {
                html += `<span class="shein-lvv-diff shein-lvv-diff-missing">`
                    + `<span class="shein-lvv-diff-label">Missing:</span>`
                    + sheinLvvSkuChips(missingSkus, 'missing')
                    + `</span>`;
            }
            if (extraSkus.length > 0) {
                html += `<span class="shein-lvv-diff shein-lvv-diff-extra">`
                    + `<span class="shein-lvv-diff-label">Excess:</span>`
                    + sheinLvvSkuChips(extraSkus, 'extra')
                    + `</span>`;
            }
            return html;
        }

        function sheinLvvExportExcel() {
            if (!sheinLvvTable || typeof XLSX === 'undefined') {
                alert('Export library not loaded. Please refresh and try again.');
                return;
            }

            const rows = sheinLvvTable.getData('active') || [];
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
            XLSX.utils.book_append_sheet(wb, ws, 'Shein Variation Verify');

            const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            XLSX.writeFile(wb, 'shein_listing_variation_verify_' + stamp + '.xlsx');
        }

        $(document).ready(function () {
            sheinLvvTable = new Tabulator('#shein-listing-variation-verify-table', {
                ajaxURL: '{{ route("shein.listing.variation.verify.data") }}',
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    sheinLvvAllData = rows;
                    if (window.ParentExpand) ParentExpand.captureDataset(rows);
                    if (response && response.meta) sheinLvvUpdateMeta(response.meta);
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
                        el.classList.add('shein-lvv-parent-row', 'parent-row', 'pm-parent-row');
                        el.classList.remove('shein-lvv-child-row');
                    } else {
                        el.classList.remove('shein-lvv-parent-row', 'parent-row', 'pm-parent-row');
                        el.classList.add('shein-lvv-child-row');
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
                                if (!sku) return sheinLvvDash(null);
                                return `<span class="fw-semibold text-primary">${sheinLvvEscapeHtml(sku)}</span>`;
                            }
                            const v = cell.getValue() || '';
                            if (!v) return sheinLvvDash(null);
                            return `<span class="fw-semibold">${sheinLvvEscapeHtml(v)}</span>`;
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
                            if (!isFinite(value)) return sheinLvvDash(null);
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
                        formatter: sheinLvvFormatRequired
                    },
                    {
                        title: 'Parent Vs Listed SKU',
                        field: 'child_sku_available_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 140,
                        widthGrow: 1,
                        formatter: sheinLvvFormatAvailable
                    },
                    {
                        title: 'Missing / Excess SKU',
                        field: 'missing_skus',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 320,
                        widthGrow: 4,
                        formatter: sheinLvvFormatMissingExcess,
                        variableHeight: true
                    }
                ]
            });

            if (window.ParentExpand) {
                ParentExpand.configure({
                    parentField: 'parent',
                    skuField: 'sku',
                    getTable: function () { return sheinLvvTable; },
                    getDataset: function () { return sheinLvvAllData; },
                    setDataset: function (rows) { sheinLvvAllData = rows; },
                    onCollapse: function () { sheinLvvApplyFilters(); },
                    onAfterExpand: function () { sheinLvvUpdateRowCount(); },
                });
                ParentExpand.bind();
            }

            sheinLvvTable.on('dataProcessed', sheinLvvApplyFilters);
            sheinLvvTable.on('dataFiltered', sheinLvvUpdateRowCount);
            sheinLvvTable.on('pageLoaded', sheinLvvUpdateRowCount);

            $('#shein-lvv-filter-apply').on('click', sheinLvvApplyFilters);
            $('#shein-lvv-listed-filter').on('change', sheinLvvApplyFilters);
            $('#shein-lvv-badge-mismatch-btn').on('click keypress', function (e) {
                if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
                e.preventDefault();
                const cur = $('#shein-lvv-listed-filter').val();
                $('#shein-lvv-listed-filter').val(cur === 'mismatch' ? 'all' : 'mismatch');
                sheinLvvApplyFilters();
            });
            $('#shein-lvv-badge-mismatch-inv-btn').on('click keypress', function (e) {
                if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
                e.preventDefault();
                const cur = $('#shein-lvv-listed-filter').val();
                $('#shein-lvv-listed-filter').val(cur === 'mismatch_inv' ? 'all' : 'mismatch_inv');
                sheinLvvApplyFilters();
            });
            $('#shein-lvv-filter-clear').on('click', function () {
                $('#shein-lvv-listed-filter').val('all');
                $('#shein-lvv-search').val('');
                sheinLvvApplyFilters();
            });

            let searchTimer = null;
            $('#shein-lvv-search').on('keyup search', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(sheinLvvApplyFilters, 200);
            });

            $('#shein-lvv-refresh-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                sheinLvvTable.setData('{{ route("shein.listing.variation.verify.data") }}')
                    .finally(function () { $btn.prop('disabled', false); });
            });

            $('#shein-lvv-export-btn').on('click', sheinLvvExportExcel);

            $('#shein-lvv-pull-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;

                if (!confirm("Refresh Shein listings from shein_pricing_prices cache?\n\nUse Sync API on Shein Pricing (/shein-pricing) if the cache is empty.")) {
                    return;
                }

                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Refreshing…');

                $.ajax({
                    url: '{{ route("shein.listing.variation.verify.pull") }}',
                    method: 'POST',
                    timeout: 0,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function (res) {
                        if (res.status === 200) {
                            $('#shein-lvv-status-line').text(res.message || 'Pull completed.');
                            sheinLvvTable.setData('{{ route("shein.listing.variation.verify.data") }}');
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
