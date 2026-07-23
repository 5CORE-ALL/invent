@extends('layouts.vertical', ['title' => 'Purchasing Power Listing Variation Verify'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #pp-lvv-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
        }
        #pp-lvv-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #pp-lvv-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #pp-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #pp-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #pp-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important; display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #pp-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #pp-lvv-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #pp-lvv-wrap .tabulator .tabulator-row { min-height: 32px; }
        #pp-lvv-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #pp-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }

        #pp-lvv-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
        }
        #pp-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #pp-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #pp-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #pp-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #pp-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #pp-lvv-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        #pp-lvv-wrap { overflow-x: auto; overflow-y: visible; }

        #pp-lvv-wrap .tabulator-row.pp-lvv-parent-row,
        #pp-lvv-wrap .tabulator-row.pp-lvv-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            color: #664d03;
        }
        #pp-lvv-wrap .tabulator-row.pp-lvv-parent-row:hover,
        #pp-lvv-wrap .tabulator-row.pp-lvv-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        #pp-lvv-filter-bar {
            background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;
        }
        #pp-lvv-filter-bar .pp-lvv-filter-label {
            display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px; letter-spacing: 0.01em;
        }
        #pp-lvv-filter-bar .pp-lvv-filter-select {
            min-width: 120px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;
            color: #64748b; font-size: 0.8125rem; padding-top: 0.35rem; padding-bottom: 0.35rem;
        }

        .pp-stat-badge {
            display: inline-flex; align-items: center; flex-shrink: 0; color: #fff; font-size: 15px; font-weight: 700;
            padding: 9px 16px; border-radius: 8px; white-space: nowrap; line-height: 1.25; letter-spacing: 0.2px;
        }
        .pp-stat-badge > span { margin-left: 4px; font-size: 16px; font-weight: 800; }
        .pp-stat-badge--parents { background: #4c7ed8; }
        .pp-stat-badge--children { background: #8b5cf6; }
        .pp-stat-badge--listed { background: #16a34a; }
        .pp-stat-badge--mismatch { background: #dc2626; }
        .pp-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .pp-raw-icon-btn > i { font-size: 14px; }

        .pp-lvv-avail-yes { color: #16a34a; font-weight: 700; }
        .pp-lvv-avail-no { color: #dc2626; font-weight: 700; }
        .pp-lvv-avail-na { color: #94a3b8; }
        .pp-lvv-avail-partial { color: #ea580c; font-weight: 700; }

        .pp-lvv-diff { display: block; margin-top: 4px; line-height: 1.35; text-align: left; font-weight: 500; }
        .pp-lvv-diff-missing { color: #dc2626; }
        .pp-lvv-diff-extra { color: #2563eb; }
        .pp-lvv-diff-label { font-weight: 700; margin-right: 4px; }
        .pp-lvv-sku-chip {
            display: inline-block; margin: 1px 3px 1px 0; padding: 1px 6px; border-radius: 4px;
            font-size: 11.5px; font-weight: 600; line-height: 1.4;
        }
        .pp-lvv-sku-chip--missing { background: #fee2e2; color: #b91c1c; }
        .pp-lvv-sku-chip--extra { background: #dbeafe; color: #1d4ed8; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Purchasing Power Listing Variation Verify',
        'sub_title'  => 'Purchasing Power Listings',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
                            <span class="pp-stat-badge pp-stat-badge--parents" title="Parents from CP Master">PARENTS:<span id="pp-lvv-badge-parents">0</span></span>
                            <span class="pp-stat-badge pp-stat-badge--children" title="Required child SKUs from CP Master">REQUIRED:<span id="pp-lvv-badge-children">0</span></span>
                            <span class="pp-stat-badge pp-stat-badge--listed" title="Purchasing Power listings (purchasing_power_products)">LISTED:<span id="pp-lvv-badge-listed">0</span></span>
                            <span class="pp-stat-badge pp-stat-badge--mismatch" title="Parents with missing or excess SKUs">MISMATCH:<span id="pp-lvv-badge-mismatch">0</span></span>
                        </div>
                        <span id="pp-lvv-total" class="badge bg-secondary">Total: —</span>
                        <span id="pp-lvv-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="pp-lvv-refresh-btn" class="btn btn-sm btn-outline-primary pp-raw-icon-btn" title="Refresh" aria-label="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="pp-lvv-pull-btn" class="btn btn-sm btn-warning text-dark" title="Refresh Purchasing Power listings cache">
                            <i class="fas fa-sync-alt me-1"></i> Refresh Listings
                        </button>
                        <button type="button" id="pp-lvv-export-btn" class="btn btn-sm btn-success" title="Export filtered rows to Excel">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </button>
                        <span class="text-muted small" id="pp-lvv-status-line"></span>
                    </div>

                    <div id="pp-lvv-filter-bar" class="mb-2">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="pp-lvv-filter-label" for="pp-lvv-listed-filter">Listed</label>
                                <select id="pp-lvv-listed-filter" class="form-select form-select-sm pp-lvv-filter-select">
                                    <option value="all">All</option>
                                    <option value="mismatch" selected>Mismatch Only</option>
                                    <option value="match">Match Only</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="pp-lvv-filter-apply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="pp-lvv-filter-clear">Clear</button>
                            </div>
                            <div class="ms-auto d-flex flex-wrap align-items-center gap-2 small">
                                <span class="pp-lvv-sku-chip pp-lvv-sku-chip--missing">Missing</span>
                                <span class="text-muted">in CP Master, not on parent listing</span>
                                <span class="pp-lvv-sku-chip pp-lvv-sku-chip--extra">Excess</span>
                                <span class="text-muted">on parent listing, not in CP Master</span>
                            </div>
                        </div>
                    </div>

                    <div id="pp-lvv-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="pp-lvv-search" class="form-control" placeholder="Search Parent..." autocomplete="off" aria-label="Search Parent" maxlength="100">
                            <span id="pp-lvv-source-label" class="badge bg-dark text-nowrap">CP Master</span>
                        </div>
                        <div id="purchasing-power-listing-variation-verify-table"></div>
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
        let ppLvvTable = null;

        function ppLvvEscapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function ppLvvDash(val) {
            if (val === null || val === undefined || val === '') {
                return '<span class="text-muted">--</span>';
            }
            return val;
        }

        function ppLvvUpdateMeta(meta) {
            if (!meta) return;
            $('#pp-lvv-badge-parents').text((meta.required_parent_count || 0).toLocaleString());
            $('#pp-lvv-badge-children').text((meta.required_child_count || 0).toLocaleString());
            $('#pp-lvv-badge-listed').text((meta.listings_count || 0).toLocaleString());
            $('#pp-lvv-badge-mismatch').text((meta.mismatch_count || 0).toLocaleString());

            const parts = [];
            if (meta.required_refreshed_at) parts.push('CP Master · ' + meta.required_refreshed_at);
            if (meta.last_pulled_at) parts.push('Listings · ' + meta.last_pulled_at);
            $('#pp-lvv-status-line').text(parts.join(' · '));
            $('#pp-lvv-source-label').text(meta.has_listings_cache ? 'CP Master + Listings' : 'CP Master');
        }

        function ppLvvUpdateRowCount() {
            if (!ppLvvTable) return;
            const shown = ppLvvTable.getDataCount('active');
            const total = ppLvvTable.getDataCount();
            $('#pp-lvv-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            try {
                $('#pp-lvv-page-info').text('Page: ' + ppLvvTable.getPage() + ' / ' + ppLvvTable.getPageMax());
            } catch (e) {
                $('#pp-lvv-page-info').text('Page: —');
            }
        }

        function ppLvvApplyFilters() {
            if (!ppLvvTable) return;
            ppLvvTable.clearFilter();

            const listedFilter = $('#pp-lvv-listed-filter').val();
            const q = ($('#pp-lvv-search').val() || '').trim().toLowerCase();

            if (listedFilter === 'mismatch') {
                ppLvvTable.addFilter(d => d.match_status === false);
            } else if (listedFilter === 'match') {
                ppLvvTable.addFilter(d => d.match_status === true);
            }

            if (q) {
                ppLvvTable.addFilter(d => String(d.parent || '').toLowerCase().includes(q));
            }

            ppLvvUpdateRowCount();
        }

        function ppLvvFormatRequired(cell) {
            const label = cell.getRow().getData().child_sku_required_label;
            if (label === null || label === undefined || label === '') return ppLvvDash(null);
            return `<span class="fw-semibold pp-lvv-avail-yes">${ppLvvEscapeHtml(label)}</span>`;
        }

        function ppLvvSkuChips(skus, type) {
            if (!Array.isArray(skus) || skus.length === 0) return '';
            const chipCls = type === 'extra' ? 'pp-lvv-sku-chip--extra' : 'pp-lvv-sku-chip--missing';
            return skus.map(s =>
                `<span class="pp-lvv-sku-chip ${chipCls}">${ppLvvEscapeHtml(s)}</span>`
            ).join('');
        }

        function ppLvvFormatAvailable(cell) {
            const d = cell.getRow().getData();
            const label = d.child_sku_available_label || '';
            if (!label || label === '—') return ppLvvDash(null);

            const avail = parseInt(d.child_sku_available_count, 10) || 0;
            const total = parseInt(d.child_sku_total, 10) || 0;
            const extraCount = parseInt(d.extra_count, 10) || 0;

            let cls = 'pp-lvv-avail-partial';
            if (total > 0 && avail === total && extraCount === 0) cls = 'pp-lvv-avail-yes';
            else if (avail === 0) cls = 'pp-lvv-avail-no';

            return `<span class="fw-semibold ${cls}">${ppLvvEscapeHtml(label)}</span>`;
        }

        function ppLvvFormatMissingExcess(cell) {
            const d = cell.getRow().getData();
            const missingSkus = Array.isArray(d.missing_skus) ? d.missing_skus : [];
            const extraSkus = Array.isArray(d.extra_skus) ? d.extra_skus : [];

            if (missingSkus.length === 0 && extraSkus.length === 0) {
                return ppLvvDash(null);
            }

            let html = '';
            if (missingSkus.length > 0) {
                html += `<span class="pp-lvv-diff pp-lvv-diff-missing">`
                    + `<span class="pp-lvv-diff-label">Missing:</span>`
                    + ppLvvSkuChips(missingSkus, 'missing')
                    + `</span>`;
            }
            if (extraSkus.length > 0) {
                html += `<span class="pp-lvv-diff pp-lvv-diff-extra">`
                    + `<span class="pp-lvv-diff-label">Excess:</span>`
                    + ppLvvSkuChips(extraSkus, 'extra')
                    + `</span>`;
            }
            return html;
        }

        function ppLvvExportExcel() {
            if (!ppLvvTable || typeof XLSX === 'undefined') {
                alert('Export library not loaded. Please refresh and try again.');
                return;
            }

            const rows = ppLvvTable.getData('active') || [];
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
            XLSX.utils.book_append_sheet(wb, ws, 'Purchasing Power Variation Verify');

            const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            XLSX.writeFile(wb, 'purchasing_power_listing_variation_verify_' + stamp + '.xlsx');
        }

        $(document).ready(function () {
            ppLvvTable = new Tabulator('#purchasing-power-listing-variation-verify-table', {
                ajaxURL: '{{ route("purchasing.power.listing.variation.verify.data") }}',
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    if (response && response.meta) ppLvvUpdateMeta(response.meta);
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
                    row.getElement().classList.add('pp-lvv-parent-row');
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
                            const v = cell.getValue() || '';
                            if (!v) return ppLvvDash(null);
                            return `<span class="fw-semibold">${ppLvvEscapeHtml(v)}</span>`;
                        }
                    },
                    {
                        title: 'Required',
                        field: 'child_sku_required_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 100,
                        widthGrow: 1,
                        formatter: ppLvvFormatRequired
                    },
                    {
                        title: 'Parent Vs Listed SKU',
                        field: 'child_sku_available_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 140,
                        widthGrow: 1,
                        formatter: ppLvvFormatAvailable
                    },
                    {
                        title: 'Missing / Excess SKU',
                        field: 'missing_skus',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 320,
                        widthGrow: 4,
                        formatter: ppLvvFormatMissingExcess,
                        variableHeight: true
                    }
                ]
            });

            ppLvvTable.on('dataProcessed', ppLvvApplyFilters);
            ppLvvTable.on('dataFiltered', ppLvvUpdateRowCount);
            ppLvvTable.on('pageLoaded', ppLvvUpdateRowCount);

            $('#pp-lvv-filter-apply').on('click', ppLvvApplyFilters);
            $('#pp-lvv-listed-filter').on('change', ppLvvApplyFilters);
            $('#pp-lvv-filter-clear').on('click', function () {
                $('#pp-lvv-listed-filter').val('all');
                $('#pp-lvv-search').val('');
                ppLvvApplyFilters();
            });

            let searchTimer = null;
            $('#pp-lvv-search').on('keyup search', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(ppLvvApplyFilters, 200);
            });

            $('#pp-lvv-refresh-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                ppLvvTable.setData('{{ route("purchasing.power.listing.variation.verify.data") }}')
                    .finally(function () { $btn.prop('disabled', false); });
            });

            $('#pp-lvv-export-btn').on('click', ppLvvExportExcel);

            $('#pp-lvv-pull-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;

                if (!confirm("Refresh Purchasing Power listings from purchasing_power_products / offers cache?\n\nUpdate data on Purchasing Power Pricing (/purchasing-power-pricing) if the cache is empty.")) {
                    return;
                }

                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Refreshing…');

                $.ajax({
                    url: '{{ route("purchasing.power.listing.variation.verify.pull") }}',
                    method: 'POST',
                    timeout: 0,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function (res) {
                        if (res.status === 200) {
                            $('#pp-lvv-status-line').text(res.message || 'Pull completed.');
                            ppLvvTable.setData('{{ route("purchasing.power.listing.variation.verify.data") }}');
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
