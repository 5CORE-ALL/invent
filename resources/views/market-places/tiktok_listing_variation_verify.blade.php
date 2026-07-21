@extends('layouts.vertical', ['title' => 'TikTok 1 Listing Variation Verify'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #tiktok-lvv-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
        }
        #tiktok-lvv-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #tiktok-lvv-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #tiktok-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #tiktok-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #tiktok-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important; display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #tiktok-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #tiktok-lvv-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #tiktok-lvv-wrap .tabulator .tabulator-row { min-height: 32px; }
        #tiktok-lvv-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #tiktok-lvv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }

        #tiktok-lvv-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
        }
        #tiktok-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #tiktok-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #tiktok-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #tiktok-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #tiktok-lvv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #tiktok-lvv-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        #tiktok-lvv-wrap { overflow-x: auto; overflow-y: visible; }

        #tiktok-lvv-wrap .tabulator-row.tiktok-lvv-parent-row,
        #tiktok-lvv-wrap .tabulator-row.tiktok-lvv-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            color: #664d03;
        }
        #tiktok-lvv-wrap .tabulator-row.tiktok-lvv-parent-row:hover,
        #tiktok-lvv-wrap .tabulator-row.tiktok-lvv-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        #tiktok-lvv-filter-bar {
            background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;
        }
        #tiktok-lvv-filter-bar .tiktok-lvv-filter-label {
            display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px; letter-spacing: 0.01em;
        }
        #tiktok-lvv-filter-bar .tiktok-lvv-filter-select {
            min-width: 120px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;
            color: #64748b; font-size: 0.8125rem; padding-top: 0.35rem; padding-bottom: 0.35rem;
        }

        .tiktok-stat-badge {
            display: inline-flex; align-items: center; flex-shrink: 0; color: #fff; font-size: 15px; font-weight: 700;
            padding: 9px 16px; border-radius: 8px; white-space: nowrap; line-height: 1.25; letter-spacing: 0.2px;
        }
        .tiktok-stat-badge > span { margin-left: 4px; font-size: 16px; font-weight: 800; }
        .tiktok-stat-badge--parents { background: #4c7ed8; }
        .tiktok-stat-badge--children { background: #8b5cf6; }
        .tiktok-stat-badge--listed { background: #16a34a; }
        .tiktok-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .tiktok-raw-icon-btn > i { font-size: 14px; }

        .tiktok-lvv-avail-yes { color: #16a34a; font-weight: 700; }
        .tiktok-lvv-avail-no { color: #dc2626; font-weight: 700; }
        .tiktok-lvv-avail-na { color: #94a3b8; }
        .tiktok-lvv-avail-partial { color: #ea580c; font-weight: 700; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'TikTok 1 Listing Variation Verify',
        'sub_title'  => 'TikTok 1 Listings',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
                            <span class="tiktok-stat-badge tiktok-stat-badge--parents" title="Parents from CP Master">PARENTS:<span id="tiktok-lvv-badge-parents">0</span></span>
                            <span class="tiktok-stat-badge tiktok-stat-badge--children" title="Required child SKUs from CP Master">REQUIRED:<span id="tiktok-lvv-badge-children">0</span></span>
                            <span class="tiktok-stat-badge tiktok-stat-badge--listed" title="TikTok 1 listings cache (tiktok_products)">LISTED:<span id="tiktok-lvv-badge-listed">0</span></span>
                        </div>
                        <span id="tiktok-lvv-total" class="badge bg-secondary">Total: —</span>
                        <span id="tiktok-lvv-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="tiktok-lvv-refresh-btn" class="btn btn-sm btn-outline-primary tiktok-raw-icon-btn" title="Refresh" aria-label="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="tiktok-lvv-pull-btn" class="btn btn-sm btn-warning text-dark" title="Pull TikTok 1 listings (Shop API)">
                            <i class="fas fa-cloud-download-alt me-1"></i> Pull Listings
                        </button>
                        <span class="text-muted small" id="tiktok-lvv-status-line"></span>
                    </div>

                    <div id="tiktok-lvv-filter-bar" class="mb-2">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="tiktok-lvv-filter-label" for="tiktok-lvv-listed-filter">Listed</label>
                                <select id="tiktok-lvv-listed-filter" class="form-select form-select-sm tiktok-lvv-filter-select">
                                    <option value="all" selected>All</option>
                                    <option value="mismatch">Mismatch Only</option>
                                    <option value="match">Match Only</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="tiktok-lvv-filter-apply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="tiktok-lvv-filter-clear">Clear</button>
                            </div>
                        </div>
                    </div>

                    <div id="tiktok-lvv-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="tiktok-lvv-search" class="form-control" placeholder="Search Parent..." autocomplete="off" aria-label="Search Parent" maxlength="100">
                            <span id="tiktok-lvv-source-label" class="badge bg-dark text-nowrap">CP Master</span>
                        </div>
                        <div id="tiktok-listing-variation-verify-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        let tiktokLvvTable = null;

        function tiktokLvvEscapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function tiktokLvvDash(val) {
            if (val === null || val === undefined || val === '') {
                return '<span class="text-muted">--</span>';
            }
            return val;
        }

        function tiktokLvvUpdateMeta(meta) {
            if (!meta) return;
            $('#tiktok-lvv-badge-parents').text((meta.required_parent_count || 0).toLocaleString());
            $('#tiktok-lvv-badge-children').text((meta.required_child_count || 0).toLocaleString());
            $('#tiktok-lvv-badge-listed').text((meta.listings_count || 0).toLocaleString());

            const parts = [];
            if (meta.required_refreshed_at) parts.push('CP Master · ' + meta.required_refreshed_at);
            if (meta.last_pulled_at) parts.push('Listings · ' + meta.last_pulled_at);
            $('#tiktok-lvv-status-line').text(parts.join(' · '));
            $('#tiktok-lvv-source-label').text(meta.has_listings_cache ? 'CP Master + Listings' : 'CP Master');
        }

        function tiktokLvvUpdateRowCount() {
            if (!tiktokLvvTable) return;
            const shown = tiktokLvvTable.getDataCount('active');
            const total = tiktokLvvTable.getDataCount();
            $('#tiktok-lvv-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            try {
                $('#tiktok-lvv-page-info').text('Page: ' + tiktokLvvTable.getPage() + ' / ' + tiktokLvvTable.getPageMax());
            } catch (e) {
                $('#tiktok-lvv-page-info').text('Page: —');
            }
        }

        function tiktokLvvApplyFilters() {
            if (!tiktokLvvTable) return;
            tiktokLvvTable.clearFilter();

            const listedFilter = $('#tiktok-lvv-listed-filter').val();
            const q = ($('#tiktok-lvv-search').val() || '').trim().toLowerCase();

            if (listedFilter === 'mismatch') {
                tiktokLvvTable.addFilter(d => d.match_status === false);
            } else if (listedFilter === 'match') {
                tiktokLvvTable.addFilter(d => d.match_status === true);
            }

            if (q) {
                tiktokLvvTable.addFilter(d => String(d.parent || '').toLowerCase().includes(q));
            }

            tiktokLvvUpdateRowCount();
        }

        function tiktokLvvFormatRequired(cell) {
            const label = cell.getRow().getData().child_sku_required_label;
            if (label === null || label === undefined || label === '') return tiktokLvvDash(null);
            return `<span class="fw-semibold tiktok-lvv-avail-yes">${tiktokLvvEscapeHtml(label)}</span>`;
        }

        function tiktokLvvFormatAvailable(cell) {
            const d = cell.getRow().getData();
            const label = d.child_sku_available_label || '';
            if (!label || label === '—') return tiktokLvvDash(null);

            const avail = parseInt(d.child_sku_available_count, 10) || 0;
            const total = parseInt(d.child_sku_total, 10) || 0;
            let cls = 'tiktok-lvv-avail-partial';
            if (total > 0 && avail === total) cls = 'tiktok-lvv-avail-yes';
            else if (avail === 0) cls = 'tiktok-lvv-avail-no';
            return `<span class="fw-semibold ${cls}">${tiktokLvvEscapeHtml(label)}</span>`;
        }

        $(document).ready(function () {
            tiktokLvvTable = new Tabulator('#tiktok-listing-variation-verify-table', {
                ajaxURL: '{{ route("tiktok.listing.variation.verify.data") }}',
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    if (response && response.meta) tiktokLvvUpdateMeta(response.meta);
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
                    row.getElement().classList.add('tiktok-lvv-parent-row');
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
                            if (!v) return tiktokLvvDash(null);
                            return `<span class="fw-semibold">${tiktokLvvEscapeHtml(v)}</span>`;
                        }
                    },
                    {
                        title: 'Required',
                        field: 'child_sku_required_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 100,
                        widthGrow: 1,
                        formatter: tiktokLvvFormatRequired
                    },
                    {
                        title: 'Parent Vs Listed SKU',
                        field: 'child_sku_available_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 140,
                        widthGrow: 1,
                        formatter: tiktokLvvFormatAvailable
                    }
                ]
            });

            tiktokLvvTable.on('dataProcessed', tiktokLvvApplyFilters);
            tiktokLvvTable.on('dataFiltered', tiktokLvvUpdateRowCount);
            tiktokLvvTable.on('pageLoaded', tiktokLvvUpdateRowCount);

            $('#tiktok-lvv-filter-apply').on('click', tiktokLvvApplyFilters);
            $('#tiktok-lvv-listed-filter').on('change', tiktokLvvApplyFilters);
            $('#tiktok-lvv-filter-clear').on('click', function () {
                $('#tiktok-lvv-listed-filter').val('all');
                $('#tiktok-lvv-search').val('');
                tiktokLvvApplyFilters();
            });

            let searchTimer = null;
            $('#tiktok-lvv-search').on('keyup search', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(tiktokLvvApplyFilters, 200);
            });

            $('#tiktok-lvv-refresh-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                tiktokLvvTable.setData('{{ route("tiktok.listing.variation.verify.data") }}')
                    .finally(function () { $btn.prop('disabled', false); });
            });

            $('#tiktok-lvv-pull-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;

                if (!confirm('Pull all product listings from TikTok Shop API?\n\nThis runs sync:tiktok-api-data and may take a few minutes.')) {
                    return;
                }

                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Pulling…');

                $.ajax({
                    url: '{{ route("tiktok.listing.variation.verify.pull") }}',
                    method: 'POST',
                    timeout: 0,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function (res) {
                        if (res.status === 200) {
                            $('#tiktok-lvv-status-line').text(res.message || 'Pull completed.');
                            tiktokLvvTable.setData('{{ route("tiktok.listing.variation.verify.data") }}');
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
