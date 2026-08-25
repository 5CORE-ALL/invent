@extends('layouts.vertical', ['title' => 'Ebay 2 Listing Variation Verify', 'skipHighcharts' => true])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #ebay2-lvv-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
            height: 100% !important;
        }
        #ebay2-lvv-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #ebay2-lvv-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #ebay2-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #ebay2-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #ebay2-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important; display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #ebay2-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #ebay2-lvv-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #ebay2-lvv-wrap .tabulator .tabulator-row { min-height: 32px; }
        #ebay2-lvv-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #ebay2-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }

        #ebay2-lvv-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
        }
        #ebay2-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #ebay2-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #ebay2-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #ebay2-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #ebay2-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #ebay2-lvv-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        #ebay2-lvv-wrap {
            display: flex;
            flex-direction: column;
            min-height: 280px;
            overflow: hidden;
        }
        #ebay2-listing-variation-verify-table {
            flex: 1 1 auto;
            min-height: 0;
            width: 100%;
        }

        #ebay2-lvv-wrap .tabulator-row.ebay2-lvv-parent-row,
        #ebay2-lvv-wrap .tabulator-row.ebay2-lvv-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            color: #664d03;
        }
        #ebay2-lvv-wrap .tabulator-row.ebay2-lvv-parent-row:hover,
        #ebay2-lvv-wrap .tabulator-row.ebay2-lvv-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        #ebay2-lvv-filter-bar {
            background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;
        }
        #ebay2-lvv-filter-bar .ebay2-lvv-filter-label {
            display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px; letter-spacing: 0.01em;
        }
        #ebay2-lvv-filter-bar .ebay2-lvv-filter-select {
            min-width: 120px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;
            color: #64748b; font-size: 0.8125rem; padding-top: 0.35rem; padding-bottom: 0.35rem;
        }

        .ebay2-stat-badge {
            display: inline-flex; align-items: center; flex-shrink: 0; color: #fff; font-size: 15px; font-weight: 700;
            padding: 9px 16px; border-radius: 8px; white-space: nowrap; line-height: 1.25; letter-spacing: 0.2px;
        }
        .ebay2-stat-badge > span { margin-left: 4px; font-size: 16px; font-weight: 800; }
        .ebay2-stat-badge--parents { background: #4c7ed8; }
        .ebay2-stat-badge--children { background: #8b5cf6; }
        .ebay2-stat-badge--listed { background: #16a34a; }
        .ebay2-stat-badge--mismatch { background: #dc2626; }
        .ebay2-stat-badge--mismatch-inv { background: #dc2626; cursor: pointer; }
        .ebay2-stat-badge--mismatch-inv:hover { filter: brightness(0.92); }
        .ebay2-stat-badge--mismatch { cursor: pointer; }
        .ebay2-stat-badge--mismatch:hover { filter: brightness(0.92); }
        .ebay2-stat-badge.is-active { outline: 2px solid #0f172a; outline-offset: 2px; }
        .ebay2-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .ebay2-raw-icon-btn > i { font-size: 14px; }

        .ebay2-lvv-avail-yes { color: #16a34a; font-weight: 700; }
        .ebay2-lvv-avail-no { color: #dc2626; font-weight: 700; }
        .ebay2-lvv-avail-na { color: #94a3b8; }
        .ebay2-lvv-avail-partial { color: #ea580c; font-weight: 700; }

        .ebay2-lvv-diff { display: block; margin-top: 4px; line-height: 1.35; text-align: left; font-weight: 500; }
        .ebay2-lvv-diff-missing { color: #dc2626; }
        .ebay2-lvv-diff-extra { color: #2563eb; }
        .ebay2-lvv-diff-label { font-weight: 700; margin-right: 4px; }
        .ebay2-lvv-sku-chip {
            display: inline-block; margin: 1px 3px 1px 0; padding: 1px 6px; border-radius: 4px;
            font-size: 11.5px; font-weight: 600; line-height: 1.4;
        }
        .ebay2-lvv-sku-chip--missing { background: #fee2e2; color: #b91c1c; }
        .ebay2-lvv-sku-chip--extra { background: #dbeafe; color: #1d4ed8; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Ebay 2 Listing Variation Verify',
        'sub_title'  => 'eBay 2 Listings',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
                            <span class="ebay2-stat-badge ebay2-stat-badge--parents" title="Parents from CP Master">PARENTS:<span id="ebay2-lvv-badge-parents">0</span></span>
                            <span class="ebay2-stat-badge ebay2-stat-badge--children" title="Required child SKUs from CP Master">REQUIRED:<span id="ebay2-lvv-badge-children">0</span></span>
                            <span class="ebay2-stat-badge ebay2-stat-badge--listed" title="eBay listings cache (ebay_2_metrics)">LISTED:<span id="ebay2-lvv-badge-listed">0</span></span>
                            <span class="ebay2-stat-badge ebay2-stat-badge--mismatch" id="ebay2-lvv-badge-mismatch-btn" role="button" tabindex="0" title="Filter: mismatch only">MISMATCH:<span id="ebay2-lvv-badge-mismatch">0</span></span>
                            <span class="ebay2-stat-badge ebay2-stat-badge--mismatch-inv" id="ebay2-lvv-badge-mismatch-inv-btn" role="button" tabindex="0" title="Filter: mismatch parents with Shopify INV &gt; 0">MISMATCH INV&gt;0:<span id="ebay2-lvv-badge-mismatch-inv">0</span></span>
                        </div>
                        <span id="ebay2-lvv-total" class="badge bg-secondary">Total: —</span>
                        <span id="ebay2-lvv-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="ebay2-lvv-refresh-btn" class="btn btn-sm btn-outline-primary ebay2-raw-icon-btn" title="Refresh" aria-label="Refresh">
                            <i class="ri-refresh-line"></i>
                        </button>
                        <button type="button" id="ebay2-lvv-pull-btn" class="btn btn-sm btn-warning text-dark" title="Pull eBay listings (inventory report)">
                            <i class="ri-download-cloud-2-line me-1"></i> Pull Listings
                        </button>
                        <button type="button" id="ebay2-lvv-export-btn" class="btn btn-sm btn-success" title="Export filtered rows to Excel">
                            <i class="ri-file-excel-2-line me-1"></i> Export Excel
                        </button>
                        <span class="text-muted small" id="ebay2-lvv-status-line"></span>
                    </div>

                    <div id="ebay2-lvv-filter-bar" class="mb-2">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="ebay2-lvv-filter-label" for="ebay2-lvv-listed-filter">Listed</label>
                                <select id="ebay2-lvv-listed-filter" class="form-select form-select-sm ebay2-lvv-filter-select">
                                    <option value="all">All</option>
                                    <option value="mismatch" selected>Mismatch Only</option>
                                    <option value="mismatch_inv">Mismatch INV&gt;0</option>
                                    <option value="match">Match Only</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="ebay2-lvv-filter-apply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="ebay2-lvv-filter-clear">Clear</button>
                            </div>
                            <div class="ms-auto d-flex flex-wrap align-items-center gap-2 small">
                                <span class="ebay2-lvv-sku-chip ebay2-lvv-sku-chip--missing">Missing</span>
                                <span class="text-muted">in CP Master, not listed on eBay 2, INV &gt; 0</span>
                                <span class="ebay2-lvv-sku-chip ebay2-lvv-sku-chip--extra">Excess</span>
                                <span class="text-muted">listed on eBay 2, not in CP Master</span>
                            </div>
                        </div>
                    </div>

                    <div id="ebay2-lvv-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="ebay2-lvv-search" class="form-control" placeholder="Search Parent..." autocomplete="off" aria-label="Search Parent" maxlength="100">
                            <span id="ebay2-lvv-source-label" class="badge bg-dark text-nowrap">CP Master</span>
                        </div>
                        <div id="ebay2-listing-variation-verify-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        let ebay2LvvTable = null;
        let ebay2LvvAllData = [];
        const ebay2LvvDataUrl = '{{ route("ebay2.listing.variation.verify.data") }}';
        const ebay2LvvXlsxUrl = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';

        function ebay2LvvLoadXlsx() {
            return new Promise(function (resolve, reject) {
                if (typeof XLSX !== 'undefined') {
                    resolve();
                    return;
                }
                const existing = document.querySelector('script[data-ebay2-lvv-xlsx]');
                if (existing) {
                    existing.addEventListener('load', function () { resolve(); });
                    existing.addEventListener('error', function () { reject(new Error('xlsx')); });
                    return;
                }
                const s = document.createElement('script');
                s.src = ebay2LvvXlsxUrl;
                s.async = true;
                s.setAttribute('data-ebay2-lvv-xlsx', '1');
                s.onload = function () { resolve(); };
                s.onerror = function () { reject(new Error('xlsx')); };
                document.head.appendChild(s);
            });
        }

        function ebay2LvvEscapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function ebay2LvvDash(val) {
            if (val === null || val === undefined || val === '') {
                return '<span class="text-muted">--</span>';
            }
            return val;
        }

        function ebay2LvvUpdateMeta(meta) {
            if (!meta) return;
            $('#ebay2-lvv-badge-parents').text((meta.required_parent_count || 0).toLocaleString());
            $('#ebay2-lvv-badge-children').text((meta.required_child_count || 0).toLocaleString());
            $('#ebay2-lvv-badge-listed').text((meta.listings_count || 0).toLocaleString());
            $('#ebay2-lvv-badge-mismatch').text((meta.mismatch_count || 0).toLocaleString());
            $('#ebay2-lvv-badge-mismatch-inv').text((meta.mismatch_inv_gt0_count || 0).toLocaleString());

            const parts = [];
            if (meta.required_refreshed_at) parts.push('CP Master · ' + meta.required_refreshed_at);
            if (meta.last_pulled_at) parts.push('Listings · ' + meta.last_pulled_at);
            $('#ebay2-lvv-status-line').text(parts.join(' · '));
            $('#ebay2-lvv-source-label').text(meta.has_listings_cache ? 'CP Master + Listings' : 'CP Master');
        }

        function ebay2LvvUpdateRowCount() {
            if (!ebay2LvvTable) return;
            const shown = ebay2LvvTable.getDataCount('active');
            const total = ebay2LvvTable.getDataCount();
            $('#ebay2-lvv-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            try {
                $('#ebay2-lvv-page-info').text('Page: ' + ebay2LvvTable.getPage() + ' / ' + ebay2LvvTable.getPageMax());
            } catch (e) {
                $('#ebay2-lvv-page-info').text('Page: —');
            }
        }

        function ebay2LvvSyncBadgeActive() {
            const v = $('#ebay2-lvv-listed-filter').val();
            $('#ebay2-lvv-badge-mismatch-btn').toggleClass('is-active', v === 'mismatch');
            $('#ebay2-lvv-badge-mismatch-inv-btn').toggleClass('is-active', v === 'mismatch_inv');
        }

        function ebay2LvvApplyFilters() {
            if (!ebay2LvvTable) return;
            if (window.ParentExpand) {
                ParentExpand.beforeFilters(function () { ebay2LvvApplyFiltersBody(); });
                return;
            }
            ebay2LvvApplyFiltersBody();
        }

        function ebay2LvvApplyFiltersBody() {
            if (!ebay2LvvTable) return;
            ebay2LvvTable.clearFilter();

            const listedFilter = $('#ebay2-lvv-listed-filter').val();
            const q = ($('#ebay2-lvv-search').val() || '').trim().toLowerCase();

            if (listedFilter === 'mismatch') {
                ebay2LvvTable.addFilter(d => d.match_status === false);
            } else if (listedFilter === 'mismatch_inv') {
                ebay2LvvTable.addFilter(d => d.match_status === false && (parseFloat(d.INV) || 0) > 0);
            } else if (listedFilter === 'match') {
                ebay2LvvTable.addFilter(d => d.match_status === true);
            }

            if (q) {
                ebay2LvvTable.addFilter(d => String(d.parent || '').toLowerCase().includes(q));
            }

            ebay2LvvSyncBadgeActive();
            ebay2LvvUpdateRowCount();
        }

        function ebay2LvvFormatRequired(cell) {
            const label = cell.getRow().getData().child_sku_required_label;
            if (label === null || label === undefined || label === '') return ebay2LvvDash(null);
            return `<span class="fw-semibold ebay2-lvv-avail-yes">${ebay2LvvEscapeHtml(label)}</span>`;
        }

        function ebay2LvvSkuChips(skus, type) {
            if (!Array.isArray(skus) || skus.length === 0) return '';
            const chipCls = type === 'extra' ? 'ebay2-lvv-sku-chip--extra' : 'ebay2-lvv-sku-chip--missing';
            return skus.map(s =>
                `<span class="ebay2-lvv-sku-chip ${chipCls}">${ebay2LvvEscapeHtml(s)}</span>`
            ).join('');
        }

        function ebay2LvvFormatAvailable(cell) {
            const d = cell.getRow().getData();
            const label = d.child_sku_available_label || '';
            if (!label || label === '—') return ebay2LvvDash(null);

            const avail = parseInt(d.child_sku_available_count, 10) || 0;
            const total = parseInt(d.child_sku_total, 10) || 0;
            const extraCount = parseInt(d.extra_count, 10) || 0;

            let cls = 'ebay2-lvv-avail-partial';
            if (total > 0 && avail === total && extraCount === 0) cls = 'ebay2-lvv-avail-yes';
            else if (avail === 0) cls = 'ebay2-lvv-avail-no';

            return `<span class="fw-semibold ${cls}">${ebay2LvvEscapeHtml(label)}</span>`;
        }

        function ebay2LvvFormatMissingExcess(cell) {
            const d = cell.getRow().getData();
            const missingSkus = Array.isArray(d.missing_skus) ? d.missing_skus : [];
            const extraSkus = Array.isArray(d.extra_skus) ? d.extra_skus : [];

            if (missingSkus.length === 0 && extraSkus.length === 0) {
                return ebay2LvvDash(null);
            }

            let html = '';
            if (missingSkus.length > 0) {
                html += `<span class="ebay2-lvv-diff ebay2-lvv-diff-missing">`
                    + `<span class="ebay2-lvv-diff-label">Missing:</span>`
                    + ebay2LvvSkuChips(missingSkus, 'missing')
                    + `</span>`;
            }
            if (extraSkus.length > 0) {
                html += `<span class="ebay2-lvv-diff ebay2-lvv-diff-extra">`
                    + `<span class="ebay2-lvv-diff-label">Excess:</span>`
                    + ebay2LvvSkuChips(extraSkus, 'extra')
                    + `</span>`;
            }
            return html;
        }

        function ebay2LvvExportExcel() {
            if (!ebay2LvvTable) {
                alert('Table is not ready yet.');
                return;
            }

            ebay2LvvLoadXlsx().then(function () {
                ebay2LvvExportExcelBody();
            }).catch(function () {
                alert('Export library not loaded. Please refresh and try again.');
            });
        }

        function ebay2LvvExportExcelBody() {
            if (typeof XLSX === 'undefined') {
                alert('Export library not loaded. Please refresh and try again.');
                return;
            }

            const rows = ebay2LvvTable.getData('active') || [];
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
                { wch: 22 }, { wch: 8 }, { wch: 10 }, { wch: 18 }, { wch: 12 }, { wch: 14 },
                { wch: 12 }, { wch: 12 }, { wch: 50 }, { wch: 50 }, { wch: 12 },
            ];

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Ebay2 Variation Verify');

            const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            XLSX.writeFile(wb, 'ebay2_listing_variation_verify_' + stamp + '.xlsx');
        }

        function ebay2LvvFitHeight() {
            const wrap = document.getElementById('ebay2-lvv-wrap');
            if (!wrap) return;
            const top = wrap.getBoundingClientRect().top;
            const gap = 12;
            const h = Math.max(280, Math.floor(window.innerHeight - top - gap));
            wrap.style.height = h + 'px';
            if (ebay2LvvTable) {
                ebay2LvvTable.setHeight('100%');
            }
        }

        $(document).ready(function () {
            ebay2LvvFitHeight();
            $(window).on('resize', function () {
                clearTimeout(window._ebay2LvvResizeTimer);
                window._ebay2LvvResizeTimer = setTimeout(ebay2LvvFitHeight, 100);
            });

            ebay2LvvTable = new Tabulator('#ebay2-listing-variation-verify-table', {
                ajaxURL: ebay2LvvDataUrl,
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    ebay2LvvAllData = rows;
                    if (window.ParentExpand) ParentExpand.captureDataset(rows);
                    if (response && response.meta) ebay2LvvUpdateMeta(response.meta);
                    setTimeout(ebay2LvvFitHeight, 0);
                    return rows;
                },
                height: '100%',
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
                        el.classList.add('ebay2-lvv-parent-row', 'parent-row', 'pm-parent-row');
                        el.classList.remove('ebay2-lvv-child-row');
                    } else {
                        el.classList.remove('ebay2-lvv-parent-row', 'parent-row', 'pm-parent-row');
                        el.classList.add('ebay2-lvv-child-row');
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
                                if (!sku) return ebay2LvvDash(null);
                                return `<span class="fw-semibold text-primary">${ebay2LvvEscapeHtml(sku)}</span>`;
                            }
                            const v = cell.getValue() || '';
                            if (!v) return ebay2LvvDash(null);
                            return `<span class="fw-semibold">${ebay2LvvEscapeHtml(v)}</span>`;
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
                            if (!isFinite(value)) return ebay2LvvDash(null);
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
                        formatter: ebay2LvvFormatRequired
                    },
                    {
                        title: 'Parent Vs Listed SKU',
                        field: 'child_sku_available_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 140,
                        widthGrow: 1,
                        formatter: ebay2LvvFormatAvailable
                    },
                    {
                        title: 'Missing / Excess SKU',
                        field: 'missing_skus',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 320,
                        widthGrow: 4,
                        formatter: ebay2LvvFormatMissingExcess,
                        variableHeight: true
                    }
                ]
            });

            if (window.ParentExpand) {
                ParentExpand.configure({
                    parentField: 'parent',
                    skuField: 'sku',
                    getTable: function () { return ebay2LvvTable; },
                    getDataset: function () { return ebay2LvvAllData; },
                    setDataset: function (rows) { ebay2LvvAllData = rows; },
                    onCollapse: function () { ebay2LvvApplyFilters(); },
                    onAfterExpand: function () { ebay2LvvUpdateRowCount(); },
                });
                ParentExpand.bind();
            }

            ebay2LvvTable.on('dataProcessed', ebay2LvvApplyFilters);
            ebay2LvvTable.on('dataFiltered', ebay2LvvUpdateRowCount);
            ebay2LvvTable.on('pageLoaded', ebay2LvvUpdateRowCount);

            $('#ebay2-lvv-filter-apply').on('click', ebay2LvvApplyFilters);
            $('#ebay2-lvv-listed-filter').on('change', ebay2LvvApplyFilters);
            $('#ebay2-lvv-badge-mismatch-btn').on('click keypress', function (e) {
                if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
                e.preventDefault();
                const cur = $('#ebay2-lvv-listed-filter').val();
                $('#ebay2-lvv-listed-filter').val(cur === 'mismatch' ? 'all' : 'mismatch');
                ebay2LvvApplyFilters();
            });
            $('#ebay2-lvv-badge-mismatch-inv-btn').on('click keypress', function (e) {
                if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
                e.preventDefault();
                const cur = $('#ebay2-lvv-listed-filter').val();
                $('#ebay2-lvv-listed-filter').val(cur === 'mismatch_inv' ? 'all' : 'mismatch_inv');
                ebay2LvvApplyFilters();
            });
            $('#ebay2-lvv-filter-clear').on('click', function () {
                $('#ebay2-lvv-listed-filter').val('all');
                $('#ebay2-lvv-search').val('');
                ebay2LvvApplyFilters();
            });

            let searchTimer = null;
            $('#ebay2-lvv-search').on('keyup search', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(ebay2LvvApplyFilters, 200);
            });

            $('#ebay2-lvv-refresh-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                ebay2LvvTable.setData(ebay2LvvDataUrl + '?refresh=1')
                    .finally(function () { $btn.prop('disabled', false); });
            });

            $('#ebay2-lvv-export-btn').on('click', ebay2LvvExportExcel);

            $('#ebay2-lvv-pull-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;

                if (!confirm('Pull all merchant listings from eBay 2 inventory report?\n\nThis runs app:fetch-ebay-two-metrics and may take a few minutes.')) {
                    return;
                }

                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Pulling…');

                $.ajax({
                    url: '{{ route("ebay2.listing.variation.verify.pull") }}',
                    method: 'POST',
                    timeout: 0,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function (res) {
                        if (res.status === 200) {
                            $('#ebay2-lvv-status-line').text(res.message || 'Pull completed.');
                            ebay2LvvTable.setData(ebay2LvvDataUrl + '?refresh=1');
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
                            .html('<i class="ri-download-cloud-2-line me-1"></i> Pull Listings');
                    }
                });
            });
        });
    </script>
@endsection
