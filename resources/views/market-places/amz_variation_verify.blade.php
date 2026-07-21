@extends('layouts.vertical', ['title' => 'Amazon Ads Variation Verification'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #amz-vv-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
        }
        #amz-vv-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #amz-vv-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #amz-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #amz-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #amz-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important; display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #amz-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #amz-vv-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #amz-vv-wrap .tabulator .tabulator-row { min-height: 32px; }
        #amz-vv-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #amz-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }

        #amz-vv-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
        }
        #amz-vv-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #amz-vv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #amz-vv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #amz-vv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #amz-vv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #amz-vv-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        #amz-vv-wrap { overflow-x: auto; overflow-y: visible; }

        #amz-vv-wrap .tabulator-row.amz-vv-parent-row,
        #amz-vv-wrap .tabulator-row.amz-vv-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            color: #664d03;
        }
        #amz-vv-wrap .tabulator-row.amz-vv-parent-row:hover,
        #amz-vv-wrap .tabulator-row.amz-vv-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        #amz-vv-filter-bar {
            background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;
        }
        #amz-vv-filter-bar .amz-vv-filter-label {
            display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px; letter-spacing: 0.01em;
        }
        #amz-vv-filter-bar .amz-vv-filter-select {
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
        .amz-stat-badge--campaigns { background: #ea580c; }
        .amz-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .amz-raw-icon-btn > i { font-size: 14px; }

        .amz-vv-avail-yes { color: #16a34a; font-weight: 700; }
        .amz-vv-avail-no { color: #dc2626; font-weight: 700; }
        .amz-vv-avail-na { color: #94a3b8; }
        .amz-vv-avail-partial { color: #ea580c; font-weight: 700; }
        .amz-vv-match-ok {
            color: #16a34a; font-size: 16px; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .amz-vv-match-bad {
            color: #dc2626; font-size: 12px; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center; gap: 4px;
        }
        .amz-vv-match-na { color: #94a3b8; }
        .amz-vv-ad-added { color: #16a34a; font-weight: 700; }
        .amz-vv-ad-missing { color: #dc2626; font-weight: 700; }
        .amz-vv-ad-over { color: #ea580c; font-weight: 700; }
        .amz-vv-ad-na { color: #94a3b8; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amazon Ads Variation Verification',
        'sub_title'  => 'Amazon',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    {{-- Toolbar (same pattern as /amazon-ads/all) --}}
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
                            <span class="amz-stat-badge amz-stat-badge--parents" title="Parents from CP Master">PARENTS:<span id="amz-vv-badge-parents">0</span></span>
                            <span class="amz-stat-badge amz-stat-badge--children" title="Required child SKUs from CP Master">REQUIRED:<span id="amz-vv-badge-children">0</span></span>
                            <span class="amz-stat-badge amz-stat-badge--listed" title="Amazon listings cache">LISTED:<span id="amz-vv-badge-listed">0</span></span>
                            <span class="amz-stat-badge amz-stat-badge--campaigns" title="SP L30 campaigns">CAMPAIGNS:<span id="amz-vv-badge-campaigns">0</span></span>
                        </div>
                        <span id="amz-vv-total" class="badge bg-secondary">Total: —</span>
                        <span id="amz-vv-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="amz-vv-refresh-btn" class="btn btn-sm btn-outline-primary amz-raw-icon-btn" title="Refresh from CP Master" aria-label="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="amz-vv-pull-btn" class="btn btn-sm btn-warning text-dark" title="Pull Amazon listings (SP-API)">
                            <i class="fas fa-cloud-download-alt me-1"></i> Pull Listings
                        </button>
                        <span class="text-muted small" id="amz-vv-status-line"></span>
                    </div>

                    {{-- Search strip + table --}}
                    <div id="amz-vv-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="amz-vv-search" class="form-control" placeholder="Search Parent..." autocomplete="off" aria-label="Search Parent" maxlength="100">
                            <span id="amz-vv-source-label" class="badge bg-dark text-nowrap">CP Master</span>
                        </div>
                        <div id="amz-variation-verify-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        let amzVvTable = null;

        function amzVvEscapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function amzVvDash(val) {
            if (val === null || val === undefined || val === '') {
                return '<span class="text-muted">--</span>';
            }
            return val;
        }

        function amzVvUpdateMeta(meta) {
            if (!meta) return;
            const parents = meta.required_parent_count || 0;
            const children = meta.required_child_count || 0;
            const listed = meta.listings_count || 0;
            const campaigns = meta.ads_count || 0;

            $('#amz-vv-badge-parents').text(parents.toLocaleString());
            $('#amz-vv-badge-children').text(children.toLocaleString());
            $('#amz-vv-badge-listed').text(listed.toLocaleString());
            $('#amz-vv-badge-campaigns').text(campaigns.toLocaleString());

            const parts = [];
            if (meta.required_refreshed_at) parts.push('CP Master · ' + meta.required_refreshed_at);
            if (meta.last_pulled_at) parts.push('Listings · ' + meta.last_pulled_at);
            if (meta.ads_source) parts.push(meta.ads_source);
            $('#amz-vv-status-line').text(parts.join(' · '));
            $('#amz-vv-source-label').text(meta.has_listings_cache ? 'CP Master + Listings' : 'CP Master');
        }

        function amzVvUpdateRowCount() {
            if (!amzVvTable) return;
            const shown = amzVvTable.getDataCount('active');
            const total = amzVvTable.getDataCount();
            $('#amz-vv-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            try {
                const page = amzVvTable.getPage();
                const pages = amzVvTable.getPageMax();
                $('#amz-vv-page-info').text('Page: ' + page + ' / ' + pages);
            } catch (e) {
                $('#amz-vv-page-info').text('Page: —');
            }
        }

        function amzVvApplyFilters() {
            if (!amzVvTable) return;
            amzVvTable.clearFilter();

            const q = ($('#amz-vv-search').val() || '').trim().toLowerCase();
            if (q) {
                amzVvTable.addFilter(function (data) {
                    return String(data.parent || '').toLowerCase().includes(q)
                        || String(data.sku || '').toLowerCase().includes(q);
                });
            }

            amzVvUpdateRowCount();
        }

        function amzVvFormatAdSibling(type) {
            return function (cell) {
                const d = cell.getRow().getData();
                const status = d[type + '_ad_status'];
                const label = d[type + '_ad_label'] || '';
                const missing = parseInt(d[type + '_missing'], 10) || 0;

                if (status === null || status === undefined || !label || label === '—') {
                    return amzVvDash(null);
                }

                if (d.is_parent) {
                    if (status === 'ok') {
                        return `<span class="amz-vv-ad-added" title="${type.toUpperCase()} siblings OK"><i class="fas fa-check"></i> ${amzVvEscapeHtml(label)}</span>`;
                    }
                    if (status === 'over' && missing === 0) {
                        return `<span class="amz-vv-ad-over" title="${type.toUpperCase()}: in campaign but not listed">${amzVvEscapeHtml(label)}</span>`;
                    }
                    return `<span class="amz-vv-ad-missing" title="${type.toUpperCase()} missing / over">${amzVvEscapeHtml(label)}</span>`;
                }

                if (status === 'over') {
                    return '<span class="amz-vv-ad-over" title="In campaign but not in active listed records">Over</span>';
                }
                if (status === 'added') {
                    return '<span class="amz-vv-ad-added" title="Ads existing (in campaign + inv ≥ 0)">Added</span>';
                }
                if (status === 'missing') {
                    return '<span class="amz-vv-ad-missing" title="Eligible inv but no campaign">Missing</span>';
                }
                return amzVvDash(null);
            };
        }

        $(document).ready(function () {
            amzVvTable = new Tabulator('#amz-variation-verify-table', {
                ajaxURL: '{{ route("amz.variation.verify.data") }}',
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    if (response && response.meta) amzVvUpdateMeta(response.meta);
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
                    if (row.getData().is_parent === true) {
                        row.getElement().classList.add('amz-vv-parent-row');
                    }
                },
                columns: [
                    {
                        title: 'Parent',
                        field: 'parent',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 120,
                        widthGrow: 2,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            const v = cell.getValue() || '';
                            if (!v) return amzVvDash(null);
                            if (d.is_parent) return `<span class="fw-semibold">${amzVvEscapeHtml(v)}</span>`;
                            return `<span class="fw-semibold" style="color:#0d6efd;">${amzVvEscapeHtml(v)}</span>`;
                        }
                    },
                    {
                        title: 'SKU',
                        field: 'sku',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 140,
                        widthGrow: 2,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            const v = cell.getValue() || '';
                            if (!v) return amzVvDash(null);
                            return d.is_parent
                                ? `<span class="fw-semibold">${amzVvEscapeHtml(v)}</span>`
                                : amzVvEscapeHtml(v);
                        }
                    },
                    {
                        title: 'Parent Vs Ads SKU KW',
                        field: 'kw_ad_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 130,
                        widthGrow: 1,
                        formatter: amzVvFormatAdSibling('kw')
                    },
                    {
                        title: 'Parent Vs Ads SKU PT',
                        field: 'pt_ad_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 130,
                        widthGrow: 1,
                        formatter: amzVvFormatAdSibling('pt')
                    }
                ]
            });

            amzVvTable.on('dataProcessed', amzVvApplyFilters);
            amzVvTable.on('dataFiltered', amzVvUpdateRowCount);
            amzVvTable.on('pageLoaded', amzVvUpdateRowCount);

            let searchTimer = null;
            $('#amz-vv-search').on('keyup search', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(amzVvApplyFilters, 200);
            });

            $('#amz-vv-refresh-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                amzVvTable.setData('{{ route("amz.variation.verify.data") }}')
                    .finally(function () { $btn.prop('disabled', false); });
            });

            $('#amz-vv-pull-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;

                if (!confirm('Pull all merchant listings from Amazon SP-API?\n\nThis uses GET_MERCHANT_LISTINGS_ALL_DATA and may take several minutes.')) {
                    return;
                }

                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Pulling…');
                $('#amz-vv-status-line').text('Requesting listings report from Amazon…');

                $.ajax({
                    url: '{{ route("amz.variation.verify.pull") }}',
                    method: 'POST',
                    timeout: 0,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function (res) {
                        if (res.status === 200) {
                            $('#amz-vv-status-line').text(res.message || 'Pull completed.');
                            amzVvTable.setData('{{ route("amz.variation.verify.data") }}');
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
