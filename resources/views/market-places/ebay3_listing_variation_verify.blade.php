@extends('layouts.vertical', ['title' => 'Ebay 3 Listing Variation Verify'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #ebay3-lvv-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
        }
        #ebay3-lvv-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #ebay3-lvv-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #ebay3-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #ebay3-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #ebay3-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important; display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #ebay3-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #ebay3-lvv-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #ebay3-lvv-wrap .tabulator .tabulator-row { min-height: 32px; }
        #ebay3-lvv-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #ebay3-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }

        #ebay3-lvv-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
        }
        #ebay3-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #ebay3-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #ebay3-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #ebay3-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #ebay3-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #ebay3-lvv-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        #ebay3-lvv-wrap { overflow-x: auto; overflow-y: visible; }

        #ebay3-lvv-wrap .tabulator-row.ebay3-lvv-parent-row,
        #ebay3-lvv-wrap .tabulator-row.ebay3-lvv-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            color: #664d03;
        }
        #ebay3-lvv-wrap .tabulator-row.ebay3-lvv-parent-row:hover,
        #ebay3-lvv-wrap .tabulator-row.ebay3-lvv-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        #ebay3-lvv-filter-bar {
            background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;
        }
        #ebay3-lvv-filter-bar .ebay3-lvv-filter-label {
            display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px; letter-spacing: 0.01em;
        }
        #ebay3-lvv-filter-bar .ebay3-lvv-filter-select {
            min-width: 120px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;
            color: #64748b; font-size: 0.8125rem; padding-top: 0.35rem; padding-bottom: 0.35rem;
        }

        .ebay3-stat-badge {
            display: inline-flex; align-items: center; flex-shrink: 0; color: #fff; font-size: 15px; font-weight: 700;
            padding: 9px 16px; border-radius: 8px; white-space: nowrap; line-height: 1.25; letter-spacing: 0.2px;
        }
        .ebay3-stat-badge > span { margin-left: 4px; font-size: 16px; font-weight: 800; }
        .ebay3-stat-badge--parents { background: #4c7ed8; }
        .ebay3-stat-badge--children { background: #8b5cf6; }
        .ebay3-stat-badge--listed { background: #16a34a; }
        .ebay3-stat-badge--mismatch { background: #dc2626; }
        .ebay3-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .ebay3-raw-icon-btn > i { font-size: 14px; }

        .ebay3-lvv-avail-yes { color: #16a34a; font-weight: 700; }
        .ebay3-lvv-avail-no { color: #dc2626; font-weight: 700; }
        .ebay3-lvv-avail-na { color: #94a3b8; }
        .ebay3-lvv-avail-partial { color: #ea580c; font-weight: 700; }

        .ebay3-lvv-diff { display: block; margin-top: 4px; line-height: 1.35; text-align: left; font-weight: 500; }
        .ebay3-lvv-diff-missing { color: #dc2626; }
        .ebay3-lvv-diff-extra { color: #2563eb; }
        .ebay3-lvv-diff-label { font-weight: 700; margin-right: 4px; }
        .ebay3-lvv-sku-chip {
            display: inline-block; margin: 1px 3px 1px 0; padding: 1px 6px; border-radius: 4px;
            font-size: 11.5px; font-weight: 600; line-height: 1.4;
        }
        .ebay3-lvv-sku-chip--missing { background: #fee2e2; color: #b91c1c; }
        .ebay3-lvv-sku-chip--extra { background: #dbeafe; color: #1d4ed8; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Ebay 3 Listing Variation Verify',
        'sub_title'  => 'eBay 3 Listings',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
                            <span class="ebay3-stat-badge ebay3-stat-badge--parents" title="Parents from CP Master">PARENTS:<span id="ebay3-lvv-badge-parents">0</span></span>
                            <span class="ebay3-stat-badge ebay3-stat-badge--children" title="Required child SKUs from CP Master">REQUIRED:<span id="ebay3-lvv-badge-children">0</span></span>
                            <span class="ebay3-stat-badge ebay3-stat-badge--listed" title="eBay listings cache (ebay_3_metrics)">LISTED:<span id="ebay3-lvv-badge-listed">0</span></span>
                            <span class="ebay3-stat-badge ebay3-stat-badge--mismatch" title="Parents with missing or excess SKUs">MISMATCH:<span id="ebay3-lvv-badge-mismatch">0</span></span>
                        </div>
                        <span id="ebay3-lvv-total" class="badge bg-secondary">Total: —</span>
                        <span id="ebay3-lvv-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="ebay3-lvv-refresh-btn" class="btn btn-sm btn-outline-primary ebay3-raw-icon-btn" title="Refresh" aria-label="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="ebay3-lvv-pull-btn" class="btn btn-sm btn-warning text-dark" title="Pull eBay listings (inventory report)">
                            <i class="fas fa-cloud-download-alt me-1"></i> Pull Listings
                        </button>
                        <button type="button" id="ebay3-lvv-export-btn" class="btn btn-sm btn-success" title="Export filtered rows to Excel">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </button>
                        <span class="text-muted small" id="ebay3-lvv-status-line"></span>
                    </div>

                    <div id="ebay3-lvv-filter-bar" class="mb-2">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="ebay3-lvv-filter-label" for="ebay3-lvv-listed-filter">Listed</label>
                                <select id="ebay3-lvv-listed-filter" class="form-select form-select-sm ebay3-lvv-filter-select">
                                    <option value="all">All</option>
                                    <option value="mismatch" selected>Mismatch Only</option>
                                    <option value="match">Match Only</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="ebay3-lvv-filter-apply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="ebay3-lvv-filter-clear">Clear</button>
                            </div>
                            <div class="ms-auto d-flex flex-wrap align-items-center gap-2 small">
                                <span class="ebay3-lvv-sku-chip ebay3-lvv-sku-chip--missing">Missing</span>
                                <span class="text-muted">in CP Master, not on parent listing</span>
                                <span class="ebay3-lvv-sku-chip ebay3-lvv-sku-chip--extra">Excess</span>
                                <span class="text-muted">on parent listing, not in CP Master</span>
                            </div>
                        </div>
                    </div>

                    <div id="ebay3-lvv-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="ebay3-lvv-search" class="form-control" placeholder="Search Parent..." autocomplete="off" aria-label="Search Parent" maxlength="100">
                            <span id="ebay3-lvv-source-label" class="badge bg-dark text-nowrap">CP Master</span>
                        </div>
                        <div id="ebay3-listing-variation-verify-table"></div>
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
        let ebay3LvvTable = null;

        function ebay3LvvEscapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function ebay3LvvDash(val) {
            if (val === null || val === undefined || val === '') {
                return '<span class="text-muted">--</span>';
            }
            return val;
        }

        function ebay3LvvUpdateMeta(meta) {
            if (!meta) return;
            $('#ebay3-lvv-badge-parents').text((meta.required_parent_count || 0).toLocaleString());
            $('#ebay3-lvv-badge-children').text((meta.required_child_count || 0).toLocaleString());
            $('#ebay3-lvv-badge-listed').text((meta.listings_count || 0).toLocaleString());
            $('#ebay3-lvv-badge-mismatch').text((meta.mismatch_count || 0).toLocaleString());

            const parts = [];
            if (meta.required_refreshed_at) parts.push('CP Master · ' + meta.required_refreshed_at);
            if (meta.last_pulled_at) parts.push('Listings · ' + meta.last_pulled_at);
            $('#ebay3-lvv-status-line').text(parts.join(' · '));
            $('#ebay3-lvv-source-label').text(meta.has_listings_cache ? 'CP Master + Listings' : 'CP Master');
        }

        function ebay3LvvUpdateRowCount() {
            if (!ebay3LvvTable) return;
            const shown = ebay3LvvTable.getDataCount('active');
            const total = ebay3LvvTable.getDataCount();
            $('#ebay3-lvv-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            try {
                $('#ebay3-lvv-page-info').text('Page: ' + ebay3LvvTable.getPage() + ' / ' + ebay3LvvTable.getPageMax());
            } catch (e) {
                $('#ebay3-lvv-page-info').text('Page: —');
            }
        }

        function ebay3LvvApplyFilters() {
            if (!ebay3LvvTable) return;
            ebay3LvvTable.clearFilter();

            const listedFilter = $('#ebay3-lvv-listed-filter').val();
            const q = ($('#ebay3-lvv-search').val() || '').trim().toLowerCase();

            if (listedFilter === 'mismatch') {
                ebay3LvvTable.addFilter(d => d.match_status === false);
            } else if (listedFilter === 'match') {
                ebay3LvvTable.addFilter(d => d.match_status === true);
            }

            if (q) {
                ebay3LvvTable.addFilter(d => String(d.parent || '').toLowerCase().includes(q));
            }

            ebay3LvvUpdateRowCount();
        }

        function ebay3LvvFormatRequired(cell) {
            const label = cell.getRow().getData().child_sku_required_label;
            if (label === null || label === undefined || label === '') return ebay3LvvDash(null);
            return `<span class="fw-semibold ebay3-lvv-avail-yes">${ebay3LvvEscapeHtml(label)}</span>`;
        }

        function ebay3LvvSkuChips(skus, type) {
            if (!Array.isArray(skus) || skus.length === 0) return '';
            const chipCls = type === 'extra' ? 'ebay3-lvv-sku-chip--extra' : 'ebay3-lvv-sku-chip--missing';
            return skus.map(s =>
                `<span class="ebay3-lvv-sku-chip ${chipCls}">${ebay3LvvEscapeHtml(s)}</span>`
            ).join('');
        }

        function ebay3LvvFormatAvailable(cell) {
            const d = cell.getRow().getData();
            const label = d.child_sku_available_label || '';
            if (!label || label === '—') return ebay3LvvDash(null);

            const avail = parseInt(d.child_sku_available_count, 10) || 0;
            const total = parseInt(d.child_sku_total, 10) || 0;
            const extraCount = parseInt(d.extra_count, 10) || 0;

            let cls = 'ebay3-lvv-avail-partial';
            if (total > 0 && avail === total && extraCount === 0) cls = 'ebay3-lvv-avail-yes';
            else if (avail === 0) cls = 'ebay3-lvv-avail-no';

            return `<span class="fw-semibold ${cls}">${ebay3LvvEscapeHtml(label)}</span>`;
        }

        function ebay3LvvFormatMissingExcess(cell) {
            const d = cell.getRow().getData();
            const missingSkus = Array.isArray(d.missing_skus) ? d.missing_skus : [];
            const extraSkus = Array.isArray(d.extra_skus) ? d.extra_skus : [];

            if (missingSkus.length === 0 && extraSkus.length === 0) {
                return ebay3LvvDash(null);
            }

            let html = '';
            if (missingSkus.length > 0) {
                html += `<span class="ebay3-lvv-diff ebay3-lvv-diff-missing">`
                    + `<span class="ebay3-lvv-diff-label">Missing:</span>`
                    + ebay3LvvSkuChips(missingSkus, 'missing')
                    + `</span>`;
            }
            if (extraSkus.length > 0) {
                html += `<span class="ebay3-lvv-diff ebay3-lvv-diff-extra">`
                    + `<span class="ebay3-lvv-diff-label">Excess:</span>`
                    + ebay3LvvSkuChips(extraSkus, 'extra')
                    + `</span>`;
            }
            return html;
        }

        function ebay3LvvExportExcel() {
            if (!ebay3LvvTable || typeof XLSX === 'undefined') {
                alert('Export library not loaded. Please refresh and try again.');
                return;
            }

            const rows = ebay3LvvTable.getData('active') || [];
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
            XLSX.utils.book_append_sheet(wb, ws, 'Ebay3 Variation Verify');

            const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            XLSX.writeFile(wb, 'ebay3_listing_variation_verify_' + stamp + '.xlsx');
        }

        $(document).ready(function () {
            ebay3LvvTable = new Tabulator('#ebay3-listing-variation-verify-table', {
                ajaxURL: '{{ route("ebay3.listing.variation.verify.data") }}',
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    if (response && response.meta) ebay3LvvUpdateMeta(response.meta);
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
                    row.getElement().classList.add('ebay3-lvv-parent-row');
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
                            if (!v) return ebay3LvvDash(null);
                            return `<span class="fw-semibold">${ebay3LvvEscapeHtml(v)}</span>`;
                        }
                    },
                    {
                        title: 'Required',
                        field: 'child_sku_required_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 100,
                        widthGrow: 1,
                        formatter: ebay3LvvFormatRequired
                    },
                    {
                        title: 'Parent Vs Listed SKU',
                        field: 'child_sku_available_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 140,
                        widthGrow: 1,
                        formatter: ebay3LvvFormatAvailable
                    },
                    {
                        title: 'Missing / Excess SKU',
                        field: 'missing_skus',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 320,
                        widthGrow: 4,
                        formatter: ebay3LvvFormatMissingExcess,
                        variableHeight: true
                    }
                ]
            });

            ebay3LvvTable.on('dataProcessed', ebay3LvvApplyFilters);
            ebay3LvvTable.on('dataFiltered', ebay3LvvUpdateRowCount);
            ebay3LvvTable.on('pageLoaded', ebay3LvvUpdateRowCount);

            $('#ebay3-lvv-filter-apply').on('click', ebay3LvvApplyFilters);
            $('#ebay3-lvv-listed-filter').on('change', ebay3LvvApplyFilters);
            $('#ebay3-lvv-filter-clear').on('click', function () {
                $('#ebay3-lvv-listed-filter').val('all');
                $('#ebay3-lvv-search').val('');
                ebay3LvvApplyFilters();
            });

            let searchTimer = null;
            $('#ebay3-lvv-search').on('keyup search', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(ebay3LvvApplyFilters, 200);
            });

            $('#ebay3-lvv-refresh-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                ebay3LvvTable.setData('{{ route("ebay3.listing.variation.verify.data") }}')
                    .finally(function () { $btn.prop('disabled', false); });
            });

            $('#ebay3-lvv-export-btn').on('click', ebay3LvvExportExcel);

            $('#ebay3-lvv-pull-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;

                if (!confirm('Pull all merchant listings from eBay 3 inventory report?\n\nThis runs app:fetch-ebay-three-metrics and may take a few minutes.')) {
                    return;
                }

                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Pulling…');

                $.ajax({
                    url: '{{ route("ebay3.listing.variation.verify.pull") }}',
                    method: 'POST',
                    timeout: 0,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function (res) {
                        if (res.status === 200) {
                            $('#ebay3-lvv-status-line').text(res.message || 'Pull completed.');
                            ebay3LvvTable.setData('{{ route("ebay3.listing.variation.verify.data") }}');
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
                            .html('<i class="fas fa-cloud-download-alt me-1"></i> Pull Listings');
                    }
                });
            });
        });
    </script>
@endsection
