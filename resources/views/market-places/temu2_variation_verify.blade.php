@extends('layouts.vertical', ['title' => 'Temu 2 Ads Variation Verification'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #temu2-vv-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
        }
        #temu2-vv-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #temu2-vv-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #temu2-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #temu2-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #temu2-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important; display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #temu2-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #temu2-vv-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #temu2-vv-wrap .tabulator .tabulator-row { min-height: 32px; }
        #temu2-vv-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #temu2-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }

        #temu2-vv-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
        }
        #temu2-vv-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #temu2-vv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #temu2-vv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #temu2-vv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #temu2-vv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #temu2-vv-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        #temu2-vv-wrap { overflow-x: auto; overflow-y: visible; }

        #temu2-vv-wrap .tabulator-row.temu2-vv-parent-row,
        #temu2-vv-wrap .tabulator-row.temu2-vv-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            color: #664d03;
        }
        #temu2-vv-wrap .tabulator-row.temu2-vv-parent-row:hover,
        #temu2-vv-wrap .tabulator-row.temu2-vv-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        .temu2-stat-badge {
            display: inline-flex; align-items: center; flex-shrink: 0; color: #fff; font-size: 15px; font-weight: 700;
            padding: 9px 16px; border-radius: 8px; white-space: nowrap; line-height: 1.25; letter-spacing: 0.2px;
        }
        .temu2-stat-badge > span { margin-left: 4px; font-size: 16px; font-weight: 800; }
        .temu2-stat-badge--parents { background: #4c7ed8; }
        .temu2-stat-badge--children { background: #8b5cf6; }
        .temu2-stat-badge--listed { background: #16a34a; }
        .temu2-stat-badge--campaigns { background: #ea580c; }
        .temu2-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .temu2-raw-icon-btn > i { font-size: 14px; }

        .temu2-vv-ad-added { color: #16a34a; font-weight: 700; }
        .temu2-vv-ad-missing { color: #dc2626; font-weight: 700; }
        .temu2-vv-ad-over { color: #ea580c; font-weight: 700; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Temu 2 Ads Variation Verification',
        'sub_title'  => 'Temu 2',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
                            <span class="temu2-stat-badge temu2-stat-badge--parents" title="Parents from CP Master">PARENTS:<span id="temu2-vv-badge-parents">0</span></span>
                            <span class="temu2-stat-badge temu2-stat-badge--children" title="Required child SKUs from CP Master">REQUIRED:<span id="temu2-vv-badge-children">0</span></span>
                            <span class="temu2-stat-badge temu2-stat-badge--listed" title="Temu 2 listings (temu2_pricing)">LISTED:<span id="temu2-vv-badge-listed">0</span></span>
                            <span class="temu2-stat-badge temu2-stat-badge--campaigns" title="Temu 2 L30 campaign report rows">CAMPAIGNS:<span id="temu2-vv-badge-campaigns">0</span></span>
                        </div>
                        <span id="temu2-vv-total" class="badge bg-secondary">Total: —</span>
                        <span id="temu2-vv-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="temu2-vv-refresh-btn" class="btn btn-sm btn-outline-primary temu2-raw-icon-btn" title="Refresh from CP Master" aria-label="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="temu2-vv-pull-btn" class="btn btn-sm btn-warning text-dark" title="Refresh Temu 2 listings from temu2_pricing">
                            <i class="fas fa-cloud-download-alt me-1"></i> Pull Listings
                        </button>
                        <span class="text-muted small" id="temu2-vv-status-line"></span>
                    </div>

                    <div id="temu2-vv-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="temu2-vv-search" class="form-control" placeholder="Search Parent..." autocomplete="off" aria-label="Search Parent" maxlength="100">
                            <span id="temu2-vv-source-label" class="badge bg-dark text-nowrap">CP Master</span>
                        </div>
                        <div id="temu2-variation-verify-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        let temu2VvTable = null;

        function temu2VvEscapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function temu2VvDash(val) {
            if (val === null || val === undefined || val === '') {
                return '<span class="text-muted">--</span>';
            }
            return val;
        }

        function temu2VvUpdateMeta(meta) {
            if (!meta) return;
            const parents = meta.required_parent_count || 0;
            const children = meta.required_child_count || 0;
            const listed = meta.listings_count || 0;
            const campaigns = meta.ads_count || 0;

            $('#temu2-vv-badge-parents').text(parents.toLocaleString());
            $('#temu2-vv-badge-children').text(children.toLocaleString());
            $('#temu2-vv-badge-listed').text(listed.toLocaleString());
            $('#temu2-vv-badge-campaigns').text(campaigns.toLocaleString());

            const parts = [];
            if (meta.required_refreshed_at) parts.push('CP Master · ' + meta.required_refreshed_at);
            if (meta.last_pulled_at) parts.push('Listings · ' + meta.last_pulled_at);
            if (meta.ads_source) parts.push(meta.ads_source);
            $('#temu2-vv-status-line').text(parts.join(' · '));
            $('#temu2-vv-source-label').text(meta.has_listings_cache ? 'CP Master + Listings' : 'CP Master');
        }

        function temu2VvUpdateRowCount() {
            if (!temu2VvTable) return;
            const shown = temu2VvTable.getDataCount('active');
            const total = temu2VvTable.getDataCount();
            $('#temu2-vv-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            try {
                const page = temu2VvTable.getPage();
                const pages = temu2VvTable.getPageMax();
                $('#temu2-vv-page-info').text('Page: ' + page + ' / ' + pages);
            } catch (e) {
                $('#temu2-vv-page-info').text('Page: —');
            }
        }

        function temu2VvApplyFilters() {
            if (!temu2VvTable) return;
            temu2VvTable.clearFilter();

            const q = ($('#temu2-vv-search').val() || '').trim().toLowerCase();
            if (q) {
                temu2VvTable.addFilter(function (data) {
                    return String(data.parent || '').toLowerCase().includes(q)
                        || String(data.sku || '').toLowerCase().includes(q);
                });
            }

            temu2VvUpdateRowCount();
        }

        function temu2VvFormatAdSibling(cell) {
            const d = cell.getRow().getData();
            const status = d.ad_ad_status;
            const label = d.ad_ad_label || '';
            const missing = parseInt(d.ad_missing, 10) || 0;

            if (status === null || status === undefined || !label || label === '—') {
                return temu2VvDash(null);
            }

            if (d.is_parent) {
                if (status === 'ok') {
                    return `<span class="temu2-vv-ad-added" title="Ads siblings OK"><i class="fas fa-check"></i> ${temu2VvEscapeHtml(label)}</span>`;
                }
                if (status === 'over' && missing === 0) {
                    return `<span class="temu2-vv-ad-over" title="In campaign but not listed">${temu2VvEscapeHtml(label)}</span>`;
                }
                return `<span class="temu2-vv-ad-missing" title="Ads missing / over">${temu2VvEscapeHtml(label)}</span>`;
            }

            if (status === 'over') {
                return '<span class="temu2-vv-ad-over" title="In campaign but not in temu2_pricing">Over</span>';
            }
            if (status === 'added') {
                return '<span class="temu2-vv-ad-added" title="Ads existing (in campaign + inv ≥ 0)">Added</span>';
            }
            if (status === 'missing') {
                return '<span class="temu2-vv-ad-missing" title="Eligible inv but no campaign">Missing</span>';
            }
            return temu2VvDash(null);
        }

        $(document).ready(function () {
            temu2VvTable = new Tabulator('#temu2-variation-verify-table', {
                ajaxURL: '{{ route("temu2.variation.verify.data") }}',
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    if (response && response.meta) temu2VvUpdateMeta(response.meta);
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
                        row.getElement().classList.add('temu2-vv-parent-row');
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
                            if (!v) return temu2VvDash(null);
                            if (d.is_parent) return `<span class="fw-semibold">${temu2VvEscapeHtml(v)}</span>`;
                            return `<span class="fw-semibold" style="color:#0d6efd;">${temu2VvEscapeHtml(v)}</span>`;
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
                            if (!v) return temu2VvDash(null);
                            return d.is_parent
                                ? `<span class="fw-semibold">${temu2VvEscapeHtml(v)}</span>`
                                : temu2VvEscapeHtml(v);
                        }
                    },
                    {
                        title: 'Parent Vs Ads SKU',
                        field: 'ad_ad_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 150,
                        widthGrow: 1,
                        formatter: temu2VvFormatAdSibling
                    }
                ]
            });

            temu2VvTable.on('dataProcessed', temu2VvApplyFilters);
            temu2VvTable.on('dataFiltered', temu2VvUpdateRowCount);
            temu2VvTable.on('pageLoaded', temu2VvUpdateRowCount);

            let searchTimer = null;
            $('#temu2-vv-search').on('keyup search', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(temu2VvApplyFilters, 200);
            });

            $('#temu2-vv-refresh-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                temu2VvTable.setData('{{ route("temu2.variation.verify.data") }}')
                    .finally(function () { $btn.prop('disabled', false); });
            });

            $('#temu2-vv-pull-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;

                if (!confirm('Refresh Temu 2 listings from temu2_pricing?\n\nUpload pricing on Temu 2 Analytics if the cache is empty.')) {
                    return;
                }

                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Pulling…');
                $('#temu2-vv-status-line').text('Refreshing listings from temu2_pricing…');

                $.ajax({
                    url: '{{ route("temu2.variation.verify.pull") }}',
                    method: 'POST',
                    timeout: 0,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function (res) {
                        if (res.status === 200) {
                            $('#temu2-vv-status-line').text(res.message || 'Pull completed.');
                            temu2VvTable.setData('{{ route("temu2.variation.verify.data") }}');
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
