@extends('layouts.vertical', ['title' => 'Faire Listing Variation Verify'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #faire-lvv-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
        }
        #faire-lvv-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #faire-lvv-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #faire-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #faire-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #faire-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important; display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #faire-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #faire-lvv-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #faire-lvv-wrap .tabulator .tabulator-row { min-height: 32px; }
        #faire-lvv-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #faire-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }

        #faire-lvv-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
        }
        #faire-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #faire-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #faire-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #faire-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #faire-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #faire-lvv-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        #faire-lvv-wrap { overflow-x: auto; overflow-y: visible; }

        #faire-lvv-wrap .tabulator-row.faire-lvv-parent-row,
        #faire-lvv-wrap .tabulator-row.faire-lvv-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            color: #664d03;
        }
        #faire-lvv-wrap .tabulator-row.faire-lvv-parent-row:hover,
        #faire-lvv-wrap .tabulator-row.faire-lvv-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        #faire-lvv-filter-bar {
            background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;
        }
        #faire-lvv-filter-bar .faire-lvv-filter-label {
            display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px; letter-spacing: 0.01em;
        }
        #faire-lvv-filter-bar .faire-lvv-filter-select {
            min-width: 120px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;
            color: #64748b; font-size: 0.8125rem; padding-top: 0.35rem; padding-bottom: 0.35rem;
        }

        .faire-stat-badge {
            display: inline-flex; align-items: center; flex-shrink: 0; color: #fff; font-size: 15px; font-weight: 700;
            padding: 9px 16px; border-radius: 8px; white-space: nowrap; line-height: 1.25; letter-spacing: 0.2px;
        }
        .faire-stat-badge > span { margin-left: 4px; font-size: 16px; font-weight: 800; }
        .faire-stat-badge--parents { background: #4c7ed8; }
        .faire-stat-badge--children { background: #8b5cf6; }
        .faire-stat-badge--listed { background: #16a34a; }
        .faire-stat-badge--mismatch { background: #dc2626; }
        .faire-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .faire-raw-icon-btn > i { font-size: 14px; }

        .faire-lvv-avail-yes { color: #16a34a; font-weight: 700; }
        .faire-lvv-avail-no { color: #dc2626; font-weight: 700; }
        .faire-lvv-avail-na { color: #94a3b8; }
        .faire-lvv-avail-partial { color: #ea580c; font-weight: 700; }

        .faire-lvv-diff { display: block; margin-top: 4px; line-height: 1.35; text-align: left; font-weight: 500; }
        .faire-lvv-diff-missing { color: #dc2626; }
        .faire-lvv-diff-extra { color: #2563eb; }
        .faire-lvv-diff-label { font-weight: 700; margin-right: 4px; }
        .faire-lvv-sku-chip {
            display: inline-block; margin: 1px 3px 1px 0; padding: 1px 6px; border-radius: 4px;
            font-size: 11.5px; font-weight: 600; line-height: 1.4;
        }
        .faire-lvv-sku-chip--missing { background: #fee2e2; color: #b91c1c; }
        .faire-lvv-sku-chip--extra { background: #dbeafe; color: #1d4ed8; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Faire Listing Variation Verify',
        'sub_title'  => 'Faire Listings',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
                            <span class="faire-stat-badge faire-stat-badge--parents" title="Parents from CP Master">PARENTS:<span id="faire-lvv-badge-parents">0</span></span>
                            <span class="faire-stat-badge faire-stat-badge--children" title="Required child SKUs from CP Master">REQUIRED:<span id="faire-lvv-badge-children">0</span></span>
                            <span class="faire-stat-badge faire-stat-badge--listed" title="Faire listings from products API (faire_metric)">LISTED:<span id="faire-lvv-badge-listed">0</span></span>
                            <span class="faire-stat-badge faire-stat-badge--mismatch" title="Parents with missing or excess SKUs">MISMATCH:<span id="faire-lvv-badge-mismatch">0</span></span>
                        </div>
                        <span id="faire-lvv-total" class="badge bg-secondary">Total: —</span>
                        <span id="faire-lvv-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="faire-lvv-refresh-btn" class="btn btn-sm btn-outline-primary faire-raw-icon-btn" title="Refresh" aria-label="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="faire-lvv-pull-btn" class="btn btn-sm btn-warning text-dark" title="Refresh Faire listings cache">
                            <i class="fas fa-sync-alt me-1"></i> Refresh Listings
                        </button>
                        <button type="button" id="faire-lvv-export-btn" class="btn btn-sm btn-success" title="Export filtered rows to Excel">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </button>
                        <span class="text-muted small" id="faire-lvv-status-line"></span>
                    </div>

                    <div id="faire-lvv-filter-bar" class="mb-2">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="faire-lvv-filter-label" for="faire-lvv-listed-filter">Listed</label>
                                <select id="faire-lvv-listed-filter" class="form-select form-select-sm faire-lvv-filter-select">
                                    <option value="all">All</option>
                                    <option value="mismatch" selected>Mismatch Only</option>
                                    <option value="match">Match Only</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="faire-lvv-filter-apply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="faire-lvv-filter-clear">Clear</button>
                            </div>
                            <div class="ms-auto d-flex flex-wrap align-items-center gap-2 small">
                                <span class="faire-lvv-sku-chip faire-lvv-sku-chip--missing">Missing</span>
                                <span class="text-muted">in CP Master, not on parent listing</span>
                                <span class="faire-lvv-sku-chip faire-lvv-sku-chip--extra">Excess</span>
                                <span class="text-muted">on parent listing, not in CP Master</span>
                            </div>
                        </div>
                    </div>

                    <div id="faire-lvv-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="faire-lvv-search" class="form-control" placeholder="Search Parent..." autocomplete="off" aria-label="Search Parent" maxlength="100">
                            <span id="faire-lvv-source-label" class="badge bg-dark text-nowrap">CP Master</span>
                        </div>
                        <div id="faire-listing-variation-verify-table"></div>
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
        let faireLvvTable = null;

        function faireLvvEscapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function faireLvvDash(val) {
            if (val === null || val === undefined || val === '') {
                return '<span class="text-muted">--</span>';
            }
            return val;
        }

        function faireLvvUpdateMeta(meta) {
            if (!meta) return;
            $('#faire-lvv-badge-parents').text((meta.required_parent_count || 0).toLocaleString());
            $('#faire-lvv-badge-children').text((meta.required_child_count || 0).toLocaleString());
            $('#faire-lvv-badge-listed').text((meta.listings_count || 0).toLocaleString());
            $('#faire-lvv-badge-mismatch').text((meta.mismatch_count || 0).toLocaleString());

            const parts = [];
            if (meta.required_refreshed_at) parts.push('CP Master · ' + meta.required_refreshed_at);
            if (meta.last_pulled_at) parts.push('Listings · ' + meta.last_pulled_at);
            $('#faire-lvv-status-line').text(parts.join(' · '));
            $('#faire-lvv-source-label').text(meta.has_listings_cache ? 'CP Master + Listings' : 'CP Master');
        }

        function faireLvvUpdateRowCount() {
            if (!faireLvvTable) return;
            const shown = faireLvvTable.getDataCount('active');
            const total = faireLvvTable.getDataCount();
            $('#faire-lvv-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            try {
                $('#faire-lvv-page-info').text('Page: ' + faireLvvTable.getPage() + ' / ' + faireLvvTable.getPageMax());
            } catch (e) {
                $('#faire-lvv-page-info').text('Page: —');
            }
        }

        function faireLvvApplyFilters() {
            if (!faireLvvTable) return;
            faireLvvTable.clearFilter();

            const listedFilter = $('#faire-lvv-listed-filter').val();
            const q = ($('#faire-lvv-search').val() || '').trim().toLowerCase();

            if (listedFilter === 'mismatch') {
                faireLvvTable.addFilter(d => d.match_status === false);
            } else if (listedFilter === 'match') {
                faireLvvTable.addFilter(d => d.match_status === true);
            }

            if (q) {
                faireLvvTable.addFilter(d => String(d.parent || '').toLowerCase().includes(q));
            }

            faireLvvUpdateRowCount();
        }

        function faireLvvFormatRequired(cell) {
            const label = cell.getRow().getData().child_sku_required_label;
            if (label === null || label === undefined || label === '') return faireLvvDash(null);
            return `<span class="fw-semibold faire-lvv-avail-yes">${faireLvvEscapeHtml(label)}</span>`;
        }

        function faireLvvSkuChips(skus, type) {
            if (!Array.isArray(skus) || skus.length === 0) return '';
            const chipCls = type === 'extra' ? 'faire-lvv-sku-chip--extra' : 'faire-lvv-sku-chip--missing';
            return skus.map(s =>
                `<span class="faire-lvv-sku-chip ${chipCls}">${faireLvvEscapeHtml(s)}</span>`
            ).join('');
        }

        function faireLvvFormatAvailable(cell) {
            const d = cell.getRow().getData();
            const label = d.child_sku_available_label || '';
            if (!label || label === '—') return faireLvvDash(null);

            const avail = parseInt(d.child_sku_available_count, 10) || 0;
            const total = parseInt(d.child_sku_total, 10) || 0;
            const extraCount = parseInt(d.extra_count, 10) || 0;

            let cls = 'faire-lvv-avail-partial';
            if (total > 0 && avail === total && extraCount === 0) cls = 'faire-lvv-avail-yes';
            else if (avail === 0) cls = 'faire-lvv-avail-no';

            return `<span class="fw-semibold ${cls}">${faireLvvEscapeHtml(label)}</span>`;
        }

        function faireLvvFormatMissingExcess(cell) {
            const d = cell.getRow().getData();
            const missingSkus = Array.isArray(d.missing_skus) ? d.missing_skus : [];
            const extraSkus = Array.isArray(d.extra_skus) ? d.extra_skus : [];

            if (missingSkus.length === 0 && extraSkus.length === 0) {
                return faireLvvDash(null);
            }

            let html = '';
            if (missingSkus.length > 0) {
                html += `<span class="faire-lvv-diff faire-lvv-diff-missing">`
                    + `<span class="faire-lvv-diff-label">Missing:</span>`
                    + faireLvvSkuChips(missingSkus, 'missing')
                    + `</span>`;
            }
            if (extraSkus.length > 0) {
                html += `<span class="faire-lvv-diff faire-lvv-diff-extra">`
                    + `<span class="faire-lvv-diff-label">Excess:</span>`
                    + faireLvvSkuChips(extraSkus, 'extra')
                    + `</span>`;
            }
            return html;
        }

        function faireLvvExportExcel() {
            if (!faireLvvTable || typeof XLSX === 'undefined') {
                alert('Export library not loaded. Please refresh and try again.');
                return;
            }

            const rows = faireLvvTable.getData('active') || [];
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
            XLSX.utils.book_append_sheet(wb, ws, 'Faire Variation Verify');

            const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            XLSX.writeFile(wb, 'faire_listing_variation_verify_' + stamp + '.xlsx');
        }

        $(document).ready(function () {
            faireLvvTable = new Tabulator('#faire-listing-variation-verify-table', {
                ajaxURL: '{{ route("faire.listing.variation.verify.data") }}',
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    if (response && response.meta) faireLvvUpdateMeta(response.meta);
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
                    row.getElement().classList.add('faire-lvv-parent-row');
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
                            if (!v) return faireLvvDash(null);
                            return `<span class="fw-semibold">${faireLvvEscapeHtml(v)}</span>`;
                        }
                    },
                    {
                        title: 'Required',
                        field: 'child_sku_required_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 100,
                        widthGrow: 1,
                        formatter: faireLvvFormatRequired
                    },
                    {
                        title: 'Parent Vs Listed SKU',
                        field: 'child_sku_available_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 140,
                        widthGrow: 1,
                        formatter: faireLvvFormatAvailable
                    },
                    {
                        title: 'Missing / Excess SKU',
                        field: 'missing_skus',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 320,
                        widthGrow: 4,
                        formatter: faireLvvFormatMissingExcess,
                        variableHeight: true
                    }
                ]
            });

            faireLvvTable.on('dataProcessed', faireLvvApplyFilters);
            faireLvvTable.on('dataFiltered', faireLvvUpdateRowCount);
            faireLvvTable.on('pageLoaded', faireLvvUpdateRowCount);

            $('#faire-lvv-filter-apply').on('click', faireLvvApplyFilters);
            $('#faire-lvv-listed-filter').on('change', faireLvvApplyFilters);
            $('#faire-lvv-filter-clear').on('click', function () {
                $('#faire-lvv-listed-filter').val('all');
                $('#faire-lvv-search').val('');
                faireLvvApplyFilters();
            });

            let searchTimer = null;
            $('#faire-lvv-search').on('keyup search', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(faireLvvApplyFilters, 200);
            });

            $('#faire-lvv-refresh-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                faireLvvTable.setData('{{ route("faire.listing.variation.verify.data") }}')
                    .finally(function () { $btn.prop('disabled', false); });
            });

            $('#faire-lvv-export-btn').on('click', faireLvvExportExcel);

            $('#faire-lvv-pull-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;

                if (!confirm("Pull live Faire listings from the Faire products API into faire_metric?\n\nThis updates Parent Vs Listed SKU (API only).")) {
                    return;
                }

                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Refreshing…');

                $.ajax({
                    url: '{{ route("faire.listing.variation.verify.pull") }}',
                    method: 'POST',
                    timeout: 0,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function (res) {
                        if (res.status === 200) {
                            $('#faire-lvv-status-line').text(res.message || 'Pull completed.');
                            faireLvvTable.setData('{{ route("faire.listing.variation.verify.data") }}');
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
