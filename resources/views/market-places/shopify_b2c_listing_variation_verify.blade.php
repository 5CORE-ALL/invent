@extends('layouts.vertical', ['title' => 'Shopify B2C Listing Variation Verify'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #shopify-b2c-lvv-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
        }
        #shopify-b2c-lvv-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #shopify-b2c-lvv-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #shopify-b2c-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #shopify-b2c-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #shopify-b2c-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important; display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #shopify-b2c-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #shopify-b2c-lvv-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #shopify-b2c-lvv-wrap .tabulator .tabulator-row { min-height: 32px; }
        #shopify-b2c-lvv-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #shopify-b2c-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }

        #shopify-b2c-lvv-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
        }
        #shopify-b2c-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #shopify-b2c-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #shopify-b2c-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #shopify-b2c-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #shopify-b2c-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #shopify-b2c-lvv-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        #shopify-b2c-lvv-wrap { overflow-x: auto; overflow-y: visible; }

        #shopify-b2c-lvv-wrap .tabulator-row.shopify-b2c-lvv-parent-row,
        #shopify-b2c-lvv-wrap .tabulator-row.shopify-b2c-lvv-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            color: #664d03;
        }
        #shopify-b2c-lvv-wrap .tabulator-row.shopify-b2c-lvv-parent-row:hover,
        #shopify-b2c-lvv-wrap .tabulator-row.shopify-b2c-lvv-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        #shopify-b2c-lvv-filter-bar {
            background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;
        }
        #shopify-b2c-lvv-filter-bar .shopify-b2c-lvv-filter-label {
            display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px; letter-spacing: 0.01em;
        }
        #shopify-b2c-lvv-filter-bar .shopify-b2c-lvv-filter-select {
            min-width: 120px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;
            color: #64748b; font-size: 0.8125rem; padding-top: 0.35rem; padding-bottom: 0.35rem;
        }

        .shopify-b2c-stat-badge {
            display: inline-flex; align-items: center; flex-shrink: 0; color: #fff; font-size: 15px; font-weight: 700;
            padding: 9px 16px; border-radius: 8px; white-space: nowrap; line-height: 1.25; letter-spacing: 0.2px;
        }
        .shopify-b2c-stat-badge > span { margin-left: 4px; font-size: 16px; font-weight: 800; }
        .shopify-b2c-stat-badge--parents { background: #4c7ed8; }
        .shopify-b2c-stat-badge--children { background: #8b5cf6; }
        .shopify-b2c-stat-badge--listed { background: #16a34a; }
        .shopify-b2c-stat-badge--mismatch { background: #dc2626; }
        .shopify-b2c-stat-badge--mismatch-inv { background: #dc2626; cursor: pointer; }
        .shopify-b2c-stat-badge--mismatch-inv:hover { filter: brightness(0.92); }
        .shopify-b2c-stat-badge--mismatch { cursor: pointer; }
        .shopify-b2c-stat-badge--mismatch:hover { filter: brightness(0.92); }
        .shopify-b2c-stat-badge.is-active { outline: 2px solid #0f172a; outline-offset: 2px; }
        .shopify-b2c-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .shopify-b2c-raw-icon-btn > i { font-size: 14px; }

        .shopify-b2c-lvv-avail-yes { color: #16a34a; font-weight: 700; }
        .shopify-b2c-lvv-avail-no { color: #dc2626; font-weight: 700; }
        .shopify-b2c-lvv-avail-na { color: #94a3b8; }
        .shopify-b2c-lvv-avail-partial { color: #ea580c; font-weight: 700; }

        .shopify-b2c-lvv-diff { display: block; margin-top: 4px; line-height: 1.35; text-align: left; font-weight: 500; }
        .shopify-b2c-lvv-diff-missing { color: #dc2626; }
        .shopify-b2c-lvv-diff-extra { color: #2563eb; }
        .shopify-b2c-lvv-diff-label { font-weight: 700; margin-right: 4px; }
        .shopify-b2c-lvv-sku-chip {
            display: inline-block; margin: 1px 3px 1px 0; padding: 1px 6px; border-radius: 4px;
            font-size: 11.5px; font-weight: 600; line-height: 1.4;
        }
        .shopify-b2c-lvv-sku-chip--missing { background: #fee2e2; color: #b91c1c; }
        .shopify-b2c-lvv-sku-chip--extra { background: #dbeafe; color: #1d4ed8; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shopify B2C Listing Variation Verify',
        'sub_title'  => 'Shopify B2C Listings',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
                            <span class="shopify-b2c-stat-badge shopify-b2c-stat-badge--parents" title="Parents from CP Master">PARENTS:<span id="shopify-b2c-lvv-badge-parents">0</span></span>
                            <span class="shopify-b2c-stat-badge shopify-b2c-stat-badge--children" title="Required child SKUs from CP Master">REQUIRED:<span id="shopify-b2c-lvv-badge-children">0</span></span>
                            <span class="shopify-b2c-stat-badge shopify-b2c-stat-badge--listed" title="Shopify listings cache (shopify_skus)">LISTED:<span id="shopify-b2c-lvv-badge-listed">0</span></span>
                            <span class="shopify-b2c-stat-badge shopify-b2c-stat-badge--mismatch" id="shopify-b2c-lvv-badge-mismatch-btn" role="button" tabindex="0" title="Filter: mismatch only">MISMATCH:<span id="shopify-b2c-lvv-badge-mismatch">0</span></span>
                            <span class="shopify-b2c-stat-badge shopify-b2c-stat-badge--mismatch-inv" id="shopify-b2c-lvv-badge-mismatch-inv-btn" role="button" tabindex="0" title="Filter: mismatch parents with Shopify INV &gt; 0">MISMATCH INV&gt;0:<span id="shopify-b2c-lvv-badge-mismatch-inv">0</span></span>
                        </div>
                        <span id="shopify-b2c-lvv-total" class="badge bg-secondary">Total: —</span>
                        <span id="shopify-b2c-lvv-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="shopify-b2c-lvv-refresh-btn" class="btn btn-sm btn-outline-primary shopify-b2c-raw-icon-btn" title="Refresh" aria-label="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="shopify-b2c-lvv-pull-btn" class="btn btn-sm btn-warning text-dark" title="Refresh Shopify B2C listings cache">
                            <i class="fas fa-sync-alt me-1"></i> Refresh Listings
                        </button>
                        <button type="button" id="shopify-b2c-lvv-export-btn" class="btn btn-sm btn-success" title="Export filtered rows to Excel">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </button>
                        <span class="text-muted small" id="shopify-b2c-lvv-status-line"></span>
                    </div>

                    <div id="shopify-b2c-lvv-filter-bar" class="mb-2">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="shopify-b2c-lvv-filter-label" for="shopify-b2c-lvv-listed-filter">Listed</label>
                                <select id="shopify-b2c-lvv-listed-filter" class="form-select form-select-sm shopify-b2c-lvv-filter-select">
                                    <option value="all">All</option>
                                    <option value="mismatch" selected>Mismatch Only</option>
                                    <option value="mismatch_inv">Mismatch INV&gt;0</option>
                                    <option value="match">Match Only</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="shopify-b2c-lvv-filter-apply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="shopify-b2c-lvv-filter-clear">Clear</button>
                            </div>
                            <div class="ms-auto d-flex flex-wrap align-items-center gap-2 small">
                                <span class="shopify-b2c-lvv-sku-chip shopify-b2c-lvv-sku-chip--missing">Missing</span>
                                <span class="text-muted">in CP Master, not on parent listing</span>
                                <span class="shopify-b2c-lvv-sku-chip shopify-b2c-lvv-sku-chip--extra">Excess</span>
                                <span class="text-muted">on parent listing, not in CP Master</span>
                            </div>
                        </div>
                    </div>

                    <div id="shopify-b2c-lvv-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="shopify-b2c-lvv-search" class="form-control" placeholder="Search Parent..." autocomplete="off" aria-label="Search Parent" maxlength="100">
                            <span id="shopify-b2c-lvv-source-label" class="badge bg-dark text-nowrap">CP Master</span>
                        </div>
                        <div id="shopify-b2c-listing-variation-verify-table"></div>
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
        let shopifyB2cLvvTable = null;
        let shopifyB2cLvvAllData = [];

        function shopifyB2cLvvEscapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function shopifyB2cLvvDash(val) {
            if (val === null || val === undefined || val === '') {
                return '<span class="text-muted">--</span>';
            }
            return val;
        }

        function shopifyB2cLvvUpdateMeta(meta) {
            if (!meta) return;
            $('#shopify-b2c-lvv-badge-parents').text((meta.required_parent_count || 0).toLocaleString());
            $('#shopify-b2c-lvv-badge-children').text((meta.required_child_count || 0).toLocaleString());
            $('#shopify-b2c-lvv-badge-listed').text((meta.listings_count || 0).toLocaleString());
            $('#shopify-b2c-lvv-badge-mismatch').text((meta.mismatch_count || 0).toLocaleString());
            $('#shopify-b2c-lvv-badge-mismatch-inv').text((meta.mismatch_inv_gt0_count || 0).toLocaleString());

            const parts = [];
            if (meta.required_refreshed_at) parts.push('CP Master · ' + meta.required_refreshed_at);
            if (meta.last_pulled_at) parts.push('Listings · ' + meta.last_pulled_at);
            $('#shopify-b2c-lvv-status-line').text(parts.join(' · '));
            $('#shopify-b2c-lvv-source-label').text(meta.has_listings_cache ? 'CP Master + Listings' : 'CP Master');
        }

        function shopifyB2cLvvUpdateRowCount() {
            if (!shopifyB2cLvvTable) return;
            const shown = shopifyB2cLvvTable.getDataCount('active');
            const total = shopifyB2cLvvTable.getDataCount();
            $('#shopify-b2c-lvv-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            try {
                $('#shopify-b2c-lvv-page-info').text('Page: ' + shopifyB2cLvvTable.getPage() + ' / ' + shopifyB2cLvvTable.getPageMax());
            } catch (e) {
                $('#shopify-b2c-lvv-page-info').text('Page: —');
            }
        }

        function shopifyB2cLvvSyncBadgeActive() {
            const v = $('#shopify-b2c-lvv-listed-filter').val();
            $('#shopify-b2c-lvv-badge-mismatch-btn').toggleClass('is-active', v === 'mismatch');
            $('#shopify-b2c-lvv-badge-mismatch-inv-btn').toggleClass('is-active', v === 'mismatch_inv');
        }

        function shopifyB2cLvvApplyFilters() {
            if (!shopifyB2cLvvTable) return;
            if (window.ParentExpand) {
                ParentExpand.beforeFilters(function () { shopifyB2cLvvApplyFiltersBody(); });
                return;
            }
            shopifyB2cLvvApplyFiltersBody();
        }

        function shopifyB2cLvvApplyFiltersBody() {
            if (!shopifyB2cLvvTable) return;
            shopifyB2cLvvTable.clearFilter();

            const listedFilter = $('#shopify-b2c-lvv-listed-filter').val();
            const q = ($('#shopify-b2c-lvv-search').val() || '').trim().toLowerCase();

            if (listedFilter === 'mismatch') {
                shopifyB2cLvvTable.addFilter(d => d.match_status === false);
            } else if (listedFilter === 'mismatch_inv') {
                shopifyB2cLvvTable.addFilter(d => d.match_status === false && (parseFloat(d.INV) || 0) > 0);
            } else if (listedFilter === 'match') {
                shopifyB2cLvvTable.addFilter(d => d.match_status === true);
            }

            if (q) {
                shopifyB2cLvvTable.addFilter(d => String(d.parent || '').toLowerCase().includes(q));
            }

            shopifyB2cLvvSyncBadgeActive();
            shopifyB2cLvvUpdateRowCount();
        }

        function shopifyB2cLvvFormatRequired(cell) {
            const label = cell.getRow().getData().child_sku_required_label;
            if (label === null || label === undefined || label === '') return shopifyB2cLvvDash(null);
            return `<span class="fw-semibold shopify-b2c-lvv-avail-yes">${shopifyB2cLvvEscapeHtml(label)}</span>`;
        }

        function shopifyB2cLvvSkuChips(skus, type) {
            if (!Array.isArray(skus) || skus.length === 0) return '';
            const chipCls = type === 'extra' ? 'shopify-b2c-lvv-sku-chip--extra' : 'shopify-b2c-lvv-sku-chip--missing';
            return skus.map(s =>
                `<span class="shopify-b2c-lvv-sku-chip ${chipCls}">${shopifyB2cLvvEscapeHtml(s)}</span>`
            ).join('');
        }

        function shopifyB2cLvvFormatAvailable(cell) {
            const d = cell.getRow().getData();
            const label = d.child_sku_available_label || '';
            if (!label || label === '—') return shopifyB2cLvvDash(null);

            const avail = parseInt(d.child_sku_available_count, 10) || 0;
            const total = parseInt(d.child_sku_total, 10) || 0;
            const extraCount = parseInt(d.extra_count, 10) || 0;

            let cls = 'shopify-b2c-lvv-avail-partial';
            if (total > 0 && avail === total && extraCount === 0) cls = 'shopify-b2c-lvv-avail-yes';
            else if (avail === 0) cls = 'shopify-b2c-lvv-avail-no';

            return `<span class="fw-semibold ${cls}">${shopifyB2cLvvEscapeHtml(label)}</span>`;
        }

        function shopifyB2cLvvFormatMissingExcess(cell) {
            const d = cell.getRow().getData();
            const missingSkus = Array.isArray(d.missing_skus) ? d.missing_skus : [];
            const extraSkus = Array.isArray(d.extra_skus) ? d.extra_skus : [];

            if (missingSkus.length === 0 && extraSkus.length === 0) {
                return shopifyB2cLvvDash(null);
            }

            let html = '';
            if (missingSkus.length > 0) {
                html += `<span class="shopify-b2c-lvv-diff shopify-b2c-lvv-diff-missing">`
                    + `<span class="shopify-b2c-lvv-diff-label">Missing:</span>`
                    + shopifyB2cLvvSkuChips(missingSkus, 'missing')
                    + `</span>`;
            }
            if (extraSkus.length > 0) {
                html += `<span class="shopify-b2c-lvv-diff shopify-b2c-lvv-diff-extra">`
                    + `<span class="shopify-b2c-lvv-diff-label">Excess:</span>`
                    + shopifyB2cLvvSkuChips(extraSkus, 'extra')
                    + `</span>`;
            }
            return html;
        }

        function shopifyB2cLvvExportExcel() {
            if (!shopifyB2cLvvTable || typeof XLSX === 'undefined') {
                alert('Export library not loaded. Please refresh and try again.');
                return;
            }

            const rows = shopifyB2cLvvTable.getData('active') || [];
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
            XLSX.utils.book_append_sheet(wb, ws, 'Shopify B2C Variation Verify');

            const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            XLSX.writeFile(wb, 'shopify_b2c_listing_variation_verify_' + stamp + '.xlsx');
        }

        $(document).ready(function () {
            shopifyB2cLvvTable = new Tabulator('#shopify-b2c-listing-variation-verify-table', {
                ajaxURL: '{{ route("shopify.b2c.listing.variation.verify.data") }}',
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    shopifyB2cLvvAllData = rows;
                    if (window.ParentExpand) ParentExpand.captureDataset(rows);
                    if (response && response.meta) shopifyB2cLvvUpdateMeta(response.meta);
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
                        el.classList.add('shopify-b2c-lvv-parent-row', 'parent-row', 'pm-parent-row');
                        el.classList.remove('shopify-b2c-lvv-child-row');
                    } else {
                        el.classList.remove('shopify-b2c-lvv-parent-row', 'parent-row', 'pm-parent-row');
                        el.classList.add('shopify-b2c-lvv-child-row');
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
                                if (!sku) return shopifyB2cLvvDash(null);
                                return `<span class="fw-semibold text-primary">${shopifyB2cLvvEscapeHtml(sku)}</span>`;
                            }
                            const v = cell.getValue() || '';
                            if (!v) return shopifyB2cLvvDash(null);
                            return `<span class="fw-semibold">${shopifyB2cLvvEscapeHtml(v)}</span>`;
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
                            if (!isFinite(value)) return shopifyB2cLvvDash(null);
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
                        formatter: shopifyB2cLvvFormatRequired
                    },
                    {
                        title: 'Parent Vs Listed SKU',
                        field: 'child_sku_available_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 140,
                        widthGrow: 1,
                        formatter: shopifyB2cLvvFormatAvailable
                    },
                    {
                        title: 'Missing / Excess SKU',
                        field: 'missing_skus',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 320,
                        widthGrow: 4,
                        formatter: shopifyB2cLvvFormatMissingExcess,
                        variableHeight: true
                    }
                ]
            });

            if (window.ParentExpand) {
                ParentExpand.configure({
                    parentField: 'parent',
                    skuField: 'sku',
                    getTable: function () { return shopifyB2cLvvTable; },
                    getDataset: function () { return shopifyB2cLvvAllData; },
                    setDataset: function (rows) { shopifyB2cLvvAllData = rows; },
                    onCollapse: function () { shopifyB2cLvvApplyFilters(); },
                    onAfterExpand: function () { shopifyB2cLvvUpdateRowCount(); },
                });
                ParentExpand.bind();
            }

            shopifyB2cLvvTable.on('dataProcessed', shopifyB2cLvvApplyFilters);
            shopifyB2cLvvTable.on('dataFiltered', shopifyB2cLvvUpdateRowCount);
            shopifyB2cLvvTable.on('pageLoaded', shopifyB2cLvvUpdateRowCount);

            $('#shopify-b2c-lvv-filter-apply').on('click', shopifyB2cLvvApplyFilters);
            $('#shopify-b2c-lvv-listed-filter').on('change', shopifyB2cLvvApplyFilters);
            $('#shopify-b2c-lvv-badge-mismatch-btn').on('click keypress', function (e) {
                if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
                e.preventDefault();
                const cur = $('#shopify-b2c-lvv-listed-filter').val();
                $('#shopify-b2c-lvv-listed-filter').val(cur === 'mismatch' ? 'all' : 'mismatch');
                shopifyB2cLvvApplyFilters();
            });
            $('#shopify-b2c-lvv-badge-mismatch-inv-btn').on('click keypress', function (e) {
                if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
                e.preventDefault();
                const cur = $('#shopify-b2c-lvv-listed-filter').val();
                $('#shopify-b2c-lvv-listed-filter').val(cur === 'mismatch_inv' ? 'all' : 'mismatch_inv');
                shopifyB2cLvvApplyFilters();
            });
            $('#shopify-b2c-lvv-filter-clear').on('click', function () {
                $('#shopify-b2c-lvv-listed-filter').val('all');
                $('#shopify-b2c-lvv-search').val('');
                shopifyB2cLvvApplyFilters();
            });

            let searchTimer = null;
            $('#shopify-b2c-lvv-search').on('keyup search', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(shopifyB2cLvvApplyFilters, 200);
            });

            $('#shopify-b2c-lvv-refresh-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                shopifyB2cLvvTable.setData('{{ route("shopify.b2c.listing.variation.verify.data") }}')
                    .finally(function () { $btn.prop('disabled', false); });
            });

            $('#shopify-b2c-lvv-export-btn').on('click', shopifyB2cLvvExportExcel);

            $('#shopify-b2c-lvv-pull-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;

                if (!confirm("Refresh Shopify B2C listings from shopify_skus cache?\n\nSync Shopify data used by Shopify B2C - Analytics (/shopify-b2c-pricing) if the cache is empty.")) {
                    return;
                }

                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Refreshing…');

                $.ajax({
                    url: '{{ route("shopify.b2c.listing.variation.verify.pull") }}',
                    method: 'POST',
                    timeout: 0,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function (res) {
                        if (res.status === 200) {
                            $('#shopify-b2c-lvv-status-line').text(res.message || 'Pull completed.');
                            shopifyB2cLvvTable.setData('{{ route("shopify.b2c.listing.variation.verify.data") }}');
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
