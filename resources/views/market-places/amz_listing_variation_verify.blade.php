@extends('layouts.vertical', ['title' => 'Amz Listing Variation Verify'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #amz-lvv-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
        }
        #amz-lvv-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #amz-lvv-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #amz-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #amz-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #amz-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important; display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #amz-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #amz-lvv-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #amz-lvv-wrap .tabulator .tabulator-row { min-height: 32px; }
        #amz-lvv-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #amz-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }

        #amz-lvv-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
        }
        #amz-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #amz-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #amz-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #amz-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #amz-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #amz-lvv-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        #amz-lvv-wrap { overflow-x: auto; overflow-y: visible; }

        #amz-lvv-wrap .tabulator-row.amz-lvv-parent-row,
        #amz-lvv-wrap .tabulator-row.amz-lvv-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            color: #664d03;
        }
        #amz-lvv-wrap .tabulator-row.amz-lvv-parent-row:hover,
        #amz-lvv-wrap .tabulator-row.amz-lvv-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        #amz-lvv-filter-bar {
            background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;
        }
        #amz-lvv-filter-bar .amz-lvv-filter-label {
            display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px; letter-spacing: 0.01em;
        }
        #amz-lvv-filter-bar .amz-lvv-filter-select {
            min-width: 120px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;
            color: #64748b; font-size: 0.8125rem; padding-top: 0.35rem; padding-bottom: 0.35rem;
        }

        .amz-stat-badge {
            display: inline-flex; align-items: center; flex-shrink: 0; color: #fff; font-size: 15px; font-weight: 700;
            padding: 9px 16px; border-radius: 8px; white-space: nowrap; line-height: 1.25; letter-spacing: 0.2px;
        }
        .amz-stat-badge > span { margin-left: 4px; font-size: 16px; font-weight: 800; }
        .amz-stat-badge--parents { background: #4c7ed8; }
        .amz-stat-badge--children { background: #8b5cf6; }
        .amz-stat-badge--listed { background: #16a34a; }
        .amz-stat-badge--mismatch { background: #dc2626; }
        .amz-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .amz-raw-icon-btn > i { font-size: 14px; }

        .amz-lvv-avail-yes { color: #16a34a; font-weight: 700; }
        .amz-lvv-avail-no { color: #dc2626; font-weight: 700; }
        .amz-lvv-avail-na { color: #94a3b8; }
        .amz-lvv-avail-partial { color: #ea580c; font-weight: 700; }

        .amz-lvv-diff { display: block; margin-top: 4px; line-height: 1.35; text-align: left; font-weight: 500; }
        .amz-lvv-diff-missing { color: #dc2626; }
        .amz-lvv-diff-extra { color: #2563eb; }
        .amz-lvv-diff-label { font-weight: 700; margin-right: 4px; }
        .amz-lvv-sku-chip {
            display: inline-block; margin: 1px 3px 1px 0; padding: 1px 6px; border-radius: 4px;
            font-size: 11.5px; font-weight: 600; line-height: 1.4;
        }
        .amz-lvv-sku-chip--missing { background: #fee2e2; color: #b91c1c; }
        .amz-lvv-sku-chip--extra { background: #dbeafe; color: #1d4ed8; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amz Listing Variation Verify',
        'sub_title'  => 'Amazon Listings',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
                            <span class="amz-stat-badge amz-stat-badge--parents" title="Parents from CP Master">PARENTS:<span id="amz-lvv-badge-parents">0</span></span>
                            <span class="amz-stat-badge amz-stat-badge--children" title="Required child SKUs from CP Master">REQUIRED:<span id="amz-lvv-badge-children">0</span></span>
                            <span class="amz-stat-badge amz-stat-badge--listed" title="eBay listings cache (amazon_listings_raw)">LISTED:<span id="amz-lvv-badge-listed">0</span></span>
                            <span class="amz-stat-badge amz-stat-badge--mismatch" title="Parents with missing or excess SKUs">MISMATCH:<span id="amz-lvv-badge-mismatch">0</span></span>
                        </div>
                        <span id="amz-lvv-total" class="badge bg-secondary">Total: —</span>
                        <span id="amz-lvv-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="amz-lvv-refresh-btn" class="btn btn-sm btn-outline-primary amz-raw-icon-btn" title="Refresh" aria-label="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="amz-lvv-pull-btn" class="btn btn-sm btn-warning text-dark" title="Pull Amazon listings (inventory report)">
                            <i class="fas fa-cloud-download-alt me-1"></i> Pull Listings
                        </button>
                        <button type="button" id="amz-lvv-export-btn" class="btn btn-sm btn-success" title="Export filtered rows to Excel">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </button>
                        <span class="text-muted small" id="amz-lvv-status-line"></span>
                    </div>

                    <div id="amz-lvv-filter-bar" class="mb-2">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="amz-lvv-filter-label" for="amz-lvv-listed-filter">Listed</label>
                                <select id="amz-lvv-listed-filter" class="form-select form-select-sm amz-lvv-filter-select">
                                    <option value="all">All</option>
                                    <option value="mismatch" selected>Mismatch Only</option>
                                    <option value="match">Match Only</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="amz-lvv-filter-apply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="amz-lvv-filter-clear">Clear</button>
                            </div>
                            <div class="ms-auto d-flex flex-wrap align-items-center gap-2 small">
                                <span class="amz-lvv-sku-chip amz-lvv-sku-chip--missing">Missing</span>
                                <span class="text-muted">in CP Master, not on parent listing</span>
                                <span class="amz-lvv-sku-chip amz-lvv-sku-chip--extra">Excess</span>
                                <span class="text-muted">on parent listing, not in CP Master</span>
                            </div>
                        </div>
                    </div>

                    <div id="amz-lvv-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="amz-lvv-search" class="form-control" placeholder="Search Parent..." autocomplete="off" aria-label="Search Parent" maxlength="100">
                            <span id="amz-lvv-source-label" class="badge bg-dark text-nowrap">CP Master</span>
                        </div>
                        <div id="amz-listing-variation-verify-table"></div>
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
        let amzLvvTable = null;

        function amzLvvEscapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function amzLvvDash(val) {
            if (val === null || val === undefined || val === '') {
                return '<span class="text-muted">--</span>';
            }
            return val;
        }

        function amzLvvUpdateMeta(meta) {
            if (!meta) return;
            $('#amz-lvv-badge-parents').text((meta.required_parent_count || 0).toLocaleString());
            $('#amz-lvv-badge-children').text((meta.required_child_count || 0).toLocaleString());
            $('#amz-lvv-badge-listed').text((meta.listings_count || 0).toLocaleString());
            $('#amz-lvv-badge-mismatch').text((meta.mismatch_count || 0).toLocaleString());

            const parts = [];
            if (meta.required_refreshed_at) parts.push('CP Master · ' + meta.required_refreshed_at);
            if (meta.last_pulled_at) parts.push('Listings · ' + meta.last_pulled_at);
            $('#amz-lvv-status-line').text(parts.join(' · '));
            $('#amz-lvv-source-label').text(meta.has_listings_cache ? 'CP Master + Listings' : 'CP Master');

            // Keep sidebar badge in sync with page MISMATCH count.
            const $nav = $('a.amz-lvv-mismatch-nav');
            if ($nav.length) {
                const count = parseInt(meta.mismatch_count, 10) || 0;
                let $badge = $nav.children('.badge');
                if (count > 0) {
                    if ($badge.length) {
                        $badge.text(count);
                    } else {
                        $nav.append('<span class="badge bg-danger rounded-pill">' + count + '</span>');
                    }
                } else {
                    $badge.remove();
                }
            }
        }

        function amzLvvUpdateRowCount() {
            if (!amzLvvTable) return;
            const shown = amzLvvTable.getDataCount('active');
            const total = amzLvvTable.getDataCount();
            $('#amz-lvv-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            try {
                $('#amz-lvv-page-info').text('Page: ' + amzLvvTable.getPage() + ' / ' + amzLvvTable.getPageMax());
            } catch (e) {
                $('#amz-lvv-page-info').text('Page: —');
            }
        }

        function amzLvvApplyFilters() {
            if (!amzLvvTable) return;
            amzLvvTable.clearFilter();

            const listedFilter = $('#amz-lvv-listed-filter').val();
            const q = ($('#amz-lvv-search').val() || '').trim().toLowerCase();

            if (listedFilter === 'mismatch') {
                amzLvvTable.addFilter(d => d.match_status === false);
            } else if (listedFilter === 'match') {
                amzLvvTable.addFilter(d => d.match_status === true);
            }

            if (q) {
                amzLvvTable.addFilter(d => String(d.parent || '').toLowerCase().includes(q));
            }

            amzLvvUpdateRowCount();
        }

        function amzLvvFormatRequired(cell) {
            const label = cell.getRow().getData().child_sku_required_label;
            if (label === null || label === undefined || label === '') return amzLvvDash(null);
            return `<span class="fw-semibold amz-lvv-avail-yes">${amzLvvEscapeHtml(label)}</span>`;
        }

        function amzLvvSkuChips(skus, type) {
            if (!Array.isArray(skus) || skus.length === 0) return '';
            const chipCls = type === 'extra' ? 'amz-lvv-sku-chip--extra' : 'amz-lvv-sku-chip--missing';
            return skus.map(s =>
                `<span class="amz-lvv-sku-chip ${chipCls}">${amzLvvEscapeHtml(s)}</span>`
            ).join('');
        }

        function amzLvvFormatAvailable(cell) {
            const d = cell.getRow().getData();
            const label = d.child_sku_available_label || '';
            if (!label || label === '—') return amzLvvDash(null);

            const avail = parseInt(d.child_sku_available_count, 10) || 0;
            const total = parseInt(d.child_sku_total, 10) || 0;
            const extraCount = parseInt(d.extra_count, 10) || 0;

            let cls = 'amz-lvv-avail-partial';
            if (total > 0 && avail === total && extraCount === 0) cls = 'amz-lvv-avail-yes';
            else if (avail === 0) cls = 'amz-lvv-avail-no';

            return `<span class="fw-semibold ${cls}">${amzLvvEscapeHtml(label)}</span>`;
        }

        function amzLvvFormatMissingExcess(cell) {
            const d = cell.getRow().getData();
            const missingSkus = Array.isArray(d.missing_skus) ? d.missing_skus : [];
            const extraSkus = Array.isArray(d.extra_skus) ? d.extra_skus : [];

            if (missingSkus.length === 0 && extraSkus.length === 0) {
                return amzLvvDash(null);
            }

            let html = '';
            if (missingSkus.length > 0) {
                html += `<span class="amz-lvv-diff amz-lvv-diff-missing">`
                    + `<span class="amz-lvv-diff-label">Missing:</span>`
                    + amzLvvSkuChips(missingSkus, 'missing')
                    + `</span>`;
            }
            if (extraSkus.length > 0) {
                html += `<span class="amz-lvv-diff amz-lvv-diff-extra">`
                    + `<span class="amz-lvv-diff-label">Excess:</span>`
                    + amzLvvSkuChips(extraSkus, 'extra')
                    + `</span>`;
            }
            return html;
        }

        function amzLvvExportExcel() {
            if (!amzLvvTable || typeof XLSX === 'undefined') {
                alert('Export library not loaded. Please refresh and try again.');
                return;
            }

            const rows = amzLvvTable.getData('active') || [];
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
            XLSX.utils.book_append_sheet(wb, ws, 'Amz Variation Verify');

            const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            XLSX.writeFile(wb, 'amz_listing_variation_verify_' + stamp + '.xlsx');
        }

        $(document).ready(function () {
            amzLvvTable = new Tabulator('#amz-listing-variation-verify-table', {
                ajaxURL: '{{ route("amz.listing.variation.verify.data") }}',
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    if (response && response.meta) amzLvvUpdateMeta(response.meta);
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
                    row.getElement().classList.add('amz-lvv-parent-row');
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
                            if (!v) return amzLvvDash(null);
                            return `<span class="fw-semibold">${amzLvvEscapeHtml(v)}</span>`;
                        }
                    },
                    {
                        title: 'Required',
                        field: 'child_sku_required_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 100,
                        widthGrow: 1,
                        formatter: amzLvvFormatRequired
                    },
                    {
                        title: 'Parent Vs Listed SKU',
                        field: 'child_sku_available_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 140,
                        widthGrow: 1,
                        formatter: amzLvvFormatAvailable
                    },
                    {
                        title: 'Missing / Excess SKU',
                        field: 'missing_skus',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 320,
                        widthGrow: 4,
                        formatter: amzLvvFormatMissingExcess,
                        variableHeight: true
                    }
                ]
            });

            amzLvvTable.on('dataProcessed', amzLvvApplyFilters);
            amzLvvTable.on('dataFiltered', amzLvvUpdateRowCount);
            amzLvvTable.on('pageLoaded', amzLvvUpdateRowCount);

            $('#amz-lvv-filter-apply').on('click', amzLvvApplyFilters);
            $('#amz-lvv-listed-filter').on('change', amzLvvApplyFilters);
            $('#amz-lvv-filter-clear').on('click', function () {
                $('#amz-lvv-listed-filter').val('all');
                $('#amz-lvv-search').val('');
                amzLvvApplyFilters();
            });

            let searchTimer = null;
            $('#amz-lvv-search').on('keyup search', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(amzLvvApplyFilters, 200);
            });

            $('#amz-lvv-refresh-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                amzLvvTable.setData('{{ route("amz.listing.variation.verify.data") }}')
                    .finally(function () { $btn.prop('disabled', false); });
            });

            $('#amz-lvv-export-btn').on('click', amzLvvExportExcel);

            $('#amz-lvv-pull-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;

                if (!confirm('Pull all merchant listings from Amazon SP-API?\n\nThis uses GET_MERCHANT_LISTINGS_ALL_DATA and may take several minutes.')) {
                    return;
                }

                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Pulling…');

                $.ajax({
                    url: '{{ route("amz.listing.variation.verify.pull") }}',
                    method: 'POST',
                    timeout: 0,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function (res) {
                        if (res.status === 200) {
                            $('#amz-lvv-status-line').text(res.message || 'Pull completed.');
                            amzLvvTable.setData('{{ route("amz.listing.variation.verify.data") }}');
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
