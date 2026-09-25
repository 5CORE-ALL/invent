@extends('layouts.vertical', ['title' => 'Views Master', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter,
        .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
            margin: 0 !important;
            overflow: hidden !important;
        }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            white-space: nowrap;
            transform: none !important;
            height: auto !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.2;
        }
        .tabulator .tabulator-header { border-bottom: 1px solid #0aa2c0; }
        .tabulator .tabulator-header .tabulator-col {
            background: #0dcaf0 !important;
            color: #fff !important;
            border-right: 1px solid rgba(255,255,255,.25) !important;
        }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content { padding: 6px 4px !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-header-filter input {
            height: 26px !important;
            font-size: 11px !important;
            border-radius: 4px;
        }
        .tabulator .tabulator-header .tabulator-col-resize-handle { display: none !important; }
        .tabulator .tabulator-cell { padding: 4px 6px !important; font-size: 12px; }
        .tabulator .tabulator-row .tabulator-cell { border-right: 1px solid #eef2f7; }
        .vm-sku-cell { font-weight: 700; color: #0d6efd; }
        .vm-views-cell { font-weight: 700; color: #0f172a; }
        .vm-channel-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            font-size: 11px;
            font-weight: 600;
            color: #334155;
            white-space: nowrap;
        }
        .vm-dil-red { color: #dc3545; font-weight: 700; }
        .vm-dil-green { color: #28a745; font-weight: 700; }
        .vm-dil-pink { color: #e83e8c; font-weight: 700; }
        .status-circle {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
            border: 1px solid rgba(0,0,0,.08);
            vertical-align: middle;
        }
        .status-circle.default { background: #94a3b8; }
        .status-circle.red { background: #a00211; }
        .status-circle.yellow { background: #ffc107; }
        .status-circle.green { background: #28a745; }
        .status-circle.pink { background: #e83e8c; }
        #vm-cvr-filter-btn .status-circle { margin-right: 6px; }
        .vm-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .vm-lbl { font-size: 12px; font-weight: 600; color: #475569; margin: 0; }
        .vm-channel-menu { max-height: 320px; overflow: auto; min-width: 220px; }
        .vm-channel-menu .dropdown-item { display: flex; align-items: center; gap: 8px; font-size: 13px; }
        #vm-table-wrapper { height: calc(100vh - 200px); }
        #vm-loading {
            position: absolute; inset: 0; background: rgba(255,255,255,.86);
            display: none; align-items: center; justify-content: center; z-index: 20;
            font-weight: 600; color: #334155;
        }
        #vm-loading.active { display: flex; }
        .vm-load-box { width: min(420px, 90%); }
        .vm-load-bar { height: 8px; border-radius: 999px; background: #e2e8f0; overflow: hidden; margin-top: 8px; }
        .vm-load-bar > span { display: block; height: 100%; width: 0; background: #0dcaf0; transition: width .2s ease; }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Views Master',
        'sub_title' => 'Channel views from Pricing Errors Fix — sites with views data only',
    ])

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1080;"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2">
                <div class="vm-toolbar">
                    <span class="badge text-bg-dark" id="vm-rows-badge">Rows: 0</span>
                    <span class="badge text-bg-secondary" id="vm-channels-badge">Sites: 0</span>
                    <span class="badge text-bg-info" id="vm-views-badge">Views: 0</span>

                    <label class="vm-lbl">Site</label>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light dropdown-toggle border" type="button"
                            id="vm-channel-filter-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            aria-expanded="false">
                            <span id="vm-channel-filter-label">All sites</span>
                        </button>
                        <ul class="dropdown-menu vm-channel-menu" id="vm-channel-menu" aria-labelledby="vm-channel-filter-btn">
                            <li>
                                <label class="dropdown-item mb-0">
                                    <input type="checkbox" class="form-check-input" id="vm-channel-all" checked>
                                    <span>All sites</span>
                                </label>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            @foreach(($channels ?? []) as $ch)
                                <li class="vm-channel-item" data-key="{{ $ch['key'] }}">
                                    <label class="dropdown-item mb-0">
                                        <input type="checkbox" class="form-check-input vm-channel-cb"
                                            value="{{ $ch['key'] }}" data-label="{{ $ch['label'] }}" checked>
                                        <span>{{ $ch['label'] }}</span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="dropdown">
                        <button class="btn btn-sm btn-light dropdown-toggle border" type="button"
                            id="vm-cvr-filter-btn" data-bs-toggle="dropdown" aria-expanded="false"
                            title="Filter by CVR color — Red &lt;1%, Yellow 1–3%, Green 3–5%, Pink 5%+">
                            <span class="status-circle default" id="vm-cvr-filter-dot"></span>
                            <span id="vm-cvr-filter-label">CVR</span>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="vm-cvr-filter-btn">
                            <li><a class="dropdown-item vm-cvr-filter-item active" href="#" data-color="all"><span class="status-circle default"></span> All CVR</a></li>
                            <li><a class="dropdown-item vm-cvr-filter-item" href="#" data-color="red"><span class="status-circle red"></span> Red (&lt;1%)</a></li>
                            <li><a class="dropdown-item vm-cvr-filter-item" href="#" data-color="yellow"><span class="status-circle yellow"></span> Yellow (1–3%)</a></li>
                            <li><a class="dropdown-item vm-cvr-filter-item" href="#" data-color="green"><span class="status-circle green"></span> Green (3–5%)</a></li>
                            <li><a class="dropdown-item vm-cvr-filter-item" href="#" data-color="pink"><span class="status-circle pink"></span> Pink (5%+)</a></li>
                        </ul>
                    </div>
                    <input type="hidden" id="vm-cvr-filter" value="all">

                    <label class="vm-lbl" for="vm-inv-filter">INV</label>
                    <select id="vm-inv-filter" class="form-select form-select-sm" style="width:72px;" title="Filter by inventory">
                        <option value="all">All</option>
                        <option value="eq_0">= 0</option>
                        <option value="gt_0">&gt; 0</option>
                    </select>

                    <label class="vm-lbl" for="vm-ovl30-filter">OVL30</label>
                    <select id="vm-ovl30-filter" class="form-select form-select-sm" style="width:72px;" title="Filter by overall L30">
                        <option value="all">All</option>
                        <option value="eq_0">= 0</option>
                        <option value="gt_0">&gt; 0</option>
                    </select>

                    <label class="vm-lbl" for="vm-parent-search">Parent</label>
                    <input type="search" id="vm-parent-search" class="form-control form-control-sm" style="width:140px;"
                        placeholder="Parent">

                    <label class="vm-lbl" for="vm-sku-search">SKU</label>
                    <input type="search" id="vm-sku-search" class="form-control form-control-sm" style="width:160px;"
                        placeholder="SKU">

                    <button type="button" class="btn btn-sm btn-outline-primary" id="vm-reload-btn">
                        <i class="fas fa-sync-alt"></i> Reload
                    </button>
                </div>
            </div>
            <div class="card-body p-0 position-relative">
                <div id="vm-loading" class="active" aria-live="polite">
                    <div class="vm-load-box">
                        <div><i class="fas fa-spinner fa-spin me-2"></i><span id="vm-load-msg">Loading views…</span></div>
                        <div class="vm-load-bar"><span id="vm-load-bar"></span></div>
                    </div>
                </div>
                <div id="vm-table-wrapper">
                    <div id="vm-table"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
(function() {
    let table = null;
    let allRows = [];
    let loadTimer = null;

    function toast(msg, type) {
        const bg = type === 'error' ? 'text-bg-danger' : 'text-bg-success';
        const id = 'vm-toast-' + Date.now();
        $('.toast-container').append(
            '<div id="' + id + '" class="toast align-items-center ' + bg + ' border-0" role="alert">'
            + '<div class="d-flex"><div class="toast-body">' + msg + '</div>'
            + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>'
        );
        const el = document.getElementById(id);
        if (el && window.bootstrap && bootstrap.Toast) {
            new bootstrap.Toast(el, { delay: 4000 }).show();
        }
    }

    function setLoad(pct, msg) {
        $('#vm-load-bar').css('width', Math.max(0, Math.min(100, pct)) + '%');
        if (msg) $('#vm-load-msg').text(msg);
    }

    function showLoad() {
        $('#vm-loading').addClass('active');
        setLoad(8, 'Loading views…');
        clearInterval(loadTimer);
        let pct = 8;
        loadTimer = setInterval(function() {
            pct = Math.min(88, pct + 3);
            setLoad(pct);
        }, 400);
    }

    function hideLoad() {
        clearInterval(loadTimer);
        setLoad(100, 'Done');
        setTimeout(function() { $('#vm-loading').removeClass('active'); }, 250);
    }

    function fmtInt(v) {
        const n = Number(v);
        if (!isFinite(n)) return '';
        return Math.round(n).toLocaleString();
    }

    function dilClass(n) {
        if (n < 25) return 'vm-dil-red';
        if (n < 50) return 'vm-dil-green';
        return 'vm-dil-pink';
    }

    /** Same CVR bands as /price-increase: Red &lt;1, Yellow 1–3, Green 3–5, Pink 5%+. */
    function rowCvr(d) {
        let n = Number(d && d.cvr);
        if (!isFinite(n)) {
            const views = Number(d && d.views) || 0;
            const l30 = Number(d && d.l30) || 0;
            n = views > 0 ? (l30 / views) * 100 : 0;
        }
        return isFinite(n) ? n : 0;
    }

    function cvrColorBand(n) {
        if (n < 1) return 'red';
        if (n < 3) return 'yellow';
        if (n < 5) return 'green';
        return 'pink';
    }

    function cvrColorHex(band) {
        if (band === 'red') return '#a00211';
        if (band === 'yellow') return '#ffc107';
        if (band === 'green') return '#28a745';
        if (band === 'pink') return '#e83e8c';
        return '#334155';
    }

    /** Same color bands as analytics pages (metric-percent-colors.js). */
    function metricHtml(kind, value) {
        const n = Number(value);
        if (!isFinite(n)) return '<span class="text-muted">—</span>';
        if (window.MetricPctColors && typeof MetricPctColors.htmlFor === 'function') {
            return MetricPctColors.htmlFor(kind, n, { decimals: 0, empty: '—' });
        }
        return Math.round(n) + '%';
    }

    function metricColumn(title, field, kind, tip) {
        return {
            title: title,
            field: field,
            width: 72,
            hozAlign: 'center',
            vertAlign: 'middle',
            headerSort: true,
            sorter: 'number',
            sorterParams: { alignEmptyValues: 'bottom' },
            headerTooltip: tip,
            formatter: function(cell) { return metricHtml(kind, cell.getValue()); },
        };
    }

    function selectedChannels() {
        const keys = [];
        $('.vm-channel-cb:checked').each(function() { keys.push(String($(this).val())); });
        return keys;
    }

    function syncChannelLabel() {
        const $boxes = $('.vm-channel-cb');
        const total = $boxes.length;
        const selected = selectedChannels();
        const allOn = total > 0 && selected.length === total;
        let label = 'All sites';
        if (total === 0) label = 'No sites';
        else if (!allOn && selected.length === 0) label = 'None';
        else if (!allOn && selected.length === 1) {
            label = $boxes.filter(':checked').first().data('label') || selected[0];
        } else if (!allOn) label = selected.length + ' sites';
        $('#vm-channel-filter-label').text(label);
        $('#vm-channel-all').prop('checked', allOn);
        $('#vm-channel-all').prop('indeterminate', selected.length > 0 && !allOn);
    }

    function rebuildChannelMenu(channels) {
        const keep = {};
        selectedChannels().forEach(function(k) { keep[k] = true; });
        const hadSelection = Object.keys(keep).length > 0;
        $('#vm-channel-menu .vm-channel-item').remove();
        (channels || []).forEach(function(ch) {
            const key = String(ch.key || '');
            const label = String(ch.label || key);
            if (!key) return;
            const checked = !hadSelection || keep[key] ? 'checked' : '';
            $('#vm-channel-menu').append(
                '<li class="vm-channel-item" data-key="' + key.replace(/"/g, '&quot;') + '">'
                + '<label class="dropdown-item mb-0">'
                + '<input type="checkbox" class="form-check-input vm-channel-cb" value="'
                + key.replace(/"/g, '&quot;') + '" data-label="' + label.replace(/"/g, '&quot;') + '" ' + checked + '>'
                + '<span>' + $('<span>').text(label).html() + '</span>'
                + '</label></li>'
            );
        });
        syncChannelLabel();
    }

    function rowMatches(d) {
        const boxes = $('.vm-channel-cb');
        const keys = selectedChannels();
        const pull = String(d.pull_key || '');
        if (boxes.length && keys.indexOf(pull) === -1) return false;
        const cvrFilter = String($('#vm-cvr-filter').val() || 'all');
        if (cvrFilter !== 'all' && cvrColorBand(rowCvr(d)) !== cvrFilter) return false;
        const parentQ = String($('#vm-parent-search').val() || '').trim().toLowerCase();
        const skuQ = String($('#vm-sku-search').val() || '').trim().toLowerCase();
        if (parentQ && String(d.parent || '').toLowerCase().indexOf(parentQ) === -1) return false;
        if (skuQ && String(d.sku || '').toLowerCase().indexOf(skuQ) === -1) return false;
        const inv = Number(d.inv) || 0;
        const ovl30 = Number(d.ov_l30) || 0;
        const invFilter = String($('#vm-inv-filter').val() || 'all');
        const ovl30Filter = String($('#vm-ovl30-filter').val() || 'all');
        if (invFilter === 'eq_0' && inv !== 0) return false;
        if (invFilter === 'gt_0' && !(inv > 0)) return false;
        if (ovl30Filter === 'eq_0' && ovl30 !== 0) return false;
        if (ovl30Filter === 'gt_0' && !(ovl30 > 0)) return false;
        return true;
    }

    function applyFilter() {
        if (!table) return;
        const rows = allRows.filter(rowMatches);
        table.setData(rows);
        let views = 0;
        rows.forEach(function(r) { views += Number(r.views) || 0; });
        $('#vm-rows-badge').text('Rows: ' + rows.length.toLocaleString());
        $('#vm-views-badge').text('Views: ' + Math.round(views).toLocaleString());
    }

    function initTable() {
        table = new Tabulator('#vm-table', {
            data: [],
            layout: 'fitDataFill',
            height: '100%',
            pagination: true,
            paginationMode: 'local',
            sortMode: 'local',
            paginationSize: 100,
            paginationSizeSelector: [100, 200, 500, 1000, true],
            paginationCounter: 'rows',
            renderHorizontal: 'basic',
            renderVertical: 'virtual',
            initialSort: [{ column: 'views', dir: 'desc' }],
            columnDefaults: { resizable: false, minWidth: 40, headerSort: false },
            columns: [
                {
                    title: 'Parent', field: 'parent', width: 120, hozAlign: 'left', vertAlign: 'middle',
                    headerFilter: 'input', headerFilterPlaceholder: 'Parent',
                },
                {
                    title: 'SKU', field: 'sku', width: 170, hozAlign: 'left', vertAlign: 'middle',
                    cssClass: 'vm-sku-cell', headerFilter: 'input', headerFilterPlaceholder: 'SKU',
                },
                {
                    title: 'Site', field: 'channel', width: 120, hozAlign: 'center', vertAlign: 'middle',
                    formatter: function(cell) {
                        const v = cell.getValue() || '';
                        return v ? '<span class="vm-channel-badge">' + v + '</span>' : '';
                    },
                },
                {
                    title: 'Views', field: 'views', width: 90, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number', cssClass: 'vm-views-cell',
                    headerTooltip: 'Channel views — same number as /pricing-errors-fix',
                    formatter: function(cell) { return fmtInt(cell.getValue()); },
                },
                {
                    title: 'L30', field: 'l30', width: 70, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number',
                    headerTooltip: 'Units sold on this site in the last 30 days',
                    formatter: function(cell) { return fmtInt(cell.getValue()); },
                },
                {
                    title: 'CVR', field: 'cvr', width: 70, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number',
                    headerTooltip: 'L30 ÷ Views × 100. Red <1%, Yellow 1–3%, Green 3–5%, Pink 5%+',
                    formatter: function(cell) {
                        const n = rowCvr(cell.getRow().getData() || {});
                        const band = cvrColorBand(n);
                        const label = (Math.round(n * 10) / 10).toFixed(1) + '%';
                        return '<span style="color:' + cvrColorHex(band) + ';font-weight:700;">' + label + '</span>';
                    },
                },
                metricColumn('GROI', 'groi', 'groi', 'GROI% from the channel analytics page. Red <60, Yellow 60–90, Green 90–150, Pink 150+.'),
                metricColumn('GPFT', 'gpft', 'gpft', 'GPFT% from the channel analytics page. Red ≤20, Yellow 20–30, Green 30–43, Pink 43+.'),
                {
                    title: 'Ads', field: 'ads_pct', width: 68, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number',
                    headerTooltip: 'Channel Ads%. Green <5%, Yellow 5–10%, Red above 10%.',
                    formatter: function(cell) {
                        const n = Number(cell.getValue());
                        if (!isFinite(n)) return '<span class="text-muted">—</span>';
                        const color = n < 5 ? '#28a745' : (n <= 10 ? '#ffc107' : '#a00211');
                        return '<span style="color:' + color + ';font-weight:700;">' + (Math.round(n * 10) / 10).toFixed(1) + '%</span>';
                    },
                },
                metricColumn('NROI', 'nroi', 'nroi', 'NROI% from the channel analytics page. Red <40, Yellow 40–70, Green 70–125, Pink 125+.'),
                metricColumn('NPFT', 'npft', 'npft', 'NPFT% from the channel analytics page. Red ≤10, Yellow 10–20, Green 20–33, Pink 33+.'),
                {
                    title: 'INV', field: 'inv', width: 70, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number',
                    headerTooltip: 'Overall inventory — same as the analytics pages',
                    formatter: function(cell) { return fmtInt(cell.getValue()); },
                },
                {
                    title: 'OVL30', field: 'ov_l30', width: 72, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number',
                    headerTooltip: 'Overall Shopify L30 (all channels)',
                    formatter: function(cell) { return fmtInt(cell.getValue()); },
                },
                {
                    title: 'Dil%', field: 'dil', width: 70, hozAlign: 'center', vertAlign: 'middle',
                    headerSort: true, sorter: 'number',
                    headerTooltip: 'Red <25%, Green 25–50%, Pink 50%+',
                    formatter: function(cell) {
                        const n = Math.round(Number(cell.getValue()));
                        if (!isFinite(n)) return '';
                        return '<span class="' + dilClass(n) + '">' + n + '%</span>';
                    },
                },
                {
                    title: 'Price', field: 'price', width: 90, hozAlign: 'right', vertAlign: 'middle',
                    headerSort: true, sorter: 'number',
                    formatter: function(cell) {
                        const n = Number(cell.getValue());
                        if (!isFinite(n) || !(n > 0)) return '<span class="text-muted">—</span>';
                        return '$' + n.toFixed(2);
                    },
                },
            ],
        });
    }

    async function loadData() {
        $('#vm-reload-btn').prop('disabled', true);
        showLoad();
        try {
            const resp = await $.ajax({
                url: @json(route('views.master.data')),
                method: 'GET',
                dataType: 'json',
                timeout: 180000,
            });
            allRows = Array.isArray(resp) ? resp : (resp.data || []);
            const channels = (resp && resp.channels) ? resp.channels : [];
            rebuildChannelMenu(channels);
            $('#vm-channels-badge').text('Sites: ' + channels.length);
            if (!table) initTable();
            applyFilter();
            if (table) table.setSort([{ column: 'views', dir: 'desc' }]);
            if (!allRows.length) toast('No sites with views data', 'error');
        } catch (xhr) {
            const msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'error';
            toast('Load failed: ' + msg, 'error');
        }
        hideLoad();
        $('#vm-reload-btn').prop('disabled', false);
    }

    $(document).on('change', '#vm-channel-all', function() {
        $('.vm-channel-cb').prop('checked', $(this).is(':checked'));
        syncChannelLabel();
        applyFilter();
    });
    $(document).on('change', '.vm-channel-cb', function() {
        syncChannelLabel();
        applyFilter();
    });
    $(document).on('click', '.vm-channel-menu', function(e) { e.stopPropagation(); });
    $(document).on('click', '.vm-cvr-filter-item', function(e) {
        e.preventDefault();
        const color = String($(this).data('color') || 'all');
        const label = color === 'all' ? 'CVR' : $(this).text().trim();
        $('#vm-cvr-filter').val(color);
        $('#vm-cvr-filter-label').text(label);
        $('#vm-cvr-filter-dot').attr('class', 'status-circle ' + (color === 'all' ? 'default' : color));
        $('.vm-cvr-filter-item').removeClass('active');
        $(this).addClass('active');
        applyFilter();
    });
    $('#vm-parent-search, #vm-sku-search').on('input', applyFilter);
    $('#vm-inv-filter, #vm-ovl30-filter').on('change', applyFilter);
    $('#vm-reload-btn').on('click', loadData);

    initTable();
    loadData();
})();
</script>
@endsection
