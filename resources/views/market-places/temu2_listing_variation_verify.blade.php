@extends('layouts.vertical', ['title' => 'Temu 2 Listing Variation Verify'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #temu2-lvv-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
        }
        #temu2-lvv-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #temu2-lvv-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #temu2-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #temu2-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #temu2-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important; display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #temu2-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #temu2-lvv-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #temu2-lvv-wrap .tabulator .tabulator-row { min-height: 32px; }
        #temu2-lvv-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #temu2-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }

        #temu2-lvv-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
        }
        #temu2-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #temu2-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #temu2-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #temu2-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #temu2-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #temu2-lvv-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        #temu2-lvv-wrap { overflow-x: auto; overflow-y: visible; }

        #temu2-lvv-wrap .tabulator-row.temu2-lvv-parent-row,
        #temu2-lvv-wrap .tabulator-row.temu2-lvv-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            color: #664d03;
        }
        #temu2-lvv-wrap .tabulator-row.temu2-lvv-parent-row:hover,
        #temu2-lvv-wrap .tabulator-row.temu2-lvv-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        #temu2-lvv-filter-bar {
            background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;
        }
        #temu2-lvv-filter-bar .temu2-lvv-filter-label {
            display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px; letter-spacing: 0.01em;
        }
        #temu2-lvv-filter-bar .temu2-lvv-filter-select {
            min-width: 120px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;
            color: #64748b; font-size: 0.8125rem; padding-top: 0.35rem; padding-bottom: 0.35rem;
        }

        .temu2-stat-badge {
            display: inline-flex; align-items: center; flex-shrink: 0; color: #fff; font-size: 15px; font-weight: 700;
            padding: 9px 16px; border-radius: 8px; white-space: nowrap; line-height: 1.25; letter-spacing: 0.2px;
        }
        .temu2-stat-badge > span { margin-left: 4px; font-size: 16px; font-weight: 800; }
        .temu2-stat-badge--parents { background: #4c7ed8; }
        .temu2-stat-badge--children { background: #8b5cf6; }
        .temu2-stat-badge--listed { background: #16a34a; }
        .temu2-stat-badge--mismatch { background: #dc2626; }
        .temu2-stat-badge--mismatch-inv { background: #dc2626; cursor: pointer; }
        .temu2-stat-badge--mismatch-inv:hover { filter: brightness(0.92); }
        .temu2-stat-badge--mismatch { cursor: pointer; }
        .temu2-stat-badge--mismatch:hover { filter: brightness(0.92); }
        .temu2-stat-badge.is-active { outline: 2px solid #0f172a; outline-offset: 2px; }
        .temu2-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .temu2-raw-icon-btn > i { font-size: 14px; }

        .temu2-lvv-avail-yes { color: #16a34a; font-weight: 700; }
        .temu2-lvv-avail-no { color: #dc2626; font-weight: 700; }
        .temu2-lvv-avail-na { color: #94a3b8; }
        .temu2-lvv-avail-partial { color: #ea580c; font-weight: 700; }

        .temu2-lvv-diff { display: block; margin-top: 4px; line-height: 1.35; text-align: left; font-weight: 500; }
        .temu2-lvv-diff-missing { color: #dc2626; }
        .temu2-lvv-diff-extra { color: #2563eb; }
        .temu2-lvv-diff-label { font-weight: 700; margin-right: 4px; }
        .temu2-lvv-sku-chip {
            display: inline-block; margin: 1px 3px 1px 0; padding: 1px 6px; border-radius: 4px;
            font-size: 11.5px; font-weight: 600; line-height: 1.4;
        }
        .temu2-lvv-sku-chip--missing { background: #fee2e2; color: #b91c1c; }
        .temu2-lvv-sku-chip--extra { background: #dbeafe; color: #1d4ed8; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Temu 2 Listing Variation Verify',
        'sub_title'  => 'Temu 2 Listings',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
                            <span class="temu2-stat-badge temu2-stat-badge--parents" title="Parents from CP Master">PARENTS:<span id="temu2-lvv-badge-parents">0</span></span>
                            <span class="temu2-stat-badge temu2-stat-badge--children" title="Required child SKUs from CP Master">REQUIRED:<span id="temu2-lvv-badge-children">0</span></span>
                            <span class="temu2-stat-badge temu2-stat-badge--listed" title="Temu 2 listings (temu2_metrics API)">LISTED:<span id="temu2-lvv-badge-listed">0</span></span>
                            <span class="temu2-stat-badge temu2-stat-badge--mismatch" id="temu2-lvv-badge-mismatch-btn" role="button" tabindex="0" title="Filter: mismatch only">MISMATCH:<span id="temu2-lvv-badge-mismatch">0</span></span>
                            <span class="temu2-stat-badge temu2-stat-badge--mismatch-inv" id="temu2-lvv-badge-mismatch-inv-btn" role="button" tabindex="0" title="Filter: mismatch parents with Shopify INV &gt; 0">MISMATCH INV&gt;0:<span id="temu2-lvv-badge-mismatch-inv">0</span></span>
                        </div>
                        <span id="temu2-lvv-total" class="badge bg-secondary">Total: —</span>
                        <span id="temu2-lvv-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="temu2-lvv-refresh-btn" class="btn btn-sm btn-outline-primary temu2-raw-icon-btn" title="Refresh" aria-label="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="temu2-lvv-pull-btn" class="btn btn-sm btn-warning text-dark" title="Refresh Temu 2 listings (inventory report)">
                            <i class="fas fa-sync-alt me-1"></i> Refresh Listings
                        </button>
                        <button type="button" id="temu2-lvv-export-btn" class="btn btn-sm btn-success" title="Export filtered rows to Excel">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </button>
                        <span class="text-muted small" id="temu2-lvv-status-line"></span>
                    </div>

                    <div id="temu2-lvv-filter-bar" class="mb-2">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="temu2-lvv-filter-label" for="temu2-lvv-listed-filter">Listed</label>
                                <select id="temu2-lvv-listed-filter" class="form-select form-select-sm temu2-lvv-filter-select">
                                    <option value="all">All</option>
                                    <option value="mismatch" selected>Mismatch Only</option>
                                    <option value="mismatch_inv">Mismatch INV&gt;0</option>
                                    <option value="match">Match Only</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="temu2-lvv-filter-apply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="temu2-lvv-filter-clear">Clear</button>
                            </div>
                            <div class="ms-auto d-flex flex-wrap align-items-center gap-2 small">
                                <span class="temu2-lvv-sku-chip temu2-lvv-sku-chip--missing">Missing</span>
                                <span class="text-muted">in CP Master, not on parent listing</span>
                                <span class="temu2-lvv-sku-chip temu2-lvv-sku-chip--extra">Excess</span>
                                <span class="text-muted">on parent listing, not in CP Master</span>
                            </div>
                        </div>
                    </div>

                    <div id="temu2-lvv-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="temu2-lvv-search" class="form-control" placeholder="Search Parent..." autocomplete="off" aria-label="Search Parent" maxlength="100">
                            <span id="temu2-lvv-source-label" class="badge bg-dark text-nowrap">CP Master</span>
                        </div>
                        <div id="temu2-listing-variation-verify-table"></div>
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
        let temu2LvvTable = null;
        let temu2LvvAllData = [];

        function temu2LvvEscapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function temu2LvvDash(val) {
            if (val === null || val === undefined || val === '') {
                return '<span class="text-muted">--</span>';
            }
            return val;
        }

        function temu2LvvUpdateMeta(meta) {
            if (!meta) return;
            $('#temu2-lvv-badge-parents').text((meta.required_parent_count || 0).toLocaleString());
            $('#temu2-lvv-badge-children').text((meta.required_child_count || 0).toLocaleString());
            $('#temu2-lvv-badge-listed').text((meta.listings_count || 0).toLocaleString());
            $('#temu2-lvv-badge-mismatch').text((meta.mismatch_count || 0).toLocaleString());
            $('#temu2-lvv-badge-mismatch-inv').text((meta.mismatch_inv_gt0_count || 0).toLocaleString());

            const parts = [];
            if (meta.required_refreshed_at) parts.push('CP Master · ' + meta.required_refreshed_at);
            if (meta.last_pulled_at) parts.push('Listings · ' + meta.last_pulled_at);
            $('#temu2-lvv-status-line').text(parts.join(' · '));
            $('#temu2-lvv-source-label').text(meta.has_listings_cache ? 'CP Master + Listings' : 'CP Master');
        }

        function temu2LvvUpdateRowCount() {
            if (!temu2LvvTable) return;
            const shown = temu2LvvTable.getDataCount('active');
            const total = temu2LvvTable.getDataCount();
            $('#temu2-lvv-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            try {
                $('#temu2-lvv-page-info').text('Page: ' + temu2LvvTable.getPage() + ' / ' + temu2LvvTable.getPageMax());
            } catch (e) {
                $('#temu2-lvv-page-info').text('Page: —');
            }
        }

        function temu2LvvSyncBadgeActive() {
            const v = $('#temu2-lvv-listed-filter').val();
            $('#temu2-lvv-badge-mismatch-btn').toggleClass('is-active', v === 'mismatch');
            $('#temu2-lvv-badge-mismatch-inv-btn').toggleClass('is-active', v === 'mismatch_inv');
        }

        function temu2LvvApplyFilters() {
            if (!temu2LvvTable) return;
            if (window.ParentExpand) {
                ParentExpand.beforeFilters(function () { temu2LvvApplyFiltersBody(); });
                return;
            }
            temu2LvvApplyFiltersBody();
        }

        function temu2LvvApplyFiltersBody() {
            if (!temu2LvvTable) return;
            temu2LvvTable.clearFilter();

            const listedFilter = $('#temu2-lvv-listed-filter').val();
            const q = ($('#temu2-lvv-search').val() || '').trim().toLowerCase();

            if (listedFilter === 'mismatch') {
                temu2LvvTable.addFilter(d => d.match_status === false);
            } else if (listedFilter === 'mismatch_inv') {
                temu2LvvTable.addFilter(d => d.match_status === false && (parseFloat(d.INV) || 0) > 0);
            } else if (listedFilter === 'match') {
                temu2LvvTable.addFilter(d => d.match_status === true);
            }

            if (q) {
                temu2LvvTable.addFilter(d => String(d.parent || '').toLowerCase().includes(q));
            }

            temu2LvvSyncBadgeActive();
            temu2LvvUpdateRowCount();
        }

        function temu2LvvFormatRequired(cell) {
            const label = cell.getRow().getData().child_sku_required_label;
            if (label === null || label === undefined || label === '') return temu2LvvDash(null);
            return `<span class="fw-semibold temu2-lvv-avail-yes">${temu2LvvEscapeHtml(label)}</span>`;
        }

        function temu2LvvSkuChips(skus, type) {
            if (!Array.isArray(skus) || skus.length === 0) return '';
            const chipCls = type === 'extra' ? 'temu2-lvv-sku-chip--extra' : 'temu2-lvv-sku-chip--missing';
            return skus.map(s =>
                `<span class="temu2-lvv-sku-chip ${chipCls}">${temu2LvvEscapeHtml(s)}</span>`
            ).join('');
        }

        function temu2LvvFormatAvailable(cell) {
            const d = cell.getRow().getData();
            const label = d.child_sku_available_label || '';
            if (!label || label === '—') return temu2LvvDash(null);

            const avail = parseInt(d.child_sku_available_count, 10) || 0;
            const total = parseInt(d.child_sku_total, 10) || 0;
            const extraCount = parseInt(d.extra_count, 10) || 0;

            let cls = 'temu2-lvv-avail-partial';
            if (total > 0 && avail === total && extraCount === 0) cls = 'temu2-lvv-avail-yes';
            else if (avail === 0) cls = 'temu2-lvv-avail-no';

            return `<span class="fw-semibold ${cls}">${temu2LvvEscapeHtml(label)}</span>`;
        }

        function temu2LvvFormatMissingExcess(cell) {
            const d = cell.getRow().getData();
            const missingSkus = Array.isArray(d.missing_skus) ? d.missing_skus : [];
            const extraSkus = Array.isArray(d.extra_skus) ? d.extra_skus : [];

            if (missingSkus.length === 0 && extraSkus.length === 0) {
                return temu2LvvDash(null);
            }

            let html = '';
            if (missingSkus.length > 0) {
                html += `<span class="temu2-lvv-diff temu2-lvv-diff-missing">`
                    + `<span class="temu2-lvv-diff-label">Missing:</span>`
                    + temu2LvvSkuChips(missingSkus, 'missing')
                    + `</span>`;
            }
            if (extraSkus.length > 0) {
                html += `<span class="temu2-lvv-diff temu2-lvv-diff-extra">`
                    + `<span class="temu2-lvv-diff-label">Excess:</span>`
                    + temu2LvvSkuChips(extraSkus, 'extra')
                    + `</span>`;
            }
            return html;
        }

        function temu2LvvExportExcel() {
            if (!temu2LvvTable || typeof XLSX === 'undefined') {
                alert('Export library not loaded. Please refresh and try again.');
                return;
            }

            const rows = temu2LvvTable.getData('active') || [];
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
            XLSX.utils.book_append_sheet(wb, ws, 'Temu2 Variation Verify');

            const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            XLSX.writeFile(wb, 'temu2_listing_variation_verify_' + stamp + '.xlsx');
        }

        $(document).ready(function () {
            temu2LvvTable = new Tabulator('#temu2-listing-variation-verify-table', {
                ajaxURL: '{{ route("temu2.listing.variation.verify.data") }}',
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    temu2LvvAllData = rows;
                    if (window.ParentExpand) ParentExpand.captureDataset(rows);
                    if (response && response.meta) temu2LvvUpdateMeta(response.meta);
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
                        el.classList.add('temu2-lvv-parent-row', 'parent-row', 'pm-parent-row');
                        el.classList.remove('temu2-lvv-child-row');
                    } else {
                        el.classList.remove('temu2-lvv-parent-row', 'parent-row', 'pm-parent-row');
                        el.classList.add('temu2-lvv-child-row');
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
                                if (!sku) return temu2LvvDash(null);
                                return `<span class="fw-semibold text-primary">${temu2LvvEscapeHtml(sku)}</span>`;
                            }
                            const v = cell.getValue() || '';
                            if (!v) return temu2LvvDash(null);
                            return `<span class="fw-semibold">${temu2LvvEscapeHtml(v)}</span>`;
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
                            if (!isFinite(value)) return temu2LvvDash(null);
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
                        formatter: temu2LvvFormatRequired
                    },
                    {
                        title: 'Parent Vs Listed SKU',
                        field: 'child_sku_available_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 140,
                        widthGrow: 1,
                        formatter: temu2LvvFormatAvailable
                    },
                    {
                        title: 'Missing / Excess SKU',
                        field: 'missing_skus',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 320,
                        widthGrow: 4,
                        formatter: temu2LvvFormatMissingExcess,
                        variableHeight: true
                    }
                ]
            });

            if (window.ParentExpand) {
                ParentExpand.configure({
                    parentField: 'parent',
                    skuField: 'sku',
                    getTable: function () { return temu2LvvTable; },
                    getDataset: function () { return temu2LvvAllData; },
                    setDataset: function (rows) { temu2LvvAllData = rows; },
                    onCollapse: function () { temu2LvvApplyFilters(); },
                    onAfterExpand: function () { temu2LvvUpdateRowCount(); },
                });
                ParentExpand.bind();
            }

            temu2LvvTable.on('dataProcessed', temu2LvvApplyFilters);
            temu2LvvTable.on('dataFiltered', temu2LvvUpdateRowCount);
            temu2LvvTable.on('pageLoaded', temu2LvvUpdateRowCount);

            $('#temu2-lvv-filter-apply').on('click', temu2LvvApplyFilters);
            $('#temu2-lvv-listed-filter').on('change', temu2LvvApplyFilters);
            $('#temu2-lvv-badge-mismatch-btn').on('click keypress', function (e) {
                if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
                e.preventDefault();
                const cur = $('#temu2-lvv-listed-filter').val();
                $('#temu2-lvv-listed-filter').val(cur === 'mismatch' ? 'all' : 'mismatch');
                temu2LvvApplyFilters();
            });
            $('#temu2-lvv-badge-mismatch-inv-btn').on('click keypress', function (e) {
                if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
                e.preventDefault();
                const cur = $('#temu2-lvv-listed-filter').val();
                $('#temu2-lvv-listed-filter').val(cur === 'mismatch_inv' ? 'all' : 'mismatch_inv');
                temu2LvvApplyFilters();
            });
            $('#temu2-lvv-filter-clear').on('click', function () {
                $('#temu2-lvv-listed-filter').val('all');
                $('#temu2-lvv-search').val('');
                temu2LvvApplyFilters();
            });

            let searchTimer = null;
            $('#temu2-lvv-search').on('keyup search', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(temu2LvvApplyFilters, 200);
            });

            $('#temu2-lvv-refresh-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                temu2LvvTable.setData('{{ route("temu2.listing.variation.verify.data") }}')
                    .finally(function () { $btn.prop('disabled', false); });
            });

            $('#temu2-lvv-export-btn').on('click', temu2LvvExportExcel);

            $('#temu2-lvv-pull-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;

                if (!confirm('Sync Temu 2 listings from Open API into temu2_metrics?')) {
                    return;
                }

                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Refreshing…');

                $.ajax({
                    url: '{{ route("temu2.listing.variation.verify.pull") }}',
                    method: 'POST',
                    timeout: 0,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function (res) {
                        if (res.status === 200) {
                            $('#temu2-lvv-status-line').text(res.message || 'Pull completed.');
                            temu2LvvTable.setData('{{ route("temu2.listing.variation.verify.data") }}');
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
