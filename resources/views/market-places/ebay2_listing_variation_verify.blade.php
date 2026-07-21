@extends('layouts.vertical', ['title' => 'Ebay 2 Listing Variation Verify'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #ebay2-lvv-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
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
        #ebay2-lvv-wrap { overflow-x: auto; overflow-y: visible; }

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
        .ebay2-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .ebay2-raw-icon-btn > i { font-size: 14px; }

        .ebay2-lvv-avail-yes { color: #16a34a; font-weight: 700; }
        .ebay2-lvv-avail-no { color: #dc2626; font-weight: 700; }
        .ebay2-lvv-avail-na { color: #94a3b8; }
        .ebay2-lvv-avail-partial { color: #ea580c; font-weight: 700; }
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
                        </div>
                        <span id="ebay2-lvv-total" class="badge bg-secondary">Total: —</span>
                        <span id="ebay2-lvv-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="ebay2-lvv-refresh-btn" class="btn btn-sm btn-outline-primary ebay2-raw-icon-btn" title="Refresh" aria-label="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="ebay2-lvv-pull-btn" class="btn btn-sm btn-warning text-dark" title="Pull eBay listings (inventory report)">
                            <i class="fas fa-cloud-download-alt me-1"></i> Pull Listings
                        </button>
                        <span class="text-muted small" id="ebay2-lvv-status-line"></span>
                    </div>

                    <div id="ebay2-lvv-filter-bar" class="mb-2">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="ebay2-lvv-filter-label" for="ebay2-lvv-listed-filter">Listed</label>
                                <select id="ebay2-lvv-listed-filter" class="form-select form-select-sm ebay2-lvv-filter-select">
                                    <option value="all" selected>All</option>
                                    <option value="mismatch">Mismatch Only</option>
                                    <option value="match">Match Only</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="ebay2-lvv-filter-apply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="ebay2-lvv-filter-clear">Clear</button>
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
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        let ebay2LvvTable = null;

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

        function ebay2LvvApplyFilters() {
            if (!ebay2LvvTable) return;
            ebay2LvvTable.clearFilter();

            const listedFilter = $('#ebay2-lvv-listed-filter').val();
            const q = ($('#ebay2-lvv-search').val() || '').trim().toLowerCase();

            if (listedFilter === 'mismatch') {
                ebay2LvvTable.addFilter(d => d.match_status === false);
            } else if (listedFilter === 'match') {
                ebay2LvvTable.addFilter(d => d.match_status === true);
            }

            if (q) {
                ebay2LvvTable.addFilter(d => String(d.parent || '').toLowerCase().includes(q));
            }

            ebay2LvvUpdateRowCount();
        }

        function ebay2LvvFormatRequired(cell) {
            const label = cell.getRow().getData().child_sku_required_label;
            if (label === null || label === undefined || label === '') return ebay2LvvDash(null);
            return `<span class="fw-semibold ebay2-lvv-avail-yes">${ebay2LvvEscapeHtml(label)}</span>`;
        }

        function ebay2LvvFormatAvailable(cell) {
            const d = cell.getRow().getData();
            const label = d.child_sku_available_label || '';
            if (!label || label === '—') return ebay2LvvDash(null);

            const avail = parseInt(d.child_sku_available_count, 10) || 0;
            const total = parseInt(d.child_sku_total, 10) || 0;
            let cls = 'ebay2-lvv-avail-partial';
            if (total > 0 && avail === total) cls = 'ebay2-lvv-avail-yes';
            else if (avail === 0) cls = 'ebay2-lvv-avail-no';
            return `<span class="fw-semibold ${cls}">${ebay2LvvEscapeHtml(label)}</span>`;
        }

        $(document).ready(function () {
            ebay2LvvTable = new Tabulator('#ebay2-listing-variation-verify-table', {
                ajaxURL: '{{ route("ebay2.listing.variation.verify.data") }}',
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    if (response && response.meta) ebay2LvvUpdateMeta(response.meta);
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
                    row.getElement().classList.add('ebay2-lvv-parent-row');
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
                            if (!v) return ebay2LvvDash(null);
                            return `<span class="fw-semibold">${ebay2LvvEscapeHtml(v)}</span>`;
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
                    }
                ]
            });

            ebay2LvvTable.on('dataProcessed', ebay2LvvApplyFilters);
            ebay2LvvTable.on('dataFiltered', ebay2LvvUpdateRowCount);
            ebay2LvvTable.on('pageLoaded', ebay2LvvUpdateRowCount);

            $('#ebay2-lvv-filter-apply').on('click', ebay2LvvApplyFilters);
            $('#ebay2-lvv-listed-filter').on('change', ebay2LvvApplyFilters);
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
                ebay2LvvTable.setData('{{ route("ebay2.listing.variation.verify.data") }}')
                    .finally(function () { $btn.prop('disabled', false); });
            });

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
                            ebay2LvvTable.setData('{{ route("ebay2.listing.variation.verify.data") }}');
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
