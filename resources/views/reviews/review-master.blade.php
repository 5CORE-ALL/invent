@extends('layouts.vertical', ['title' => 'Review Intelligence Master', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter { display: none !important; }

        /* Compact, scannable table */
        .tabulator { font-size: 12px; }
        .tabulator .tabulator-header .tabulator-col { background: #1f2937; color: #fff; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-title { font-size: 11px; font-weight: 600; padding: 6px 4px; }
        .tabulator .tabulator-row.tabulator-row-even { background-color: #fafbfc; }
        .tabulator .tabulator-row:hover { background-color: #eef5ff !important; }

        /* Pagination */
        .tabulator-paginator label { margin-right: 5px; }

        /* Badges */
        .pill { display:inline-block; padding:2px 8px; border-radius:20px; font-size:10px; font-weight:600; line-height:1.4; }
        .pill-positive { background:#d1f4e0; color:#198754; }
        .pill-neutral  { background:#fff3cd; color:#856404; }
        .pill-negative { background:#f8d7da; color:#842029; }
        .pill-quality       { background:#f8d7da; color:#842029; }
        .pill-packaging     { background:#fff3cd; color:#856404; }
        .pill-shipping      { background:#cff4fc; color:#055160; }
        .pill-service       { background:#e2e3e5; color:#383d41; }
        .pill-wrong_item    { background:#212529; color:#fff; }
        .pill-missing_parts { background:#cfe2ff; color:#084298; }
        .pill-other         { background:#f8f9fa; color:#495057; border:1px solid #dee2e6; }

        .stars { color:#f5b301; letter-spacing:1px; }

        /* Stat cards */
        .stat-card { border:0; border-radius:10px; transition:.15s; }
        .stat-card:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,.08); }
        .stat-card .stat-icon { width:42px; height:42px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; }

        .review-clip { display:inline-block; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:bottom; cursor:pointer; }

        /* Modal */
        .review-detail-section { background:#f8f9fa; border:1px solid #e9ecef; border-radius:8px; padding:14px; margin-bottom:12px; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Review Intelligence Master',
        'sub_title'  => 'Centralized SKU review analytics across all marketplaces',
    ])

    <div class="toast-container"></div>

    {{-- ============================ Summary Stat Cards ============================ --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <div class="card stat-card bg-white shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="fa fa-comment-dots"></i></div>
                    <div>
                        <div class="text-muted small">Total Reviews</div>
                        <h4 class="mb-0">{{ number_format($dashStats['total_reviews'] ?? 0) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card bg-white shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-danger-subtle text-danger"><i class="fa fa-triangle-exclamation"></i></div>
                    <div>
                        <div class="text-muted small">Negative %</div>
                        <h4 class="mb-0">{{ $dashStats['negative_pct'] ?? 0 }}%</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card bg-white shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning-subtle text-warning"><i class="fa fa-bug"></i></div>
                    <div>
                        <div class="text-muted small">Top Issue</div>
                        <h6 class="mb-0 text-capitalize">{{ str_replace('_',' ', $dashStats['top_issue'] ?? 'N/A') }}</h6>
                        <small class="text-muted">{{ $dashStats['top_issue_count'] ?? 0 }} reports</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card bg-white shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info"><i class="fa fa-bell"></i></div>
                    <div>
                        <div class="text-muted small">Open Alerts</div>
                        <h4 class="mb-0">{{ $dashStats['open_alerts'] ?? 0 }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================ Main Card ============================ --}}
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4 class="mb-3">SKU Reviews</h4>

                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                                id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown"
                            id="column-dropdown-menu" style="max-height: 400px; overflow-y: auto;"></ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-file-excel"></i> Export
                    </button>

                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadCsvModal">
                        <i class="fa fa-upload"></i> Import CSV
                    </button>

                    <button type="button" class="btn btn-sm btn-outline-primary" id="refresh-btn">
                        <i class="fa fa-rotate"></i> Refresh
                    </button>

                    <div class="ms-auto d-flex gap-2 flex-wrap align-items-center">
                        <select id="filter_marketplace" class="form-select form-select-sm" style="width:auto">
                            <option value="">All Marketplaces</option>
                        </select>
                        <select id="filter_rating" class="form-select form-select-sm" style="width:auto">
                            <option value="">All Ratings</option>
                            <option value="1">★ 1</option>
                            <option value="2">★★ 2</option>
                            <option value="3">★★★ 3</option>
                            <option value="4">★★★★ 4</option>
                            <option value="5">★★★★★ 5</option>
                        </select>
                        <select id="filter_sentiment" class="form-select form-select-sm" style="width:auto">
                            <option value="">All Sentiments</option>
                            <option value="positive">Positive</option>
                            <option value="neutral">Neutral</option>
                            <option value="negative">Negative</option>
                        </select>
                        <select id="filter_issue" class="form-select form-select-sm" style="width:auto">
                            <option value="">All Issues</option>
                            <option value="quality">Quality</option>
                            <option value="packaging">Packaging</option>
                            <option value="shipping">Shipping</option>
                            <option value="service">Service</option>
                            <option value="wrong_item">Wrong Item</option>
                            <option value="missing_parts">Missing Parts</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Filtered Summary</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="badge-total" style="color:#fff;font-weight:bold">Total: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="badge-positive" style="color:#fff;font-weight:bold">Positive: 0</span>
                        <span class="badge bg-warning fs-6 p-2" id="badge-neutral" style="color:#000;font-weight:bold">Neutral: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="badge-negative" style="color:#fff;font-weight:bold">Negative: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="badge-avg" style="color:#fff;font-weight:bold">Avg Rating: 0.0</span>
                        <span class="badge bg-dark fs-6 p-2" id="badge-flagged" style="color:#fff;font-weight:bold">Flagged: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="badge-skus" style="color:#fff;font-weight:bold">Unique SKUs: 0</span>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding:0;">
                <div id="reviews-table-wrapper" style="height: calc(100vh - 380px); display:flex; flex-direction:column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU…">
                    </div>
                    <div id="reviews-table" style="flex:1;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================ Review Detail Modal ============================ --}}
    <div class="modal fade" id="reviewDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-comment-dots me-2"></i>Review Detail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="reviewDetailBody">
                    <div class="text-center py-4"><div class="spinner-border text-secondary"></div></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================ CSV Upload Modal ============================ --}}
    <div class="modal fade" id="uploadCsvModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-cloud-arrow-up me-2"></i>Import Reviews via CSV / TSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <strong>Required columns:</strong><br>
                        <code>sku, marketplace, review_id, rating, review_title, review_text, reviewer_name, review_date</code>
                        <div class="mt-2">
                            <a href="{{ asset('sample_csv/reviews_sample.csv') }}" download>
                                <i class="fa fa-download me-1"></i>Download sample CSV
                            </a>
                        </div>
                    </div>
                    <form id="csvUploadForm" enctype="multipart/form-data" onsubmit="reviewsUploadCsv(event); return false;">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Select CSV / TSV file (max 10MB)</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv,.tsv,.txt">
                        </div>
                        <div id="csvUploadMsg"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="btnUploadCsv" onclick="reviewsUploadCsv(event)">
                        <i class="fa fa-upload me-1"></i>Upload &amp; Process
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        const REVIEWS_DATA_URL       = @json(route('reviews.data'));
        const REVIEWS_UPLOAD_URL     = @json(route('reviews.upload-csv'));
        const REVIEWS_COLVIS_GET_URL = @json(route('reviews.column-visibility.get'));
        const REVIEWS_COLVIS_SET_URL = @json(route('reviews.column-visibility.save'));
        const REVIEWS_SKU_DETAIL_URL = @json(url('/reviews/sku')); // /reviews/sku/{sku}/detail
        const CSRF_TOKEN             = @json(csrf_token());

        // -----------------------------------------------------------------
        // Toast helper
        // -----------------------------------------------------------------
        function showToast(message, type = 'info') {
            const container = document.querySelector('.toast-container');
            if (!container) return;
            const color = type === 'error' ? 'danger' : (type === 'success' ? 'success' : 'info');
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${color} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>`;
            container.appendChild(toast);
            const bsT = new bootstrap.Toast(toast);
            bsT.show();
            toast.addEventListener('hidden.bs.toast', () => toast.remove());
        }

        // -----------------------------------------------------------------
        // Formatters
        // -----------------------------------------------------------------
        function escapeHtml(s) {
            if (s === null || s === undefined) return '';
            return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }
        function pill(value, className) {
            if (!value) return '<span class="text-muted">—</span>';
            const label = String(value).replace(/_/g, ' ');
            return `<span class="pill ${className}">${escapeHtml(label)}</span>`;
        }
        function sentimentFmt(cell) {
            const v = cell.getValue();
            if (!v) return '<span class="text-muted">—</span>';
            return pill(v, 'pill-' + v);
        }
        function issueFmt(cell) {
            const v = cell.getValue();
            if (!v) return '<span class="text-muted">—</span>';
            return pill(v, 'pill-' + v);
        }
        function ratingFmt(cell) {
            const v = parseInt(cell.getValue() || 0);
            if (!v) return '<span class="text-muted">—</span>';
            const filled = '★'.repeat(v);
            const empty  = '☆'.repeat(5 - v);
            return `<span class="stars">${filled}<span class="text-muted">${empty}</span></span>`;
        }
        function marketplaceFmt(cell) {
            const v = cell.getValue();
            if (!v) return '<span class="text-muted">—</span>';
            const colors = { amazon:'primary', ebay:'warning', ebay1:'warning', ebay2:'warning', ebay3:'warning',
                             walmart:'info', temu:'success', 'temu 1':'success', shopify:'dark', csv:'secondary' };
            const c = colors[v.toLowerCase()] || 'secondary';
            return `<span class="badge bg-${c} bg-opacity-25 text-${c} text-capitalize">${escapeHtml(v)}</span>`;
        }
        function reviewTextFmt(cell) {
            const row  = cell.getRow().getData();
            const text = (cell.getValue() || row.review_text || '').toString();
            if (!text) return '<span class="text-muted">—</span>';
            const truncated = text.length > 60 ? text.substring(0, 60) + '…' : text;
            return `<span class="review-clip text-decoration-underline" title="${escapeHtml(text)}">${escapeHtml(truncated)}</span>`;
        }
        function flagFmt(cell) {
            return cell.getValue()
                ? '<i class="fa fa-flag text-danger" title="Flagged"></i>'
                : '';
        }
        function actionsFmt(cell) {
            const row = cell.getRow().getData();
            return `
                <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-outline-secondary py-0 px-2 btn-view-review" title="View" data-id="${row.id}">
                        <i class="fa fa-eye"></i>
                    </button>
                    <button class="btn btn-xs btn-outline-primary py-0 px-2 btn-sku-detail" title="SKU stats" data-sku="${escapeHtml(row.sku)}">
                        <i class="fa fa-chart-line"></i>
                    </button>
                </div>`;
        }

        // -----------------------------------------------------------------
        // Tabulator
        // -----------------------------------------------------------------
        let table = null;

        const COLUMN_DEFS = [
            { title: 'SKU',          field: 'sku',           width: 160, frozen: true,
              headerFilter: 'input', headerFilterPlaceholder: 'SKU…',
              cssClass: 'text-primary fw-bold' },
            { title: 'Product',      field: 'product_name',  width: 220,
              formatter: cell => {
                  const v = cell.getValue();
                  return v ? `<span title="${escapeHtml(v)}">${escapeHtml(v.length > 30 ? v.substring(0,30)+'…' : v)}</span>`
                           : '<span class="text-muted">—</span>';
              } },
            { title: 'Marketplace',  field: 'marketplace',   width: 110, formatter: marketplaceFmt,
              headerFilter: 'list', headerFilterParams: { valuesLookup: true, clearable: true } },
            { title: 'Rating',       field: 'rating',        width: 110, hozAlign: 'center', sorter: 'number',
              formatter: ratingFmt,
              headerFilter: 'list', headerFilterParams: { values: { '': 'All', '1':'1', '2':'2', '3':'3', '4':'4', '5':'5' } } },
            { title: 'Review',       field: 'review_title',  width: 300, formatter: reviewTextFmt,
              headerFilter: 'input', headerFilterPlaceholder: 'Search…',
              headerFilterFunc: (headerValue, rowValue, rowData) => {
                  if (!headerValue) return true;
                  const hay = ((rowData.review_title || '') + ' ' + (rowData.review_text || '')).toLowerCase();
                  return hay.includes(headerValue.toLowerCase());
              } },
            { title: 'Reviewer',     field: 'reviewer_name', width: 130,
              formatter: c => c.getValue() ? escapeHtml(c.getValue()) : '<span class="text-muted">—</span>' },
            { title: 'Date',         field: 'review_date',   width: 110, sorter: 'datetime',
              formatter: c => c.getValue() ? `<small>${escapeHtml(c.getValue())}</small>` : '<span class="text-muted">—</span>' },
            { title: 'Sentiment',    field: 'sentiment',     width: 110, hozAlign: 'center', formatter: sentimentFmt,
              headerFilter: 'list', headerFilterParams: { values: { '':'All', positive:'Positive', neutral:'Neutral', negative:'Negative' } } },
            { title: 'Issue',        field: 'issue_category',width: 130, hozAlign: 'center', formatter: issueFmt,
              headerFilter: 'list', headerFilterParams: { values: { '':'All', quality:'Quality', packaging:'Packaging', shipping:'Shipping', service:'Service', wrong_item:'Wrong Item', missing_parts:'Missing Parts', other:'Other' } } },
            { title: 'Supplier',     field: 'supplier_name', width: 140,
              formatter: c => c.getValue() ? escapeHtml(c.getValue()) : '<span class="text-muted">—</span>',
              headerFilter: 'input' },
            { title: 'Department',   field: 'department',    width: 130,
              formatter: c => c.getValue() ? `<small class="text-info">${escapeHtml(c.getValue())}</small>` : '<span class="text-muted">—</span>' },
            { title: 'Source',       field: 'source_type',   width: 80, hozAlign: 'center',
              formatter: c => c.getValue() ? `<span class="badge bg-light text-dark border">${escapeHtml(c.getValue())}</span>` : '' },
            { title: 'Flag',         field: 'is_flagged',    width: 60,  hozAlign: 'center', formatter: flagFmt },
            { title: 'Actions',      field: 'actions',       width: 95,  hozAlign: 'center', headerSort: false, formatter: actionsFmt },
        ];

        // -----------------------------------------------------------------
        // Summary updater
        // -----------------------------------------------------------------
        function updateSummary() {
            if (!table) return;
            const data = table.getData('active');
            let total = 0, pos = 0, neu = 0, neg = 0, flagged = 0, ratingSum = 0, ratingCount = 0;
            const skuSet = new Set();
            data.forEach(r => {
                total++;
                if (r.sku) skuSet.add(r.sku);
                if (r.sentiment === 'positive') pos++;
                else if (r.sentiment === 'neutral') neu++;
                else if (r.sentiment === 'negative') neg++;
                if (r.is_flagged) flagged++;
                const rating = parseInt(r.rating);
                if (rating > 0) { ratingSum += rating; ratingCount++; }
            });
            const avg = ratingCount > 0 ? (ratingSum / ratingCount).toFixed(1) : '0.0';

            document.getElementById('badge-total').textContent    = 'Total: ' + total.toLocaleString();
            document.getElementById('badge-positive').textContent = 'Positive: ' + pos.toLocaleString();
            document.getElementById('badge-neutral').textContent  = 'Neutral: '  + neu.toLocaleString();
            document.getElementById('badge-negative').textContent = 'Negative: ' + neg.toLocaleString();
            document.getElementById('badge-avg').textContent      = 'Avg Rating: ' + avg;
            document.getElementById('badge-flagged').textContent  = 'Flagged: '  + flagged.toLocaleString();
            document.getElementById('badge-skus').textContent     = 'Unique SKUs: ' + skuSet.size.toLocaleString();
        }

        // -----------------------------------------------------------------
        // Column visibility (per-user, server-persisted)
        // -----------------------------------------------------------------
        function applyColumnVisibilityFromServer() {
            fetch(REVIEWS_COLVIS_GET_URL, { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } })
                .then(r => r.json())
                .then(vis => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (def.field && vis[def.field] === false) col.hide();
                    });
                    buildColumnDropdown(vis);
                })
                .catch(() => buildColumnDropdown({}));
        }
        function buildColumnDropdown(savedVis) {
            const menu = document.getElementById('column-dropdown-menu');
            menu.innerHTML = '';
            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (!def.field) return;
                const li = document.createElement('li');
                const label = document.createElement('label');
                label.style.cssText = 'display:block;padding:5px 10px;cursor:pointer';
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.value = def.field;
                cb.style.marginRight = '8px';
                cb.checked = savedVis[def.field] !== false;
                label.appendChild(cb);
                label.appendChild(document.createTextNode(def.title));
                li.appendChild(label);
                menu.appendChild(li);
            });
        }
        function saveColumnVisibilityToServer() {
            const vis = {};
            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field) vis[def.field] = col.isVisible();
            });
            fetch(REVIEWS_COLVIS_SET_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ visibility: vis }),
            });
        }

        // -----------------------------------------------------------------
        // Init
        // -----------------------------------------------------------------
        $(document).ready(function () {
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } });

            table = new Tabulator('#reviews-table', {
                ajaxURL: REVIEWS_DATA_URL,
                layout: 'fitDataStretch',
                pagination: true,
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                paginationCounter: 'rows',
                placeholder: 'No reviews yet. Click <strong>Import CSV</strong> above to load data.',
                ajaxResponse: function (url, params, response) {
                    // Populate marketplace dropdown from data
                    const mps = [...new Set(response.map(r => r.marketplace).filter(Boolean))].sort();
                    const sel = document.getElementById('filter_marketplace');
                    if (sel) {
                        const cur = sel.value;
                        sel.innerHTML = '<option value="">All Marketplaces</option>' +
                            mps.map(m => `<option value="${escapeHtml(m)}">${escapeHtml(m)}</option>`).join('');
                        sel.value = cur;
                    }
                    return response;
                },
                ajaxError: function (err) {
                    console.error('Reviews data load error:', err);
                    showToast('Failed to load reviews: ' + (err.message || 'Unknown error'), 'error');
                },
                initialSort: [{ column: 'review_date', dir: 'desc' }],
                columns: COLUMN_DEFS,
            });

            table.on('tableBuilt',     applyColumnVisibilityFromServer);
            table.on('dataLoaded',     updateSummary);
            table.on('dataFiltered',   updateSummary);
            table.on('dataProcessed',  updateSummary);
            table.on('renderComplete', updateSummary);

            // --- SKU search ---
            $('#sku-search').on('keyup', function () {
                const v = $(this).val();
                table.setFilter('sku', 'like', v);
            });

            // --- Quick filters ---
            function applyQuickFilters() {
                const filters = [];
                const mp = $('#filter_marketplace').val();
                const rt = $('#filter_rating').val();
                const se = $('#filter_sentiment').val();
                const is = $('#filter_issue').val();
                if (mp) filters.push({ field: 'marketplace',    type: '=',    value: mp });
                if (rt) filters.push({ field: 'rating',         type: '=',    value: parseInt(rt) });
                if (se) filters.push({ field: 'sentiment',      type: '=',    value: se });
                if (is) filters.push({ field: 'issue_category', type: '=',    value: is });
                table.clearFilter(true);
                if ($('#sku-search').val()) table.setFilter('sku', 'like', $('#sku-search').val());
                filters.forEach(f => table.addFilter(f.field, f.type, f.value));
            }
            $('#filter_marketplace, #filter_rating, #filter_sentiment, #filter_issue').on('change', applyQuickFilters);

            // --- Column visibility dropdown toggle ---
            document.getElementById('column-dropdown-menu').addEventListener('change', function (e) {
                if (e.target.type !== 'checkbox') return;
                const col = table.getColumn(e.target.value);
                if (e.target.checked) col.show(); else col.hide();
                saveColumnVisibilityToServer();
            });

            // --- Show all columns ---
            document.getElementById('show-all-columns-btn').addEventListener('click', function () {
                table.getColumns().forEach(c => c.show());
                buildColumnDropdown({});
                saveColumnVisibilityToServer();
            });

            // --- Export ---
            $('#export-btn').on('click', function () {
                table.download('csv', 'reviews_export_' + new Date().toISOString().slice(0,10) + '.csv');
            });

            // --- Refresh ---
            $('#refresh-btn').on('click', function () {
                table.setData(REVIEWS_DATA_URL).then(() => showToast('Reviews reloaded', 'success'));
            });

            // --- Row actions ---
            $(document).on('click', '.btn-view-review', function () {
                const id = $(this).data('id');
                const row = table.getRows('active').map(r => r.getData()).find(r => r.id == id);
                if (row) openReviewDetail(row);
            });
            $(document).on('click', '.btn-sku-detail', function () {
                const sku = $(this).data('sku');
                window.location.href = REVIEWS_SKU_DETAIL_URL + '/' + encodeURIComponent(sku) + '/detail';
            });
        });

        // -----------------------------------------------------------------
        // Review detail modal
        // -----------------------------------------------------------------
        function openReviewDetail(r) {
            const body = `
                <div class="review-detail-section">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0">${escapeHtml(r.review_title || 'No Title')}</h6>
                        <div>${ratingHtmlInline(r.rating)}</div>
                    </div>
                    <p class="mb-0">${escapeHtml(r.review_text || '—')}</p>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="review-detail-section">
                            <table class="table table-sm table-borderless mb-0 small">
                                <tr><td class="text-muted" style="width:120px">SKU</td><td><strong>${escapeHtml(r.sku || '—')}</strong></td></tr>
                                <tr><td class="text-muted">Product</td><td>${escapeHtml(r.product_name || '—')}</td></tr>
                                <tr><td class="text-muted">Marketplace</td><td>${escapeHtml(r.marketplace || '—')}</td></tr>
                                <tr><td class="text-muted">Reviewer</td><td>${escapeHtml(r.reviewer_name || '—')}</td></tr>
                                <tr><td class="text-muted">Date</td><td>${escapeHtml(r.review_date || '—')}</td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="review-detail-section">
                            <table class="table table-sm table-borderless mb-0 small">
                                <tr><td class="text-muted" style="width:120px">Sentiment</td><td>${r.sentiment ? pill(r.sentiment, 'pill-' + r.sentiment) : '—'}</td></tr>
                                <tr><td class="text-muted">Issue</td><td>${r.issue_category ? pill(r.issue_category, 'pill-' + r.issue_category) : '—'}</td></tr>
                                <tr><td class="text-muted">Supplier</td><td>${escapeHtml(r.supplier_name || '—')}</td></tr>
                                <tr><td class="text-muted">Department</td><td>${escapeHtml(r.department || '—')}</td></tr>
                                <tr><td class="text-muted">Source</td><td>${escapeHtml(r.source_type || '—')}</td></tr>
                                <tr><td class="text-muted">Flagged</td><td>${r.is_flagged ? '<i class="fa fa-flag text-danger"></i> Yes' : 'No'}</td></tr>
                            </table>
                        </div>
                    </div>
                    ${r.ai_summary ? `<div class="col-12"><div class="review-detail-section"><strong><i class="fa fa-brain me-1 text-primary"></i>AI Summary:</strong><div class="mt-1">${escapeHtml(r.ai_summary)}</div></div></div>` : ''}
                    ${r.ai_reply ? `<div class="col-12"><div class="review-detail-section"><strong><i class="fa fa-robot me-1 text-primary"></i>Suggested Reply:</strong><div class="mt-1">${escapeHtml(r.ai_reply)}</div></div></div>` : ''}
                </div>`;
            document.getElementById('reviewDetailBody').innerHTML = body;
            new bootstrap.Modal(document.getElementById('reviewDetailModal')).show();
        }
        function ratingHtmlInline(n) {
            n = parseInt(n || 0);
            if (!n) return '<span class="text-muted">No rating</span>';
            return `<span class="stars">${'★'.repeat(n)}<span class="text-muted">${'☆'.repeat(5-n)}</span></span>`;
        }

        // -----------------------------------------------------------------
        // CSV Upload (self-contained, vanilla fetch)
        // -----------------------------------------------------------------
        window.reviewsUploadCsv = function (e) {
            if (e && e.preventDefault) e.preventDefault();
            const fileInput = document.getElementById('csv_file');
            const msgEl = document.getElementById('csvUploadMsg');
            if (!fileInput || !fileInput.files.length) {
                msgEl.innerHTML = '<div class="alert alert-warning small mb-0">Please choose a CSV/TSV file first.</div>';
                return false;
            }
            const btn = document.getElementById('btnUploadCsv');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Uploading…';
            msgEl.innerHTML = '<div class="alert alert-info small mb-0">Uploading &amp; processing… please wait.</div>';

            const fd = new FormData();
            fd.append('csv_file', fileInput.files[0]);
            fd.append('_token', CSRF_TOKEN);

            fetch(REVIEWS_UPLOAD_URL, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(async res => {
                const text = await res.text();
                let data = null;
                try { data = text ? JSON.parse(text) : null; } catch (_) {}
                if (!res.ok) {
                    throw new Error((data && data.message) ? data.message : ('Upload failed (HTTP ' + res.status + ').'));
                }
                return data || { message: 'Upload complete.' };
            })
            .then(data => {
                msgEl.innerHTML = '<div class="alert alert-success small mb-0">' + escapeHtml(data.message || 'Upload complete.') + '</div>';
                fileInput.value = '';
                showToast(data.message || 'Reviews imported.', 'success');
                if (table) table.setData(REVIEWS_DATA_URL);
            })
            .catch(err => {
                msgEl.innerHTML = '<div class="alert alert-danger small mb-0">' + escapeHtml(err.message || 'Upload failed.') + '</div>';
                showToast(err.message || 'Upload failed.', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = original;
            });
            return false;
        };
    </script>
@endsection
