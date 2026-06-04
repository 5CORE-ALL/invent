@extends('layouts.vertical', ['title' => 'Shopify', 'sidenav' => 'condensed'])

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
        #shopify-table .tabulator-header .tabulator-col-title {
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
        'page_title' => 'Shopify',
        'sub_title'  => 'All Shopify order-item records',
    ])

    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999;"></div>

    {{-- Stats Row --}}
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-3" id="stat-cards">
                <div class="stat-card bg-primary text-white">
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value" id="stat-total-orders">—</div>
                    <div class="stat-sub">all sources</div>
                </div>
                <div class="stat-card bg-success text-white">
                    <div class="stat-label">Gross Sales</div>
                    <div class="stat-value" id="stat-total-revenue">—</div>
                    <div class="stat-sub">all sources</div>
                </div>
                <div class="stat-card bg-danger text-white">
                    <div class="stat-label">Total Discounts</div>
                    <div class="stat-value" id="stat-total-discount">—</div>
                    <div class="stat-sub">all sources</div>
                </div>
                <div class="stat-card bg-dark text-white">
                    <div class="stat-label">Net Sales</div>
                    <div class="stat-value" id="stat-net-sales">—</div>
                    <div class="stat-sub">gross − discounts</div>
                </div>
                <div class="stat-card bg-info text-white">
                    <div class="stat-label">Total Qty</div>
                    <div class="stat-value" id="stat-total-qty">—</div>
                    <div class="stat-sub">all sources</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4 class="mb-3">Shopify Orders</h4>

                {{-- Filter bar --}}
                <div class="d-flex align-items-center filter-bar mb-3">
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

            </div>

            <div class="card-body p-0">
                <div style="height:calc(100vh - 280px); display:flex; flex-direction:column;">
                    <div class="p-2 bg-light border-bottom">
                        <div class="d-flex gap-2 align-items-center flex-nowrap">
                            <input type="text" id="search-source" class="form-control form-control-sm" placeholder="Search Source..." style="min-width:120px;max-width:160px;">
                            <input type="text" id="search-sku" class="form-control form-control-sm" placeholder="Search SKU..." style="min-width:120px;max-width:160px;">
                            <input type="text" id="search-order" class="form-control form-control-sm" placeholder="Search Order #..." style="min-width:110px;max-width:140px;">
                            <div class="input-group input-group-sm" style="min-width:160px;max-width:200px;">
                                <span class="input-group-text bg-white"><i class="fa fa-tag text-muted" style="font-size:11px;"></i></span>
                                <input type="text" id="search-tag" class="form-control form-control-sm" placeholder="Search Tag...">
                                <button class="btn btn-outline-secondary btn-sm" id="clear-tag-btn" title="Clear"><i class="fa fa-times"></i></button>
                            </div>
                            <button class="btn btn-sm btn-warning active" id="hide-unknown-btn" title="Toggle Unknown rows">
                                <i class="fa fa-eye-slash me-1"></i>Hide Unknown
                            </button>
                        </div>
                    </div>
                    <div id="shopify-table" style="flex:1;"></div>
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

    let table        = null;
    let activeSource = 'all';
    let filterActive = false;
    let hideUnknown  = true;   // hide rows with empty tags by default
    let allData      = [];   // full dataset cache

    const SOURCE_LABELS = {
        'all'                         : 'All Sources',
        'checkout-via-buy-now-button' : 'Buy Now Button',
        'wsaio-app'                   : 'WSAIO App',
        'shopify_draft_order'         : 'Draft Order',
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

    function buildParams() {
        return {
            source    : activeSource,
            date_from : $('#date-from').val(),
            date_to   : $('#date-to').val(),
        };
    }

    // ── Tabulator ─────────────────────────────────────────────────────────
    function buildTable(data) {
        if (table) table.destroy();

        // Build unique source values for the dropdown from the loaded data
        const uniqueSources = [...new Set(
            data.map(r => (r.source_name || '').trim()).filter(Boolean)
        )].sort();
        const sourceSelectValues = [{ label: '— All Sources —', value: '' }];
        uniqueSources.forEach(s => sourceSelectValues.push({ label: s, value: s }));

        table = new Tabulator('#shopify-table', {
            data,
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
                    title: 'Source', field: 'source_name', width: 180, frozen: true,
                    headerFilter: 'select',
                    headerFilterParams: {
                        values    : sourceSelectValues,
                        clearable : true,
                    },
                    headerFilterFunc: function(headerValue, rowValue) {
                        if (!headerValue) return true;
                        return (rowValue || '').trim() === headerValue;
                    },
                    formatter: function(cell) {
                        const v    = cell.getValue() || '';
                        const tags = (cell.getRow().getData().tags || '').trim();
                        const tagsLower = tags.toLowerCase();
                        let display = v || 'Unknown', color = '#6c757d';

                        if (!tags) {
                            display = 'Unknown'; color = '#ffc107';
                        } else if (tagsLower.includes('influencer')) {
                            color = '#d63384'; display = 'Influencer';
                        } else if (v === '189863297025') {
                            color = '#e44d26'; display = 'Newegg';
                        } else if (v === '1257766') {
                            color = '#198754'; display = 'Mobile App';
                        } else if (v === '145019994113') {
                            color = '#6c757d'; display = 'Doba';
                        } else if (v === 'shopify_draft_order' || tagsLower.includes('shopify_draft_order')) {
                            color = '#0dcaf0'; display = 'Draft Order';
                        } else if (v === 'wsaio-app' || tagsLower.includes('wsaio-app')) {
                            color = '#fd7e14'; display = 'WSAIO App';
                        } else if (tagsLower.includes('checkout-via-buy-now-button')) {
                            color = '#6f42c1'; display = 'Buy Now Btn';
                        }
                        const textColor = (color === '#0dcaf0' || color === '#ffc107') ? '#000' : '#fff';
                        return `<span class="badge" style="background:${color};color:${textColor};">${display}</span>`;
                    }
                },
                { title: 'Order #',   field: 'order_number',    width: 100, frozen: true },
                { title: 'Order ID',  field: 'order_id',         width: 160, visible: false },
                {
                    title: 'SKU', field: 'sku', width: 150, frozen: true,
                    headerFilter: 'input', headerFilterPlaceholder: 'Filter SKU…',
                    cssClass: 'text-primary fw-bold',
                },
                { title: 'Product Title',   field: 'product_title',    width: 280, tooltip: true, visible: false },
                { title: 'Variant',         field: 'variant_title',    width: 150, tooltip: true, visible: false },
                { title: 'Qty',             field: 'quantity',          width: 70,  hozAlign: 'center', sorter: 'number' },
                { title: 'Price',           field: 'price',             width: 100, hozAlign: 'right',  sorter: 'number', formatter: cell => fmtMoney(cell.getValue()) },
                { title: 'Total',           field: 'total_amount',      width: 110, hozAlign: 'right',  sorter: 'number', formatter: cell => fmtMoney(cell.getValue()) },
                { title: 'Currency',        field: 'currency',          width: 80,  hozAlign: 'center', visible: false },
                {
                    title: 'Order Date', field: 'order_date', width: 160, sorter: 'datetime',
                    formatter: cell => fmtDate(cell.getValue()),
                },
                { title: 'Fin. Status',     field: 'financial_status',   width: 110, visible: false, formatter: cell => cell.getValue() || '—' },
                { title: 'Fulfill. Status', field: 'fulfillment_status', width: 120, visible: false, formatter: cell => cell.getValue() || '—' },
                { title: 'Email',          field: 'customer_email',   width: 200, tooltip: true, visible: false },
                { title: 'City',           field: 'shipping_city',    width: 120, visible: false },
                { title: 'Country',        field: 'shipping_country', width: 90,  visible: false },
                { title: 'Tracking #',     field: 'tracking_number',  width: 160, visible: false, tooltip: true },
                { title: 'Carrier',        field: 'tracking_company', width: 120, visible: false },
                {
                    title: 'Tags', field: 'tags', width: 220, tooltip: true,
                    formatter: function(cell) {
                        const v = cell.getValue() || '';
                        if (!v) return '<span class="badge" style="background:#ffc107;color:#000;">Unknown</span>';
                        return v.split(',').map(t => t.trim()).filter(Boolean)
                            .map(t => `<span class="badge bg-light text-dark border me-1">${t}</span>`)
                            .join('');
                    }
                },
                {
                    title: 'Discount Codes', field: 'discount_codes', width: 160, tooltip: true,
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (!v) return '<span class="text-muted">—</span>';
                        return v.split(',').map(c => c.trim()).filter(Boolean)
                            .map(c => `<span class="badge bg-warning text-dark me-1">${c}</span>`)
                            .join('');
                    }
                },
                {
                    title: 'Discount $', field: 'discount_amount', width: 110, hozAlign: 'right', sorter: 'number',
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue());
                        if (!v) return '<span class="text-muted">—</span>';
                        return '<span class="text-danger">-$' + v.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</span>';
                    }
                },
                {
                    title: 'Net Sales', field: 'net_sales', width: 110, hozAlign: 'right', sorter: 'number',
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue());
                        if (isNaN(v)) return '—';
                        return '<strong>$' + v.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</strong>';
                    }
                },
                {
                    title: 'Order Total', field: 'order_total', width: 115, hozAlign: 'right', sorter: 'number',
                    tooltip: 'Actual total paid for the whole order (from Shopify)',
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue());
                        if (isNaN(v) || v === 0) return '<span class="text-muted">—</span>';
                        return '<span class="badge bg-success">$' + v.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</span>';
                    }
                },
            ]
        });

        table.on('tableBuilt', () => { applyColumnVisibility(); buildColDropdown(); });
        table.on('dataLoaded', (data) => { allData = data; applySearchFilters(); updateStatCards(); });
    }

    // ── Update stat cards ──────────────────────────────────────────────────
    function updateStatCards(rows) {
        if (!rows) rows = allData;
        let orders = 0, revenue = 0, discount = 0, netSales = 0, qty = 0;
        (rows || []).forEach(r => {
            orders++;
            qty      += parseInt(r.quantity)        || 0;
            revenue  += parseFloat(r.total_amount)  || 0;
            discount += parseFloat(r.discount_amount) || 0;
            netSales += parseFloat(r.net_sales)     || 0;
        });
        $('#stat-total-orders').text(orders.toLocaleString());
        $('#stat-total-revenue').text(fmtMoney(revenue));
        $('#stat-total-discount').text(fmtMoney(discount));
        $('#stat-net-sales').text(fmtMoney(netSales));
        $('#stat-total-qty').text(qty.toLocaleString());
        const total = allData.length;
        const sub   = filterActive ? `${orders} of ${total} filtered` : 'all sources';
        // Only update the sub for orders/revenue cards, not the "gross − discounts" sub
        $('#stat-total-orders').closest('.stat-card').find('.stat-sub').text(sub);
        $('#stat-total-revenue').closest('.stat-card').find('.stat-sub').text(sub);
        $('#stat-total-discount').closest('.stat-card').find('.stat-sub').text(sub);
        $('#stat-net-sales').closest('.stat-card').find('.stat-sub').text('gross − discounts');
        $('#stat-total-qty').closest('.stat-card').find('.stat-sub').text(sub);
    }

    // ── Load data ──────────────────────────────────────────────────────────
    function loadData() {
        const params = buildParams();
        showToast('Loading…', 'info');

        $.get('{{ route("shopify-raw-data.get-data") }}', params)
            .done(function(res) {
                buildTable(res.data || []);
                showToast(`Loaded ${(res.data||[]).length} records`, 'success');
                applySearchFilters();
            })
            .fail(function(xhr) {
                showToast('Error: ' + (xhr.responseJSON?.message || 'Server error'), 'danger');
            });

    }


    // ── Column visibility ──────────────────────────────────────────────────
    const COL_VIS_KEY = 'shopify_col_visibility';

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
        try { saved = JSON.parse(localStorage.getItem(COL_VIS_KEY) || '{}'); } catch(e) {}
        table.getColumns().forEach(col => {
            const def = col.getDefinition();
            if (!def.field) return;
            const li  = document.createElement('li');
            const lbl = document.createElement('label');
            lbl.style.cssText = 'display:block;padding:5px 12px;cursor:pointer;';
            const cb = document.createElement('input');
            cb.type = 'checkbox'; cb.value = def.field;
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

        const source   = $('#search-source').val().trim().toLowerCase();
        const sku      = $('#search-sku').val().trim().toLowerCase();
        const orderNum = $('#search-order').val().trim().toLowerCase();
        const tag      = $('#search-tag').val().trim().toLowerCase();

        const hasFilter = !!(source || sku || orderNum || tag || hideUnknown);
        filterActive = hasFilter;

        table.clearFilter(true);

        // Filter function applied to Tabulator
        const matchFn = function(data) {
            const tagVal = (data.tags || '').trim();
            if (hideUnknown && tagVal === '') return false;
            if (source   && !(data.source_name  || '').toLowerCase().includes(source))   return false;
            if (sku      && !(data.sku           || '').toLowerCase().includes(sku))      return false;
            if (orderNum && !(data.order_number  || '').toLowerCase().includes(orderNum)) return false;
            if (tag) {
                if (tag === 'unknown') {
                    if (tagVal !== '') return false;
                } else {
                    if (!tagVal.toLowerCase().includes(tag)) return false;
                }
            }
            return true;
        };

        table.setFilter(matchFn);

        // Compute stats directly from allData using the same function
        updateStatCards(allData.filter(matchFn));
    }

    // ── Bootstrap ──────────────────────────────────────────────────────────
    $(document).ready(function () {
        loadData();

        $('#apply-date-btn').on('click', loadData);
        $('#reset-date-btn').on('click', function () {
            fpFrom.setDate(new Date(Date.now() - 30*86400*1000));
            fpTo.setDate(new Date());
            loadData();
        });

        $('#search-source, #search-sku, #search-order, #search-customer, #search-tag').on('keyup', applySearchFilters);

        $('#hide-unknown-btn').on('click', function () {
            hideUnknown = !hideUnknown;
            $(this).toggleClass('active', hideUnknown);
            $(this).find('i').toggleClass('fa-eye-slash', hideUnknown).toggleClass('fa-eye', !hideUnknown);
            $(this).html(hideUnknown
                ? '<i class="fa fa-eye-slash me-1"></i>Hide Unknown'
                : '<i class="fa fa-eye me-1"></i>Show Unknown');
            applySearchFilters();
        });

        $(document).on('click', '.tag-pill', function() {
            const tag     = $(this).data('tag');
            const current = $('#search-tag').val().trim();
            $('#search-tag').val(current === tag ? '' : tag);
            applySearchFilters();
        });

        $('#clear-tag-btn').on('click', function() {
            $('#search-tag').val('');
            applySearchFilters();
        });

        document.getElementById('col-dropdown').addEventListener('change', function(e) {
            if (e.target.type !== 'checkbox' || !table) return;
            const col = table.getColumn(e.target.value);
            if (!col) return;
            e.target.checked ? col.show() : col.hide();
            saveColumnVisibility();
        });

        $('#show-all-cols-btn').on('click', function () {
            if (!table) return;
            table.getColumns().forEach(c => c.show());
            saveColumnVisibility();
            buildColDropdown();
        });

        $('#export-btn').on('click', function () {
            if (!table) return;
            const src  = activeSource === 'all' ? 'all' : activeSource.replace(/[^a-z0-9]/gi, '_');
            const from = $('#date-from').val() || 'start';
            const to   = $('#date-to').val()   || 'end';
            table.download('csv', `shopify_${src}_${from}_${to}.csv`);
        });
    });
</script>
@endsection
