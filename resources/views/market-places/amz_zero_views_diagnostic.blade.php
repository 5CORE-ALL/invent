@extends('layouts.vertical', ['title' => 'Amazon 0 Views Diagnostic', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #amz-zvd-table-card {
            margin-bottom: 0;
        }
        #amz-zvd-table-card .card-body {
            padding: 0;
        }
        #amz-zvd-wrap {
            display: flex;
            flex-direction: column;
            min-height: 360px;
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
        }
        #amz-zvd-wrap .tabulator .tabulator-header {
            position: sticky !important;
            top: 0 !important;
            z-index: 8 !important;
            background: #dbeafe !important;
        }
        #amz-zvd-wrap .tabulator .tabulator-tableholder {
            overflow-y: auto !important;
        }
        #amz-zvd-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            white-space: normal !important; font-size: 11px; font-weight: 600; text-align: center; line-height: 1.2; padding: 4px 2px;
        }
        #amz-zvd-wrap .amz-zvd-inv-header {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 3px;
            padding: 2px 0;
        }
        #amz-zvd-wrap .amz-zvd-inv-header select {
            width: 100%;
            height: 22px;
            font-size: 11px;
            padding: 0 2px;
        }
        #amz-zvd-wrap .tabulator .tabulator-cell { padding: 3px 4px !important; }
        #amz-zvd-wrap .tabulator-row.tabulator-selected { background: #e7f1ff !important; }
        #amz-zvd-wrap .amz-zvd-title-btn {
            border: 1px solid #2f9e44;
            background: #fff;
            color: #2f9e44;
            border-radius: 6px;
            width: 28px;
            height: 28px;
            padding: 0;
            line-height: 1;
            cursor: pointer;
        }
        #amz-zvd-wrap .amz-zvd-title-btn:hover { background: #ebfbee; }
        #amz-zvd-wrap .amz-zvd-title-btn:disabled {
            border-color: #ced4da;
            color: #adb5bd;
            cursor: default;
        }
        #amz-zvd-wrap .tabulator .tabulator-cell.tabulator-frozen,
        #amz-zvd-wrap .tabulator .tabulator-col.tabulator-frozen {
            background: #fff;
            z-index: 2;
        }
        #amz-zvd-wrap .tabulator .tabulator-header .tabulator-col.tabulator-frozen {
            background: #dbeafe !important;
        }
        .amz-zvd-thumb { width: 36px; height: 36px; object-fit: contain; border-radius: 4px; background: #fff; }
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
        #amz-zvd-detail-body .amz-zvd-panel {
            min-height: 220px;
        }
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
                    <label class="form-label small mb-1">SKU</label>
                    <input type="text" name="sku" class="form-control form-control-sm" placeholder="SKU">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1">ASIN</label>
                    <input type="text" name="asin" class="form-control form-control-sm" placeholder="ASIN">
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
                    <label class="form-label small mb-1">INV</label>
                    <select name="inv" class="form-select form-select-sm">
                        <option value="all" selected>INV</option>
                        <option value="zero">Zero</option>
                        <option value="more">More</option>
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

    <div class="card" id="amz-zvd-table-card">
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

    <div class="modal fade" id="amz-zvd-title-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0">Title</h5>
                        <div class="small text-muted" id="amz-zvd-title-sub"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="amz-zvd-title-full" style="font-size:16px;line-height:1.5;word-break:break-word;"></div>
                </div>
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

        function amzZvdReload() {
            if (!amzZvdTable) return;
            try {
                if (amzZvdTable.getPage() !== 1) {
                    amzZvdTable.setPage(1);
                    return;
                }
            } catch (e) {}
            amzZvdTable.replaceData();
        }

        function amzZvdInvValue() {
            const formInv = document.querySelector('#amz-zvd-filters [name="inv"]');
            return formInv ? formInv.value : 'all';
        }

        function amzZvdSetInv(value, reload) {
            const next = value || 'all';
            const formInv = document.querySelector('#amz-zvd-filters [name="inv"]');
            if (formInv) formInv.value = next;
            const headerInv = document.getElementById('amz-zvd-inv-header-filter');
            if (headerInv) headerInv.value = next;
            if (reload) amzZvdReload();
        }

        function amzZvdInvHeaderFilter() {
            const select = document.createElement('select');
            select.id = 'amz-zvd-inv-header-filter';
            select.className = 'form-select form-select-sm';
            select.title = 'Filter inventory';
            select.innerHTML = '<option value="all">INV</option><option value="zero">Zero</option><option value="more">More</option>';
            select.value = amzZvdInvValue();
            select.addEventListener('mousedown', function (e) { e.stopPropagation(); });
            select.addEventListener('click', function (e) { e.stopPropagation(); });
            select.addEventListener('change', function () {
                amzZvdSetInv(this.value, true);
            });
            return select;
        }

        function amzZvdAsin(row) {
            return String((row && (row.asin || '')) || '').trim();
        }

        function amzZvdBuyerHref(asin) {
            return 'https://www.amazon.com/dp/' + encodeURIComponent(asin);
        }

        function amzZvdSellerHref(asin) {
            return 'https://sellercentral.amazon.com/inventory/ref=xx_invmgr_dnav_xx?asin=' + encodeURIComponent(asin);
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

        function openProductNameModal(row) {
            const name = String((row && row.product_name) || '').trim();
            const sku = String((row && row.sku) || '').trim();
            const asin = String((row && row.asin) || '').trim();
            document.getElementById('amz-zvd-title-sub').textContent =
                [sku ? ('SKU ' + sku) : '', asin ? ('ASIN ' + asin) : ''].filter(Boolean).join(' · ');
            document.getElementById('amz-zvd-title-full').textContent = name || '—';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('amz-zvd-title-modal')).show();
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
                '<div class="row g-3 mb-3 align-items-stretch">' +
                    section('TRAFFIC', [
                        ['L7 Views', row.l7_views],
                        ['L30 Views', row.l30_views],
                        ['A L30', row.A_L30],
                        ['CVR L30', amzZvdPct(row.CVR_L30)],
                        ['OV L30', row.L30],
                        ['Dil %', amzZvdPct(row['E Dil%'])],
                    ]) +
                    section('LISTING', [
                        ['Listing Status', row.listing_status],
                        ['Suppression', row.suppression],
                        ['Category', row.category],
                        ['Title', row.product_name],
                        ['Main Image', row.main_image_status],
                    ]) +
                    section('SELLING', [
                        ['Price', row.price != null ? ('$' + Number(row.price).toFixed(2)) : '—'],
                        ['LMP', row.lmp_price != null ? ('$' + Number(row.lmp_price).toFixed(2)) : '—'],
                        ['Inventory', row.inventory],
                        ['Buyable', row.buyable],
                        ['Fulfillment', row.fulfillment],
                        ['Featured Offer %', row.featured_offer_percentage],
                        ['GROI %', amzZvdPct(row['GROI%'])],
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

        function amzZvdPct(v) {
            if (v == null || v === '') return '—';
            const n = Number(v);
            if (Number.isNaN(n)) return String(v);
            return n.toFixed(2) + '%';
        }

        function section(title, rows) {
            const body = rows.map(([k, v], i) =>
                '<div class="d-flex justify-content-between py-1' + (i < rows.length - 1 ? ' border-bottom' : '') +
                '"><span class="text-muted">' +
                amzZvdEsc(k) + '</span><span class="text-end ms-2">' + amzZvdEsc(v ?? '—') + '</span></div>'
            ).join('');
            return '<div class="col-12 col-md-4"><div class="border rounded p-2 h-100 amz-zvd-panel"><div class="fw-semibold mb-2">' +
                amzZvdEsc(title) + '</div>' + body +
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

        function amzZvdViewportHeight() {
            const wrap = document.getElementById('amz-zvd-wrap');
            if (!wrap) return 360;
            const footer = document.querySelector('.footer');
            const footerH = footer ? footer.offsetHeight : 36;
            const top = wrap.getBoundingClientRect().top;
            return Math.max(360, Math.floor(window.innerHeight - top - footerH - 8));
        }

        function amzZvdFillHeight() {
            const wrap = document.getElementById('amz-zvd-wrap');
            if (!wrap) return;
            const h = amzZvdViewportHeight();
            wrap.style.height = h + 'px';
            wrap.style.maxHeight = h + 'px';
            if (amzZvdTable) {
                amzZvdTable.setHeight(h);
            }
        }

        function buildTable() {
            amzZvdTable = new Tabulator('#amz-zvd-table', {
                ajaxURL: AMZ_ZVD_DATA_URL,
                ajaxConfig: 'GET',
                ajaxURLGenerator: function (url, config, params) {
                    const size = (params.size === true || params.size === 'true' || params.size === 'all')
                        ? 'all'
                        : (params.size || 50);
                    return url + '?' + amzZvdQuery({
                        page: params.page || 1,
                        size: size,
                    });
                },
                ajaxResponse: function (url, params, response) {
                    amzZvdUpdateSummary(response.summary || {});
                    amzZvdUpdateRun(response.run || {});
                    requestAnimationFrame(amzZvdFillHeight);
                    return {
                        data: response.data || [],
                        last_page: response.last_page || 1,
                    };
                },
                pagination: true,
                paginationMode: 'remote',
                filterMode: 'remote',
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200, true],
                layout: 'fitDataStretch',
                height: amzZvdViewportHeight(),
                placeholder: 'No SKUs match the current filters',
                selectableRows: true,
                columns: [
                    { formatter: 'rowSelection', titleFormatter: 'rowSelection', hozAlign: 'center', headerSort: false, width: 40, frozen: true },
                    { title: 'Parent', field: 'Parent', width: 110, frozen: true, cssClass: 'text-primary',
                      formatter: c => amzZvdEsc(c.getValue() || c.getRow().getData().parent || '—') },
                    { title: 'SKU', field: 'sku', width: 160, frozen: true,
                      formatter: c => amzZvdEsc(c.getValue() || c.getRow().getData()['(Child) sku'] || '') },
                    { title: 'ASIN', field: 'asin', width: 110,
                      formatter: c => {
                          const v = c.getValue();
                          if (!v) return '<span class="text-muted">—</span>';
                          return '<a href="#" class="amz-zvd-asin" data-asin="' + amzZvdEsc(v) + '" data-sku="' +
                              amzZvdEsc(c.getRow().getData().sku) + '">' + amzZvdEsc(v) + '</a>';
                      } },
                    { title: 'Links', field: 'links_column', width: 55, hozAlign: 'center', headerSort: false,
                      headerTooltip: 'S = Seller Central · B = Amazon buyer page (same as Analytics Amz)',
                      formatter: c => {
                          const row = c.getRow().getData();
                          const asin = amzZvdAsin(row);
                          const sellerHref = row.seller_link || (asin ? amzZvdSellerHref(asin) : '');
                          const buyerHref = row.buyer_link || (asin ? amzZvdBuyerHref(asin) : '');
                          if (!sellerHref && !buyerHref) {
                              return '<span class="text-muted" style="font-size:12px;">-</span>';
                          }
                          let html = '<div style="display:flex;flex-direction:column;gap:1px;line-height:1.1;align-items:center;">';
                          if (sellerHref) {
                              html += '<a class="amz-zvd-ext-link text-info" href="' + amzZvdEsc(sellerHref) + '"'
                                  + ' target="_blank" rel="noopener noreferrer" title="Seller Central — ' + amzZvdEsc(asin) + '"'
                                  + ' style="font-size:11px;text-decoration:none;"><i class="fa fa-link"></i> S</a>';
                          }
                          if (buyerHref) {
                              html += '<a class="amz-zvd-ext-link text-success" href="' + amzZvdEsc(buyerHref) + '"'
                                  + ' target="_blank" rel="noopener noreferrer" title="Buyer page — ' + amzZvdEsc(asin) + '"'
                                  + ' style="font-size:11px;text-decoration:none;"><i class="fa fa-link"></i> B</a>';
                          }
                          html += '</div>';
                          return html;
                      } },
                    { title: 'Title', field: 'product_name', width: 70, hozAlign: 'center', headerSort: false,
                      headerTooltip: 'Click the magnifying glass to view the full title',
                      formatter: c => {
                          const row = c.getRow().getData();
                          const name = String(c.getValue() || '').trim();
                          if (!name) return '<span class="text-muted">—</span>';
                          return '<button type="button" class="amz-zvd-title-btn" title="View full title"'
                              + ' data-name="' + amzZvdEsc(name) + '"'
                              + ' data-sku="' + amzZvdEsc(row.sku || '') + '"'
                              + ' data-asin="' + amzZvdEsc(row.asin || '') + '">'
                              + '<i class="fas fa-search"></i></button>';
                      } },
                    { title: 'Inventory', field: 'inventory', width: 96, hozAlign: 'right',
                      headerTooltip: 'INV filter: all SKUs, inventory = 0, or inventory > 0',
                      titleFormatter: function () {
                          const wrap = document.createElement('div');
                          wrap.className = 'amz-zvd-inv-header';
                          const label = document.createElement('div');
                          label.textContent = 'Inventory';
                          wrap.appendChild(label);
                          wrap.appendChild(amzZvdInvHeaderFilter());
                          return wrap;
                      } },
                    { title: 'Listing Status', field: 'listing_status', width: 110, hozAlign: 'center' },
                    { title: 'Suppression', field: 'suppression', width: 110, hozAlign: 'center' },
                    { title: 'Buyable', field: 'buyable', width: 80, hozAlign: 'center' },
                    { title: 'Price', field: 'price', width: 80, hozAlign: 'right',
                      formatter: c => c.getValue() == null ? '—' : '$' + Number(c.getValue()).toFixed(2) },
                    { title: 'Featured Offer %', field: 'featured_offer_percentage', width: 90, hozAlign: 'center',
                      headerTooltip: 'L30 Featured Offer % from GET_SALES_AND_TRAFFIC_REPORT (buyBoxPercentage)',
                      formatter: c => {
                          const v = c.getValue();
                          if (v == null || v === '' || v === '—' || v === 'Not Available via Current API') {
                              return '<span class="text-muted">—</span>';
                          }
                          const num = parseFloat(v);
                          if (!isNaN(num) && String(v).indexOf('%') === -1) {
                              return num.toFixed(1) + '%';
                          }
                          return amzZvdEsc(v);
                      } },
                    { title: 'L7 Views', field: 'l7_views', width: 80, hozAlign: 'right' },
                    { title: 'L30 Views', field: 'l30_views', width: 80, hozAlign: 'right' },
                    { title: 'Category', field: 'category', width: 140, formatter: c => amzZvdEsc(c.getValue() || '—') },
                    { title: 'Main Image', field: 'main_image', width: 80, hozAlign: 'center',
                      formatter: c => {
                          const url = c.getValue();
                          if (!url) return '<span class="text-muted">—</span>';
                          return '<img class="amz-zvd-thumb" src="' + amzZvdEsc(url) + '" alt="">';
                      } },
                    { title: 'Diagnostic Result', field: 'diagnostic_status', width: 140,
                      formatter: c => amzZvdBadge(c.getValue(), c.getRow().getData().color) },
                    { title: 'Problem', field: 'problem', width: 220, formatter: c => amzZvdEsc(c.getValue() || '') },
                    { title: 'Recommended Action', field: 'recommended_action', width: 240, formatter: c => amzZvdEsc(c.getValue() || '') },
                    { title: 'Last Checked', field: 'last_checked_at', width: 120 },
                    { title: 'Actions', field: '_actions', width: 160, headerSort: false,
                      formatter: c => {
                          const d = c.getRow().getData();
                          return '<button type="button" class="btn btn-sm btn-outline-primary amz-zvd-view" data-sku="' +
                              amzZvdEsc(d.sku) + '" data-asin="' + amzZvdEsc(d.asin || '') +
                              '">View Diagnostic</button> ' +
                              '<button type="button" class="btn btn-sm btn-outline-warning amz-zvd-rerun" data-sku="' +
                              amzZvdEsc(d.sku) + '">Run Again</button>';
                      } },
                ],
            });

            document.getElementById('amz-zvd-table').addEventListener('click', function (e) {
                if (e.target.closest('.amz-zvd-ext-link')) {
                    e.stopPropagation();
                    return;
                }
                const titleBtn = e.target.closest('.amz-zvd-title-btn');
                if (titleBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    openProductNameModal({
                        product_name: titleBtn.dataset.name || '',
                        sku: titleBtn.dataset.sku || '',
                        asin: titleBtn.dataset.asin || '',
                    });
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
            amzZvdReload();
        });

        document.getElementById('amz-zvd-reset-btn').addEventListener('click', function () {
            document.getElementById('amz-zvd-filters').reset();
            document.getElementById('amz-zvd-zero-only').checked = false;
            document.querySelector('#amz-zvd-filters [name="l30_views"]').value = 'all';
            amzZvdSetInv('all', false);
            activeCard = 'total';
            document.querySelectorAll('.amz-zvd-card').forEach(el => el.classList.toggle('active', el.dataset.card === activeCard));
            amzZvdReload();
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
            amzZvdReload();
        });

        ['inv', 'l30_views', 'status', 'diagnostic_result'].forEach(function (name) {
            const el = document.querySelector('#amz-zvd-filters [name="' + name + '"]');
            if (!el) return;
            el.addEventListener('change', function () {
                if (name === 'l30_views') {
                    document.getElementById('amz-zvd-zero-only').checked = this.value === '0';
                }
                if (name === 'inv') {
                    amzZvdSetInv(this.value, true);
                    return;
                }
                amzZvdReload();
            });
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
                amzZvdReload();
            });
        });
        document.querySelector('.amz-zvd-card[data-card="total"]')?.classList.add('active');

        function amzZvdInit() {
            if (amzZvdTable) return;
            amzZvdFillHeight();
            buildTable();
            amzZvdUpdateRun(@json($runStatus ?? []));
            requestAnimationFrame(amzZvdFillHeight);
            setTimeout(amzZvdFillHeight, 80);
            window.addEventListener('resize', amzZvdFillHeight);
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', amzZvdInit);
        } else {
            amzZvdInit();
        }
    </script>
@endsection
