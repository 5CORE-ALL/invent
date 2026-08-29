@extends('layouts.vertical', ['title' => 'Amazon 0 Views Diagnostic', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #amz-zvd-wrap {
            display: flex;
            flex-direction: column;
            min-height: 280px;
            overflow: hidden;
        }
        #amz-zvd-table {
            flex: 1 1 auto;
            min-height: 0;
            width: 100%;
        }
        #amz-zvd-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 12px;
            height: 100% !important;
        }
        #amz-zvd-wrap .tabulator .tabulator-header { background: #f8f9fa; }
        #amz-zvd-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 2px;
        }
        #amz-zvd-wrap .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }
        #amz-zvd-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="row_select"] .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            height: auto !important;
        }
        #amz-zvd-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #amz-zvd-wrap .tabulator .tabulator-cell { padding: 4px 6px !important; }
        #amz-zvd-wrap .tabulator-row.tabulator-selected { background: #e7f1ff !important; }
        .amz-zvd-thumb { width: 40px; height: 40px; object-fit: contain; border-radius: 4px; background: #fff; cursor: zoom-in; }
        .amz-zvd-card { cursor: pointer; border: 1px solid #e5e7eb; transition: box-shadow .15s, border-color .15s; }
        .amz-zvd-card:hover, .amz-zvd-card.active { border-color: #0d6efd; box-shadow: 0 0 0 2px rgba(13,110,253,.15); }
        .amz-zvd-card .amz-zvd-card-value { font-size: 1.35rem; font-weight: 700; line-height: 1.1; }
        .amz-zvd-card .amz-zvd-card-label { font-size: 11px; color: #6c757d; }
        .amz-zvd-badge-green { background: #16a34a; color: #fff; }
        .amz-zvd-badge-red { background: #dc2626; color: #fff; }
        .amz-zvd-badge-orange { background: #fd7e14; color: #fff; }
        .amz-zvd-badge-gray { background: #6c757d; color: #fff; }
        .amz-zvd-check-pass { color: #16a34a; }
        .amz-zvd-check-fail { color: #dc2626; }
        .amz-zvd-check-warn { color: #fd7e14; }
        .amz-zvd-check-na { color: #6c757d; }
        .amz-zvd-subtitle { color: #6c757d; max-width: 920px; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amazon 0 Views Diagnostic',
        'sub_title'  => 'Amz FBM',
    ])

    <p class="amz-zvd-subtitle mb-3">
        Identify Amazon products receiving zero page views and determine whether the issue is listing,
        inventory, pricing, indexing, suppression, buyability, or ranking related.
    </p>

    <div class="row g-2 mb-3" id="amz-zvd-cards">
        @foreach ([
            ['key' => 'total', 'label' => 'Total Products Checked', 'class' => 'bg-light'],
            ['key' => 'zero_views', 'label' => '0 Views Products', 'class' => ''],
            ['key' => 'blocked', 'label' => 'Blocked', 'class' => ''],
            ['key' => 'suppressed', 'label' => 'Suppressed', 'class' => ''],
            ['key' => 'out_of_stock', 'label' => 'Out of Stock', 'class' => ''],
            ['key' => 'not_buyable', 'label' => 'Not Buyable', 'class' => ''],
            ['key' => 'indexing', 'label' => 'Indexing Issues', 'class' => ''],
            ['key' => 'listing', 'label' => 'Listing Issues', 'class' => ''],
            ['key' => 'needs_review', 'label' => 'Needs Review', 'class' => ''],
            ['key' => 'healthy', 'label' => 'Healthy', 'class' => ''],
        ] as $card)
            <div class="col-6 col-md-4 col-xl">
                <div class="card amz-zvd-card h-100 mb-0 {{ $card['class'] }}" data-card="{{ $card['key'] }}">
                    <div class="card-body py-2 px-2">
                        <div class="amz-zvd-card-value" id="amz-zvd-card-{{ $card['key'] }}">—</div>
                        <div class="amz-zvd-card-label">{{ $card['label'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <form id="amz-zvd-filters" class="row g-2 align-items-end">
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1">Marketplace</label>
                    <select name="marketplace" class="form-select form-select-sm">
                        @foreach(($filterOptions['marketplaces'] ?? []) as $opt)
                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1">Account</label>
                    <select name="account" class="form-select form-select-sm">
                        @foreach(($filterOptions['accounts'] ?? []) as $opt)
                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1">SKU</label>
                    <input type="text" name="sku" class="form-control form-control-sm" placeholder="SKU">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1">ASIN</label>
                    <input type="text" name="asin" class="form-control form-control-sm" placeholder="ASIN">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1">Brand</label>
                    <input list="amz-zvd-brands" name="brand" class="form-control form-control-sm" placeholder="Brand">
                    <datalist id="amz-zvd-brands">
                        @foreach(($filterOptions['brands'] ?? []) as $brand)
                            <option value="{{ $brand }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1">Category</label>
                    <input list="amz-zvd-categories" name="category" class="form-control form-control-sm" placeholder="Category">
                    <datalist id="amz-zvd-categories">
                        @foreach(($filterOptions['categories'] ?? []) as $cat)
                            <option value="{{ $cat }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach(($filterOptions['statuses'] ?? []) as $st)
                            <option value="{{ $st }}">{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1">Diagnostic Result</label>
                    <select name="diagnostic_result" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach(($filterOptions['diagnostic_results'] ?? []) as $dr)
                            <option value="{{ $dr }}">{{ $dr }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1">L30 Views</label>
                    <select name="l30_views" class="form-select form-select-sm">
                        <option value="all" selected>All SKUs</option>
                        <option value="0">L30 Views = 0</option>
                        <option value="gt0">L30 Views &gt; 0</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-xl-4 d-flex flex-wrap gap-2 align-items-center">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="amz-zvd-zero-only">
                        <label class="form-check-label small" for="amz-zvd-zero-only">
                            Show Only Products With 0 L30 Views
                        </label>
                    </div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-sm btn-primary" id="amz-zvd-search-btn">
                        <i class="fa fa-search me-1"></i> Search
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="amz-zvd-reset-btn">Reset</button>
                    <button type="button" class="btn btn-sm btn-warning text-dark" id="amz-zvd-run-btn">
                        <i class="fas fa-play me-1"></i> Run Diagnostic
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" id="amz-zvd-export-btn">
                        <i class="fa fa-download me-1"></i> Export
                    </button>
                    <span class="badge bg-secondary" id="amz-zvd-run-badge">Idle</span>
                    <span class="text-muted small" id="amz-zvd-status-line">Uses synced Amazon data · no live API on page load</span>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div id="amz-zvd-wrap">
                <div id="amz-zvd-table"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amz-zvd-detail-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0" id="amz-zvd-detail-title">Product Diagnostic</h5>
                        <div class="small text-muted" id="amz-zvd-detail-sub"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="amz-zvd-detail-body"></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        const AMZ_ZVD_DATA_URL = @json(route('amz.zero.views.diagnostic.data'));
        const AMZ_ZVD_DETAIL_URL = @json(route('amz.zero.views.diagnostic.detail'));
        const AMZ_ZVD_RUN_URL = @json(route('amz.zero.views.diagnostic.run'));
        const AMZ_ZVD_RUN_STATUS_URL = @json(route('amz.zero.views.diagnostic.run.status'));
        const AMZ_ZVD_EXPORT_URL = @json(route('amz.zero.views.diagnostic.export'));
        const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        let amzZvdTable = null;
        let activeCard = 'total';
        let pollTimer = null;

        function amzZvdEsc(s) {
            return String(s ?? '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function amzZvdFilters() {
            const form = document.getElementById('amz-zvd-filters');
            const data = new FormData(form);
            const out = {};
            data.forEach((v, k) => { out[k] = v; });
            out.zero_only = document.getElementById('amz-zvd-zero-only').checked ? '1' : '0';
            if (activeCard && activeCard !== 'total') out.card = activeCard;
            return out;
        }

        function amzZvdQuery(extra) {
            const q = new URLSearchParams(amzZvdFilters());
            Object.entries(extra || {}).forEach(([k, v]) => q.set(k, v));
            return q.toString();
        }

        function amzZvdBadge(status, color) {
            const cls = color === 'green' ? 'amz-zvd-badge-green'
                : color === 'red' ? 'amz-zvd-badge-red'
                : color === 'orange' ? 'amz-zvd-badge-orange'
                : 'amz-zvd-badge-gray';
            return '<span class="badge ' + cls + '">' + amzZvdEsc(status || '—') + '</span>';
        }

        function amzZvdUpdateSummary(summary) {
            if (!summary) return;
            Object.keys(summary).forEach(key => {
                const el = document.getElementById('amz-zvd-card-' + key);
                if (el) el.textContent = Number(summary[key] || 0).toLocaleString();
            });
        }

        function amzZvdUpdateRun(status) {
            if (!status) return;
            const badge = document.getElementById('amz-zvd-run-badge');
            const line = document.getElementById('amz-zvd-status-line');
            const label = status.status || (status.running ? 'Running' : 'Idle');
            badge.textContent = label;
            badge.className = 'badge ' + (
                label === 'Completed' ? 'bg-success'
                : label === 'Failed' ? 'bg-danger'
                : (label === 'Running' || label === 'Queued') ? 'bg-warning text-dark'
                : 'bg-secondary'
            );
            if (line) {
                line.textContent = status.message || '';
            }
            if (status.running) startRunPoll();
        }

        function startRunPoll() {
            if (pollTimer) return;
            pollTimer = setInterval(() => {
                fetch(AMZ_ZVD_RUN_STATUS_URL, { headers: { 'X-CSRF-TOKEN': CSRF } })
                    .then(r => r.json())
                    .then(res => {
                        amzZvdUpdateRun(res.status || {});
                        if (!(res.status && res.status.running)) {
                            clearInterval(pollTimer);
                            pollTimer = null;
                            if (amzZvdTable) amzZvdTable.replaceData();
                        }
                    })
                    .catch(() => {});
            }, 2500);
        }

        function openDiagnostic(sku, asin) {
            const url = AMZ_ZVD_DETAIL_URL + '?' + new URLSearchParams({
                sku: sku || '',
                asin: asin || '',
            }).toString();
            fetch(url, { headers: { 'X-CSRF-TOKEN': CSRF } })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        alert(res.message || 'Unavailable');
                        return;
                    }
                    renderDetail(res.data || {});
                    const modal = new bootstrap.Modal(document.getElementById('amz-zvd-detail-modal'));
                    modal.show();
                })
                .catch(() => alert('Unavailable'));
        }

        function renderDetail(row) {
            document.getElementById('amz-zvd-detail-title').textContent = row.product_name || row.sku || 'Diagnostic';
            document.getElementById('amz-zvd-detail-sub').textContent =
                'ASIN ' + (row.asin || '—') + ' · SKU ' + (row.sku || '—') + ' · ' + (row.marketplace || '');

            const checks = (row.checkpoints || []).map(c => {
                const icon = c.status === 'pass' ? '✓' : c.status === 'fail' ? '✕' : c.status === 'warn' ? '⚠' : '–';
                const cls = 'amz-zvd-check-' + (c.status || 'na');
                return '<tr>' +
                    '<td class="' + cls + ' fw-bold">' + icon + ' ' + amzZvdEsc(c.label) + '</td>' +
                    '<td>' + amzZvdEsc(c.status) + '</td>' +
                    '<td>' + amzZvdEsc(c.value) + '</td>' +
                    '<td class="small text-muted">' + amzZvdEsc(c.source) + '</td>' +
                    '<td class="small">' + amzZvdEsc(c.last_checked || '—') + '</td>' +
                    '<td class="small">' + amzZvdEsc(c.explanation) + '</td>' +
                    '</tr>';
            }).join('');

            document.getElementById('amz-zvd-detail-body').innerHTML =
                '<div class="mb-3">' + amzZvdBadge(row.diagnostic_status, row.color) +
                ' <span class="ms-2">' + amzZvdEsc(row.problem || '') + '</span></div>' +
                '<p class="small text-muted">' + amzZvdEsc(row.recommended_action || '') + '</p>' +
                '<div class="row g-3 mb-3">' +
                    section('TRAFFIC', [
                        ['L7 Views', row.l7_views],
                        ['L30 Views', row.l30_views],
                        ['L7 Sessions', row.l7_sessions],
                        ['L30 Sessions', row.l30_sessions],
                    ], row.traffic_note) +
                    section('LISTING', [
                        ['Listing Status', row.listing_status],
                        ['Suppression', row.suppression],
                        ['Category', row.category],
                        ['Browse Node', row.browse_node],
                        ['Title', row.product_name],
                        ['Main Image', row.main_image_status],
                    ]) +
                    section('SELLING', [
                        ['Price', row.price != null ? ('$' + row.price) : '—'],
                        ['Inventory', row.inventory],
                        ['Buyable', row.buyable],
                        ['Fulfillment', row.fulfillment],
                        ['Featured Offer %', row.featured_offer_percentage],
                    ]) +
                    section('SEARCH', [
                        ['Search Indexed', row.search_indexed],
                        ['Indexing Verification Status', 'Not Verified'],
                        ['Relevant Search Attributes', 'Not Available via Current API'],
                    ]) +
                '</div>' +
                '<h6>DIAGNOSTIC</h6>' +
                '<div class="table-responsive"><table class="table table-sm table-bordered">' +
                '<thead><tr><th>Checkpoint</th><th>STATUS</th><th>VALUE</th><th>SOURCE</th><th>LAST CHECKED</th><th>EXPLANATION</th></tr></thead>' +
                '<tbody>' + checks + '</tbody></table></div>' +
                '<div class="mt-2"><button type="button" class="btn btn-sm btn-warning text-dark" id="amz-zvd-run-again">' +
                '<i class="fas fa-redo me-1"></i> Run Again</button></div>';

            const again = document.getElementById('amz-zvd-run-again');
            if (again) {
                again.onclick = () => runDiagnostic({ mode: 'single', sku: row.sku, asin: row.asin });
            }
        }

        function section(title, rows, note) {
            const body = rows.map(([k, v]) =>
                '<div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">' +
                amzZvdEsc(k) + '</span><span>' + amzZvdEsc(v ?? '—') + '</span></div>'
            ).join('');
            return '<div class="col-md-6 col-xl-3"><div class="border rounded p-2 h-100"><div class="fw-semibold mb-2">' +
                amzZvdEsc(title) + '</div>' + body +
                (note ? '<div class="small text-muted mt-2">' + amzZvdEsc(note) + '</div>' : '') +
                '</div></div>';
        }

        function runDiagnostic(payload) {
            fetch(AMZ_ZVD_RUN_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            })
                .then(r => r.json())
                .then(res => {
                    amzZvdUpdateRun(res.status || { status: 'Queued', running: true, message: res.message });
                    startRunPoll();
                })
                .catch(() => alert('Retry Required'));
        }

        function amzZvdFillHeight() {
            const wrap = document.getElementById('amz-zvd-wrap');
            if (!wrap) return;
            const top = wrap.getBoundingClientRect().top;
            const h = Math.max(280, window.innerHeight - top - 16);
            wrap.style.height = h + 'px';
            if (amzZvdTable) amzZvdTable.redraw();
        }

        function buildTable() {
            amzZvdTable = new Tabulator('#amz-zvd-table', {
                ajaxURL: AMZ_ZVD_DATA_URL,
                ajaxConfig: 'GET',
                ajaxURLGenerator: function (url, config, params) {
                    return url + '?' + amzZvdQuery({
                        page: params.page || 1,
                        size: params.size || 50,
                    });
                },
                ajaxResponse: function (url, params, response) {
                    amzZvdUpdateSummary(response.summary || {});
                    amzZvdUpdateRun(response.run || {});
                    return {
                        data: response.data || [],
                        last_page: response.last_page || 1,
                    };
                },
                pagination: true,
                paginationMode: 'remote',
                filterMode: 'remote',
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200],
                layout: 'fitDataFill',
                height: '100%',
                placeholder: 'No SKUs match the current filters',
                selectableRows: true,
                columns: [
                    { formatter: 'rowSelection', titleFormatter: 'rowSelection', hozAlign: 'center', headerSort: false, width: 40, frozen: true, field: 'row_select' },
                    { title: 'Image', field: 'image_path', hozAlign: 'center', headerSort: false, width: 70, frozen: true,
                      formatter: c => {
                          const url = c.getValue() || c.getRow().getData().main_image;
                          if (!url) return '';
                          return '<img class="amz-zvd-thumb hover-thumb" src="' + amzZvdEsc(url) + '" alt="">';
                      } },
                    { title: 'Parent', field: 'Parent', width: 110, frozen: true, cssClass: 'text-primary',
                      formatter: c => amzZvdEsc(c.getValue() || c.getRow().getData().parent || '—') },
                    { title: 'SKU', field: '(Child) sku', width: 160, frozen: true,
                      formatter: c => {
                          const sku = c.getValue() || c.getRow().getData().sku || '';
                          return '<div style="display:flex;align-items:center;gap:5px;">'
                              + '<span>' + amzZvdEsc(sku) + '</span>'
                              + '<button type="button" class="btn btn-sm btn-link copy-sku-btn p-0" data-sku="'
                              + amzZvdEsc(sku) + '" title="Copy SKU"><i class="fas fa-copy"></i></button></div>';
                      } },
                    { title: 'INV', field: 'INV', hozAlign: 'center', sorter: 'number', width: 55,
                      headerTooltip: 'Shopify inventory',
                      formatter: c => {
                          const num = Math.round(parseFloat(c.getValue() != null ? c.getValue() : c.getRow().getData().inventory) || 0);
                          return '<span style="font-weight:600;">' + num.toLocaleString('en-US') + '</span>';
                      } },
                    { title: 'INV AMZ', field: 'INV_AMZ', hozAlign: 'center', sorter: 'number', width: 65,
                      headerTooltip: 'Amazon inventory',
                      formatter: c => {
                          const value = parseFloat(c.getValue()) || 0;
                          const shopifyInv = parseFloat(c.getRow().getData().INV) || 0;
                          const difference = Math.abs(value - shopifyInv);
                          const tol = shopifyInv * 0.03;
                          let color = '#28a745';
                          if (difference > Math.max(tol, 3)) color = '#dc3545';
                          else if (difference > 0) color = '#ffc107';
                          return '<span style="color:' + color + ';font-weight:600;">' + Math.round(value).toLocaleString('en-US') + '</span>';
                      } },
                    { title: 'OV L30', field: 'L30', hozAlign: 'center', sorter: 'number', width: 65,
                      headerTooltip: 'Overall sold L30 (Shopify quantity)',
                      formatter: c => Math.round(parseFloat(c.getValue()) || 0).toLocaleString('en-US') },
                    { title: 'Dil', field: 'E Dil%', hozAlign: 'center', sorter: 'number', width: 55,
                      headerTooltip: 'Dil% = OV L30 / INV × 100',
                      formatter: c => {
                          const row = c.getRow().getData();
                          const inv = parseFloat(row.INV != null ? row.INV : row.inventory) || 0;
                          const ovL30 = parseFloat(row.L30) || 0;
                          if (inv === 0) return '<span style="color:#6c757d;">0%</span>';
                          const dil = (ovL30 / inv) * 100;
                          let color = '#e83e8c';
                          if (dil < 16.66) color = '#a00211';
                          else if (dil < 25) color = '#ffc107';
                          else if (dil < 50) color = '#28a745';
                          return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                      } },
                    { title: 'A L30', field: 'A_L30', hozAlign: 'center', sorter: 'number', width: 60,
                      headerTooltip: 'Amz units ordered L30 (A L30)',
                      formatter: c => Math.round(parseFloat(c.getValue()) || 0).toLocaleString('en-US') },
                    { title: 'View L30', field: 'Sess30', hozAlign: 'center', sorter: 'number', width: 65,
                      headerTooltip: 'Amz sessions L30 (View L30)',
                      formatter: c => Math.round((c.getValue() != null ? c.getValue() : c.getRow().getData().l30_views) || 0).toLocaleString('en-US') },
                    { title: 'View L7', field: 'Sess7', hozAlign: 'center', sorter: 'number', width: 65,
                      headerTooltip: 'Amz sessions L7 (View L7) — red when < 70',
                      formatter: c => {
                          const num = Math.round((c.getValue() != null ? c.getValue() : c.getRow().getData().l7_views) || 0);
                          const text = num.toLocaleString('en-US');
                          if (num < 70) return '<span style="color:#a00211;font-weight:600;">' + text + '</span>';
                          return text;
                      } },
                    { title: 'CVR L30', field: 'CVR_L30', hozAlign: 'center', width: 70,
                      headerTooltip: 'CVR L30 = A L30 / View L30 × 100',
                      formatter: c => {
                          const row = c.getRow().getData();
                          const aL30 = parseFloat(row.A_L30) || 0;
                          const sess30 = parseFloat(row.Sess30 != null ? row.Sess30 : row.l30_views) || 0;
                          if (sess30 === 0) return '<span style="color:#a00211;font-weight:600;">0.0%</span>';
                          const cvr = (aL30 / sess30) * 100;
                          let color = '#e83e8c';
                          if (cvr <= 4) color = '#a00211';
                          else if (cvr <= 7) color = '#ffc107';
                          else if (cvr <= 13) color = '#28a745';
                          return '<span style="color:' + color + ';font-weight:600;">' + Math.round(cvr) + '%</span>';
                      } },
                    { title: 'Price', field: 'price', hozAlign: 'center', sorter: 'number', width: 70,
                      headerTooltip: 'Amz listing price (amazon_datsheets.price)',
                      formatter: c => {
                          const row = c.getRow().getData();
                          const price = parseFloat(c.getValue() != null ? c.getValue() : (row.std_price || 0));
                          const lmpPrice = parseFloat(row.lmp_price || 0);
                          if (price <= 0) {
                              return lmpPrice > 0
                                  ? '<span style="color:#6c757d;font-style:italic;" title="Reference LMP">$' + lmpPrice.toFixed(2) + '</span>'
                                  : '<span style="color:#999;">—</span>';
                          }
                          const formatted = '$' + price.toFixed(2);
                          if (lmpPrice > 0 && price > lmpPrice) {
                              return '<span style="color:#dc3545;font-weight:600;">' + formatted + '</span>';
                          }
                          return formatted;
                      } },
                    { title: 'LMP', field: 'lmp_price', hozAlign: 'center', sorter: 'number', width: 75,
                      headerTooltip: 'Lowest marketplace price',
                      formatter: c => {
                          const row = c.getRow().getData();
                          const lmpPrice = parseFloat(c.getValue());
                          const currentPrice = parseFloat(row.price != null ? row.price : (row.std_price || 0));
                          if (!(lmpPrice > 0)) return '<span style="color:#999;">N/A</span>';
                          const priceColor = (currentPrice > 0 && lmpPrice < currentPrice) ? '#dc3545' : '#28a745';
                          return '<span style="color:' + priceColor + ';font-weight:600;">$' + lmpPrice.toFixed(2) + '</span>';
                      } },
                    { title: 'GROI%', field: 'GROI%', hozAlign: 'center', sorter: 'number', width: 65,
                      headerTooltip: 'GROI% = ((Price × 0.80 − Ship − LP) / LP) × 100',
                      formatter: c => {
                          const percent = parseFloat(c.getValue());
                          if (percent === null || percent === undefined || isNaN(percent)) return '0%';
                          let color = '#e83e8c';
                          if (percent < 50) color = '#a00211';
                          else if (percent < 75) color = '#ffc107';
                          else if (percent <= 125) color = '#28a745';
                          return '<span style="color:' + color + ';font-weight:600;">' + percent.toFixed(0) + '%</span>';
                      } },
                    { title: 'Status', field: 'diagnostic_status', width: 120, hozAlign: 'center',
                      formatter: c => amzZvdBadge(c.getValue(), c.getRow().getData().color) },
                    { title: 'Problem', field: 'problem', width: 200, formatter: c => amzZvdEsc(c.getValue() || '') },
                    { title: 'Recommended Action', field: 'recommended_action', width: 220, formatter: c => amzZvdEsc(c.getValue() || '') },
                    { title: 'Actions', field: 'sku', width: 150, headerSort: false,
                      formatter: c => {
                          const d = c.getRow().getData();
                          return '<button type="button" class="btn btn-sm btn-outline-primary amz-zvd-view" data-sku="' +
                              amzZvdEsc(d.sku) + '" data-asin="' + amzZvdEsc(d.asin || '') +
                              '">View</button> ' +
                              '<button type="button" class="btn btn-sm btn-outline-warning amz-zvd-rerun" data-sku="' +
                              amzZvdEsc(d.sku) + '">Run</button>';
                      } },
                ],
            });

            document.getElementById('amz-zvd-table').addEventListener('click', function (e) {
                const copyBtn = e.target.closest('.copy-sku-btn');
                if (copyBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    const sku = copyBtn.dataset.sku || '';
                    if (sku && navigator.clipboard) {
                        navigator.clipboard.writeText(sku);
                    }
                    return;
                }
                const view = e.target.closest('.amz-zvd-view, .amz-zvd-asin');
                if (view) {
                    e.preventDefault();
                    openDiagnostic(view.dataset.sku, view.dataset.asin);
                    return;
                }
                const rerun = e.target.closest('.amz-zvd-rerun');
                if (rerun) {
                    e.preventDefault();
                    runDiagnostic({ mode: 'single', sku: rerun.dataset.sku });
                }
            });
        }

        document.getElementById('amz-zvd-filters').addEventListener('submit', function (e) {
            e.preventDefault();
            if (amzZvdTable) amzZvdTable.setPage(1);
        });

        document.getElementById('amz-zvd-reset-btn').addEventListener('click', function () {
            document.getElementById('amz-zvd-filters').reset();
            document.getElementById('amz-zvd-zero-only').checked = false;
            document.querySelector('#amz-zvd-filters [name="l30_views"]').value = 'all';
            activeCard = 'total';
            document.querySelectorAll('.amz-zvd-card').forEach(el => el.classList.toggle('active', el.dataset.card === activeCard));
            if (amzZvdTable) amzZvdTable.setPage(1);
        });

        document.getElementById('amz-zvd-zero-only').addEventListener('change', function () {
            const l30 = document.querySelector('#amz-zvd-filters [name="l30_views"]');
            if (this.checked) {
                l30.value = '0';
                activeCard = 'zero_views';
            } else if (l30.value === '0') {
                l30.value = 'all';
                if (activeCard === 'zero_views') activeCard = 'total';
            }
            if (amzZvdTable) amzZvdTable.setPage(1);
        });

        document.querySelector('#amz-zvd-filters [name="l30_views"]').addEventListener('change', function () {
            document.getElementById('amz-zvd-zero-only').checked = this.value === '0';
        });

        document.getElementById('amz-zvd-run-btn').addEventListener('click', function () {
            const selected = amzZvdTable ? amzZvdTable.getSelectedData().map(r => r.sku).filter(Boolean) : [];
            if (selected.length) {
                runDiagnostic({ mode: 'selected', skus: selected, filters: amzZvdFilters(), zero_only: amzZvdFilters().zero_only });
                return;
            }
            runDiagnostic({ mode: 'all', filters: amzZvdFilters(), zero_only: amzZvdFilters().zero_only });
        });

        document.getElementById('amz-zvd-export-btn').addEventListener('click', function () {
            window.location = AMZ_ZVD_EXPORT_URL + '?' + amzZvdQuery();
        });

        document.querySelectorAll('.amz-zvd-card').forEach(card => {
            card.addEventListener('click', function () {
                activeCard = this.dataset.card;
                document.querySelectorAll('.amz-zvd-card').forEach(el => el.classList.toggle('active', el === this));
                const zeroOnly = document.getElementById('amz-zvd-zero-only');
                const l30 = document.querySelector('#amz-zvd-filters [name="l30_views"]');
                if (activeCard === 'zero_views') {
                    zeroOnly.checked = true;
                    l30.value = '0';
                } else if (activeCard === 'total') {
                    zeroOnly.checked = false;
                    l30.value = 'all';
                } else {
                    zeroOnly.checked = false;
                    l30.value = 'all';
                }
                if (amzZvdTable) amzZvdTable.setPage(1);
            });
        });
        document.querySelector('.amz-zvd-card[data-card="total"]')?.classList.add('active');

        document.addEventListener('DOMContentLoaded', function () {
            amzZvdFillHeight();
            buildTable();
            amzZvdUpdateRun(@json($runStatus ?? []));
            window.addEventListener('resize', amzZvdFillHeight);
        });
    </script>
@endsection
