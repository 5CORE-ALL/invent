@extends('layouts.vertical', ['title' => 'Shopify Raw Data', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .source-badge {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 20px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
            user-select: none;
        }
        .source-badge.active {
            border-color: #fff;
            box-shadow: 0 0 0 2px currentColor;
        }
        .stat-card {
            border-radius: 10px;
            padding: 14px 18px;
            min-width: 160px;
            flex: 1;
        }
        .stat-card .stat-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.8;
            margin-bottom: 2px;
        }
        .stat-card .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .stat-card .stat-sub {
            font-size: 0.78rem;
            opacity: 0.85;
            margin-top: 2px;
        }
        #shopify-raw-table .tabulator-header .tabulator-col-title {
            font-size: 11.5px;
            font-weight: 600;
        }
        .filter-bar {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px 14px;
            gap: 10px;
            flex-wrap: wrap;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shopify Raw Data',
        'sub_title'  => 'Raw order-item records filtered by source / tag',
    ])

    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999;"></div>

    {{-- Stats Row --}}
    <div class="row g-3 mb-3" id="stats-row">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-3" id="stat-cards">
                <div class="stat-card bg-primary text-white">
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value" id="stat-total-orders">—</div>
                    <div class="stat-sub">all sources</div>
                </div>
                <div class="stat-card bg-success text-white">
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value" id="stat-total-revenue">—</div>
                    <div class="stat-sub">all sources</div>
                </div>
                <div class="stat-card bg-info text-white">
                    <div class="stat-label">Total Qty</div>
                    <div class="stat-value" id="stat-total-qty">—</div>
                    <div class="stat-sub">all sources</div>
                </div>
                {{-- per-source stat cards injected by JS --}}
                <div id="per-source-stats" class="d-flex flex-wrap gap-3"></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4 class="mb-3">Shopify Raw Data
                    <small class="text-muted fs-6 ms-2">— checkout-via-buy-now-button · wsaio-app · shopify_draft_order</small>
                </h4>

                {{-- Filter bar --}}
                <div class="d-flex align-items-center filter-bar mb-3">
                    {{-- Source tabs --}}
                    <div class="d-flex gap-2 align-items-center flex-wrap me-3">
                        <span class="fw-semibold text-muted me-1" style="font-size:0.82rem;">Source / Tag:</span>
                        <span class="badge bg-dark source-badge active" data-source="all">All</span>
                        <span class="badge source-badge" data-source="checkout-via-buy-now-button"
                              style="background-color:#6f42c1;">Buy Now Button</span>
                        <span class="badge source-badge" data-source="wsaio-app"
                              style="background-color:#fd7e14;">WSAIO App</span>
                        <span class="badge source-badge" data-source="shopify_draft_order"
                              style="background-color:#0dcaf0; color:#000;">Draft Order</span>
                    </div>

                    <div class="vr mx-2"></div>

                    {{-- Date range --}}
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <label class="fw-semibold text-muted mb-0" style="font-size:0.82rem;">Date:</label>
                        <input type="text" id="date-from" class="form-control form-control-sm" placeholder="From" style="width:130px;">
                        <span class="text-muted">–</span>
                        <input type="text" id="date-to" class="form-control form-control-sm" placeholder="To" style="width:130px;">
                        <button class="btn btn-sm btn-primary" id="apply-date-btn">
                            <i class="fa fa-filter me-1"></i>Apply
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" id="reset-date-btn">Reset</button>
                    </div>

                    <div class="vr mx-2"></div>

                    {{-- Column visibility --}}
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="colVisBtn" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye me-1"></i>Columns
                        </button>
                        <ul class="dropdown-menu" id="col-dropdown" style="max-height:400px;overflow-y:auto;min-width:200px;"></ul>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary ms-1" id="show-all-cols-btn">Show All</button>

                    <div class="vr mx-2"></div>

                    {{-- Export --}}
                    <button class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-file-excel me-1"></i>Export CSV
                    </button>
                </div>

                {{-- Table inline stats --}}
                <div class="d-flex flex-wrap gap-2 mb-2" id="table-stats">
                    <span class="badge bg-primary fs-6 p-2" id="tbl-orders">Orders: 0</span>
                    <span class="badge bg-success fs-6 p-2" id="tbl-qty">Qty: 0</span>
                    <span class="badge bg-info fs-6 p-2 text-dark" id="tbl-revenue">Revenue: $0.00</span>
                    <span class="badge bg-secondary fs-6 p-2" id="tbl-avg-price">Avg Price: $0.00</span>
                </div>
            </div>

            <div class="card-body p-0">
                <div style="height:calc(100vh - 280px); display:flex; flex-direction:column;">
                    <div class="p-2 bg-light border-bottom">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-3">
                                <input type="text" id="search-sku" class="form-control form-control-sm"
                                    placeholder="Search SKU...">
                            </div>
                            <div class="col-md-2">
                                <input type="text" id="search-order" class="form-control form-control-sm"
                                    placeholder="Search Order #...">
                            </div>
                            <div class="col-md-2">
                                <input type="text" id="search-customer" class="form-control form-control-sm"
                                    placeholder="Search Customer...">
                            </div>
                            <div class="col-md-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white">
                                        <i class="fa fa-tag text-muted" style="font-size:11px;"></i>
                                    </span>
                                    <input type="text" id="search-tag" class="form-control form-control-sm"
                                        placeholder="Search Tag... (e.g. wsaio-app)">
                                    <button class="btn btn-outline-secondary btn-sm" id="clear-tag-btn" title="Clear tag search">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                                <div id="tag-suggestions" class="mt-1 d-flex flex-wrap gap-1">
                                    <span class="badge border tag-pill" data-tag="wsaio-app"
                                          style="background:#fd7e14;color:#fff;cursor:pointer;font-size:0.72rem;">wsaio-app</span>
                                    <span class="badge border tag-pill" data-tag="checkout-via-buy-now-button"
                                          style="background:#6f42c1;color:#fff;cursor:pointer;font-size:0.72rem;">buy-now-button</span>
                                    <span class="badge border tag-pill" data-tag="shopify_draft_order"
                                          style="background:#0dcaf0;color:#000;cursor:pointer;font-size:0.72rem;">draft-order</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="shopify-raw-table" style="flex:1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

    // ── State ──────────────────────────────────────────────────────────────
    let table       = null;
    let activeSource = 'all';
    const SOURCE_COLORS = {
        'all'                          : '#343a40',
        'checkout-via-buy-now-button'  : '#6f42c1',
        'wsaio-app'                    : '#fd7e14',
        'shopify_draft_order'          : '#0dcaf0',
    };
    const SOURCE_LABELS = {
        'all'                          : 'All Sources',
        'checkout-via-buy-now-button'  : 'Buy Now Button',
        'wsaio-app'                    : 'WSAIO App',
        'shopify_draft_order'          : 'Draft Order',
    };

    // ── Helpers ────────────────────────────────────────────────────────────
    function showToast(msg, type = 'info') {
        const wrap = document.querySelector('.toast-container');
        if (!wrap) return;
        const el = document.createElement('div');
        el.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0`;
        el.setAttribute('role','alert');
        el.setAttribute('aria-live','assertive');
        el.setAttribute('aria-atomic','true');
        el.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        wrap.appendChild(el);
        new bootstrap.Toast(el).show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    function fmtMoney(v) {
        if (v == null || v === '') return '—';
        return '$' + parseFloat(v).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    }

    function fmtDate(v) {
        if (!v) return '';
        const d = new Date(v);
        return isNaN(d.getTime()) ? v : d.toLocaleDateString('en-US') + ' ' + d.toLocaleTimeString('en-US', {hour:'2-digit',minute:'2-digit'});
    }

    // ── Date pickers ───────────────────────────────────────────────────────
    const fpFrom = flatpickr('#date-from', { dateFormat: 'Y-m-d', defaultDate: new Date(Date.now() - 30*86400*1000) });
    const fpTo   = flatpickr('#date-to',   { dateFormat: 'Y-m-d', defaultDate: new Date() });

    // ── Build AJAX params ──────────────────────────────────────────────────
    function buildParams() {
        return {
            source    : activeSource,
            date_from : $('#date-from').val(),
            date_to   : $('#date-to').val(),
        };
    }

    // ── Tabulator init ─────────────────────────────────────────────────────
    function buildTable(data) {
        if (table) { table.destroy(); }

        table = new Tabulator('#shopify-raw-table', {
            data             : data,
            layout           : 'fitDataStretch',
            pagination       : true,
            paginationSize   : 100,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            paginationCounter: 'rows',
            height           : '100%',
            initialSort      : [{ column: 'order_date', dir: 'desc' }],
            langs: { default: { pagination: {
                page_size:'Show', first:'First', last:'Last', prev:'Prev', next:'Next',
                counter:{ showing:'Showing', of:'of', rows:'rows' }
            }}},
            columns: [
                {
                    title: 'Source / Tag', field: 'source_name', width: 140, frozen: true,
                    formatter: function(cell) {
                        const v   = cell.getValue() || '';
                        const row = cell.getRow().getData();
                        const tags = (row.tags || '').toLowerCase();
                        let display = v || '—';
                        let color   = '#6c757d';
                        if (v === 'shopify_draft_order' || tags.includes('shopify_draft_order')) {
                            color = '#0dcaf0'; display = 'Draft Order';
                        } else if (v === 'wsaio-app' || tags.includes('wsaio-app')) {
                            color = '#fd7e14'; display = 'WSAIO App';
                        } else if (tags.includes('checkout-via-buy-now-button')) {
                            color = '#6f42c1'; display = 'Buy Now Btn';
                        }
                        return `<span class="badge" style="background:${color};color:${color==='#0dcaf0'?'#000':'#fff'};">${display}</span>`;
                    }
                },
                {
                    title: 'Order #', field: 'order_number', width: 100, frozen: true,
                    sorter: 'string',
                },
                {
                    title: 'Order ID', field: 'order_id', width: 160, visible: false,
                },
                {
                    title: 'SKU', field: 'sku', width: 150, frozen: true,
                    headerFilter: 'input', headerFilterPlaceholder: 'Filter SKU…',
                    cssClass: 'text-primary fw-bold',
                },
                {
                    title: 'Product Title', field: 'product_title', width: 280, tooltip: true, visible: false,
                },
                {
                    title: 'Variant', field: 'variant_title', width: 150, tooltip: true, visible: false,
                },
                {
                    title: 'Qty', field: 'quantity', width: 70, hozAlign: 'center', sorter: 'number',
                },
                {
                    title: 'Price', field: 'price', width: 100, hozAlign: 'right', sorter: 'number',
                    formatter: cell => fmtMoney(cell.getValue()),
                },
                {
                    title: 'Total', field: 'total_amount', width: 110, hozAlign: 'right', sorter: 'number',
                    formatter: cell => fmtMoney(cell.getValue()),
                },
                {
                    title: 'Currency', field: 'currency', width: 80, hozAlign: 'center', visible: false,
                },
                {
                    title: 'Order Date', field: 'order_date', width: 160, sorter: 'datetime',
                    formatter: cell => fmtDate(cell.getValue()),
                },
                {
                    title: 'Fin. Status', field: 'financial_status', width: 110,
                    formatter: function(cell) {
                        const v = cell.getValue() || '';
                        return v || '—';
                    }
                },
                {
                    title: 'Fulfill. Status', field: 'fulfillment_status', width: 120,
                    formatter: function(cell) {
                        const v = cell.getValue() || '';
                        return v || '—';
                    }
                },
                {
                    title: 'Customer', field: 'customer_name', width: 160,
                    headerFilter: 'input', headerFilterPlaceholder: 'Filter…',
                },
                {
                    title: 'Email', field: 'customer_email', width: 200, tooltip: true,
                },
                {
                    title: 'City', field: 'shipping_city', width: 120,
                },
                {
                    title: 'State', field: 'shipping_state', width: 80,
                },
                {
                    title: 'Country', field: 'shipping_country', width: 90,
                },
                {
                    title: 'ZIP', field: 'shipping_zip', width: 80,
                },
                {
                    title: 'Shipping Addr.', field: 'shipping_address', width: 220, tooltip: true,
                },
                {
                    title: 'Tracking #', field: 'tracking_number', width: 160, tooltip: true,
                },
                {
                    title: 'Carrier', field: 'tracking_company', width: 120,
                },
                {
                    title: 'Tags', field: 'tags', width: 220, tooltip: true,
                    formatter: function(cell) {
                        const v = cell.getValue() || '';
                        if (!v) return '—';
                        return v.split(',').map(t => t.trim()).filter(Boolean)
                            .map(t => `<span class="badge bg-light text-dark border me-1">${t}</span>`)
                            .join('');
                    }
                },
                {
                    title: 'Discount Codes', field: 'discount_codes', width: 150, tooltip: true,
                },
                {
                    title: 'Note', field: 'note', width: 180, tooltip: true,
                },
                {
                    title: 'Channel', field: 'channel', width: 100,
                },
            ]
        });

        table.on('tableBuilt', () => {
            applyColumnVisibility();
            buildColDropdown();
        });

        table.on('dataProcessed', updateTableStats);
        table.on('renderComplete',  updateTableStats);
    }

    // ── Load data ──────────────────────────────────────────────────────────
    function loadData() {
        const params = buildParams();
        showToast('Loading raw data…', 'info');

        $.get('{{ route("shopify-raw-data.get-data") }}', params)
            .done(function(res) {
                buildTable(res.data || []);
                showToast(`Loaded ${(res.data||[]).length} records`, 'success');
                applySearchFilters();
            })
            .fail(function(xhr) {
                showToast('Error loading data: ' + (xhr.responseJSON?.message || 'Server error'), 'danger');
            });

        // Stats
        $.get('{{ route("shopify-raw-data.get-stats") }}', params)
            .done(function(res) {
                $('#stat-total-orders').text(Number(res.total_orders).toLocaleString());
                $('#stat-total-revenue').text(fmtMoney(res.total_revenue));
                $('#stat-total-qty').text(Number(res.total_qty).toLocaleString());

                const wrap = $('#per-source-stats').empty();
                const colors = {
                    'checkout-via-buy-now-button': ['#6f42c1','#fff'],
                    'wsaio-app'                  : ['#fd7e14','#fff'],
                    'shopify_draft_order'         : ['#0dcaf0','#000'],
                };
                Object.entries(res.by_source || {}).forEach(([src, d]) => {
                    const [bg, fg] = colors[src] || ['#6c757d','#fff'];
                    wrap.append(`
                        <div class="stat-card text-white" style="background:${bg};color:${fg}!important;">
                            <div class="stat-label" style="color:${fg};">${SOURCE_LABELS[src]||src}</div>
                            <div class="stat-value" style="color:${fg};">${Number(d.count).toLocaleString()} orders</div>
                            <div class="stat-sub" style="color:${fg};">${fmtMoney(d.revenue)}</div>
                        </div>`);
                });
            });
    }

    // ── Table inline stats ─────────────────────────────────────────────────
    function updateTableStats() {
        if (!table) return;
        const rows = table.getData('active');
        let orders = 0, qty = 0, rev = 0, wtPrice = 0, wtQty = 0;
        rows.forEach(r => {
            orders++;
            qty    += parseInt(r.quantity) || 0;
            rev    += parseFloat(r.total_amount) || 0;
            const q = parseInt(r.quantity) || 0;
            const p = parseFloat(r.price) || 0;
            if (q > 0 && p > 0) { wtPrice += p * q; wtQty += q; }
        });
        const avg = wtQty > 0 ? wtPrice / wtQty : 0;
        $('#tbl-orders').text('Orders: ' + orders.toLocaleString());
        $('#tbl-qty').text('Qty: ' + qty.toLocaleString());
        $('#tbl-revenue').text('Revenue: ' + fmtMoney(rev));
        $('#tbl-avg-price').text('Avg Price: ' + fmtMoney(avg));
    }

    // ── Column visibility ──────────────────────────────────────────────────
    const COL_VIS_KEY = 'shopify_raw_data_col_visibility';

    function applyColumnVisibility() {
        if (!table) return;
        try {
            const saved = JSON.parse(localStorage.getItem(COL_VIS_KEY) || '{}');
            table.getColumns().forEach(col => {
                const f = col.getDefinition().field;
                if (f && saved[f] === false) col.hide();
            });
        } catch(e) {}
    }

    function saveColumnVisibility() {
        if (!table) return;
        const vis = {};
        table.getColumns().forEach(col => {
            const f = col.getDefinition().field;
            if (f) vis[f] = col.isVisible();
        });
        localStorage.setItem(COL_VIS_KEY, JSON.stringify(vis));
    }

    function buildColDropdown() {
        if (!table) return;
        const menu = document.getElementById('col-dropdown');
        menu.innerHTML = '';
        let saved = {};
        try { saved = JSON.parse(localStorage.getItem(COL_VIS_KEY) || '{}'); } catch(e){}

        table.getColumns().forEach(col => {
            const def = col.getDefinition();
            if (!def.field) return;
            const li  = document.createElement('li');
            const lbl = document.createElement('label');
            lbl.style.cssText = 'display:block;padding:5px 12px;cursor:pointer;';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = def.field;
            cb.checked = saved[def.field] !== false;
            cb.style.marginRight = '8px';
            lbl.appendChild(cb);
            lbl.appendChild(document.createTextNode(def.title));
            li.appendChild(lbl);
            menu.appendChild(li);
        });
    }

    // ── Search filters ─────────────────────────────────────────────────────
    function applySearchFilters() {
        if (!table) return;
        table.clearFilter();
        const sku      = $('#search-sku').val().trim();
        const orderNum = $('#search-order').val().trim();
        const customer = $('#search-customer').val().trim();
        const tag      = $('#search-tag').val().trim();
        const filters  = [];
        if (sku)      filters.push({ field:'sku',           type:'like', value:sku });
        if (orderNum) filters.push({ field:'order_number',  type:'like', value:orderNum });
        if (customer) filters.push({ field:'customer_name', type:'like', value:customer });
        if (tag)      filters.push({ field:'tags',          type:'like', value:tag });
        if (filters.length) table.setFilter(filters);

        // Highlight active tag pill
        $('.tag-pill').each(function() {
            const pill = $(this).data('tag');
            $(this).css('opacity', (!tag || tag.toLowerCase().includes(pill.toLowerCase()) || pill.toLowerCase().includes(tag.toLowerCase())) ? '1' : '0.4');
        });
    }

    // ── Bootstrap ──────────────────────────────────────────────────────────
    $(document).ready(function () {

        // Initial load
        loadData();

        // Source badge clicks
        $('.source-badge').on('click', function () {
            $('.source-badge').removeClass('active');
            $(this).addClass('active');
            activeSource = $(this).data('source');
            loadData();
        });

        // Date apply / reset
        $('#apply-date-btn').on('click', loadData);
        $('#reset-date-btn').on('click', function () {
            fpFrom.setDate(new Date(Date.now() - 30*86400*1000));
            fpTo.setDate(new Date());
            loadData();
        });

        // Search inputs
        $('#search-sku, #search-order, #search-customer, #search-tag').on('keyup', applySearchFilters);

        // Quick-pick tag pills
        $(document).on('click', '.tag-pill', function() {
            const tag = $(this).data('tag');
            const current = $('#search-tag').val().trim();
            // Toggle: clicking the same pill clears it
            if (current === tag) {
                $('#search-tag').val('');
            } else {
                $('#search-tag').val(tag);
            }
            applySearchFilters();
        });

        // Clear tag button
        $('#clear-tag-btn').on('click', function() {
            $('#search-tag').val('');
            applySearchFilters();
        });

        // Column visibility dropdown changes
        document.getElementById('col-dropdown').addEventListener('change', function (e) {
            if (e.target.type !== 'checkbox' || !table) return;
            const col = table.getColumn(e.target.value);
            if (!col) return;
            e.target.checked ? col.show() : col.hide();
            saveColumnVisibility();
        });

        // Show all columns
        $('#show-all-cols-btn').on('click', function () {
            if (!table) return;
            table.getColumns().forEach(c => c.show());
            saveColumnVisibility();
            buildColDropdown();
        });

        // Export
        $('#export-btn').on('click', function () {
            if (!table) return;
            const src = activeSource === 'all' ? 'all' : activeSource.replace(/[^a-z0-9]/gi, '_');
            const from = $('#date-from').val() || 'start';
            const to   = $('#date-to').val()   || 'end';
            table.download('csv', `shopify_raw_${src}_${from}_${to}.csv`);
        });
    });
</script>
@endsection
