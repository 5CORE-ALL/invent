@extends('layouts.vertical', ['title' => 'Amz Buybox', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #amz-bb-wrap .tabulator { border: 1px solid #dee2e6; border-radius: 8px; font-size: 12px; }
        #amz-bb-wrap .tabulator .tabulator-header { background: #f8f9fa; }
        #amz-bb-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            white-space: normal !important; font-size: 11px; font-weight: 600; text-align: center; line-height: 1.2; padding: 4px 2px;
        }
        #amz-bb-wrap .tabulator .tabulator-cell { padding: 3px 4px !important; }
        #amz-bb-wrap .tabulator-row.tabulator-selected { background: #e7f1ff !important; }
        .amz-bb-yn-yes { color: #16a34a; font-weight: 700; }
        .amz-bb-yn-no { color: #dc2626; font-weight: 700; }
        .amz-bb-thumb { width: 36px; height: 36px; object-fit: contain; border-radius: 4px; background: #fff; }

        /* Column visibility — 5 columns (same pattern as amazon-tabulator-view) */
        .column-dropdown-multicol {
            min-width: 720px;
            max-height: 70vh;
            overflow-y: auto;
            padding: 6px 4px;
            column-count: 5;
            column-gap: 4px;
        }
        .column-dropdown-multicol > li {
            break-inside: avoid;
            -webkit-column-break-inside: avoid;
            page-break-inside: avoid;
        }
        .column-dropdown-multicol .dropdown-item {
            padding: 3px 8px;
            white-space: nowrap;
            font-size: 12px;
        }
        .column-dropdown-multicol .dropdown-item input {
            margin-right: 6px;
            vertical-align: middle;
        }
        @media (max-width: 992px) {
            .column-dropdown-multicol { min-width: 480px; column-count: 3; }
        }
        @media (max-width: 768px) {
            .column-dropdown-multicol { min-width: 320px; column-count: 2; }
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amz Buybox',
        'sub_title'  => 'Amazon SP-API Buy Box',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span id="amz-bb-total" class="badge bg-secondary">Total: —</span>
                        <span id="amz-bb-selected" class="badge bg-primary">Selected: 0</span>
                        <span id="amz-bb-cached" class="badge bg-info text-dark">Cached: —</span>
                        <span id="amz-bb-pull-count" class="badge bg-warning text-dark d-none">Fetched: —</span>
                        <button type="button" id="amz-bb-refresh-btn" class="btn btn-sm btn-outline-primary" title="Reload table">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="amz-bb-pull-btn" class="btn btn-sm btn-warning text-dark"
                            title="Start background cron: pull Buy Box in lots of 40 (skips INV &lt; 1)">
                            <i class="fas fa-cloud-download-alt me-1"></i> Pull Buy Box
                        </button>

                        {{-- Column hide/show — auto-saves to channel_tabulator_column_settings --}}
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                                id="amzBbColumnVisibilityDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                aria-expanded="false" title="Show / hide columns">
                                <i class="fas fa-columns"></i>
                            </button>
                            <ul class="dropdown-menu column-dropdown-multicol" id="amz-bb-column-dropdown-menu"
                                aria-labelledby="amzBbColumnVisibilityDropdown">
                                {{-- Populated dynamically --}}
                            </ul>
                        </div>

                        <span class="text-muted small" id="amz-bb-status-line">
                            Pull runs in background · 40 SKUs/lot · skips INV &lt; 1
                        </span>
                    </div>

                    <div id="amz-bb-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="amz-bb-search" class="form-control form-control-sm"
                                placeholder="Search Parent / SKU / ASIN..." autocomplete="off" style="max-width: 320px;">
                            <label class="small text-muted mb-0 d-flex align-items-center gap-1">
                                <input type="checkbox" id="amz-bb-inv-gt0" class="form-check-input m-0"> INV &gt; 0
                            </label>
                        </div>
                        <div id="amz-buybox-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        /** Persisted in channel_tabulator_column_settings (same as amazon-tabulator-view). */
        const TABULATOR_COLUMN_CHANNEL = 'amz_buybox';
        const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';

        let amzBbTable = null;

        function amzBbEsc(s) {
            return String(s ?? '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function amzBbMoney(v) {
            const n = parseFloat(v);
            if (!isFinite(n) || n <= 0) return '<span class="text-muted">—</span>';
            return '$' + n.toFixed(2);
        }

        function amzBbNum(v) {
            if (v === null || v === undefined || v === '') return '<span class="text-muted">—</span>';
            const n = Number(v);
            if (!isFinite(n)) return amzBbEsc(v);
            return String(n);
        }

        function amzBbYn(v) {
            if (v === null || v === undefined || v === '') return '<span class="text-muted">—</span>';
            const yes = v === true || v === 1 || v === '1' || v === 'true';
            return yes
                ? '<span class="amz-bb-yn-yes">Yes</span>'
                : '<span class="amz-bb-yn-no">No</span>';
        }

        function amzBbText(v) {
            if (v === null || v === undefined || v === '') return '<span class="text-muted">—</span>';
            return amzBbEsc(v);
        }

        /*
         * Column visibility persists in `channel_tabulator_column_settings`
         * under channel = 'amz_buybox' via /tabulator-column-visibility
         * (same endpoint as amazon-tabulator-view).
         */
        function amzBbBuildColumnDropdown() {
            const menu = document.getElementById('amz-bb-column-dropdown-menu');
            if (!menu || !amzBbTable) return;
            menu.innerHTML = '';

            fetch(TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
            })
                .then(res => res.json())
                .then(savedVisibility => {
                    const map = (savedVisibility && typeof savedVisibility === 'object') ? savedVisibility : {};
                    amzBbTable.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        const field = def.field;
                        if (!field) return;

                        const isVisible = map.hasOwnProperty(field) ? (map[field] !== false) : col.isVisible();
                        const li = document.createElement('li');
                        li.innerHTML =
                            '<label class="dropdown-item"><input type="checkbox" ' +
                            (isVisible ? 'checked' : '') +
                            ' data-field="' + amzBbEsc(field) + '"> ' +
                            amzBbEsc(def.title || field) +
                            '</label>';
                        menu.appendChild(li);
                    });
                })
                .catch(err => console.error('Error loading column visibility:', err));
        }

        function amzBbSaveColumnVisibilityToServer() {
            if (!amzBbTable) return;
            const visibility = {};
            amzBbTable.getColumns().forEach(col => {
                const field = col.getDefinition().field;
                if (field) visibility[field] = col.isVisible();
            });

            fetch(TABULATOR_COLUMN_VISIBILITY_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
                body: JSON.stringify({
                    channel: TABULATOR_COLUMN_CHANNEL,
                    visibility: visibility,
                }),
            }).catch(err => console.error('Error saving column visibility:', err));
        }

        function amzBbApplyColumnVisibilityFromServer() {
            if (!amzBbTable) return;
            fetch(TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
            })
                .then(res => res.json())
                .then(savedVisibility => {
                    if (!savedVisibility || typeof savedVisibility !== 'object') return;
                    amzBbTable.getColumns().forEach(col => {
                        const field = col.getDefinition().field;
                        if (field && savedVisibility.hasOwnProperty(field)) {
                            if (savedVisibility[field]) col.show();
                            else col.hide();
                        }
                    });
                })
                .catch(err => console.error('Error applying column visibility:', err));
        }

        function moneyCol(title, field, width) {
            return { title: title, field: field, hozAlign: 'right', width: width || 90, formatter: c => amzBbMoney(c.getValue()) };
        }
        function numCol(title, field, width) {
            return { title: title, field: field, hozAlign: 'center', width: width || 70, formatter: c => amzBbNum(c.getValue()) };
        }
        function ynCol(title, field, width) {
            return { title: title, field: field, hozAlign: 'center', width: width || 70, formatter: c => amzBbYn(c.getValue()) };
        }
        function textCol(title, field, width) {
            return { title: title, field: field, hozAlign: 'center', width: width || 90, formatter: c => amzBbText(c.getValue()) };
        }

        function amzBbUpdateCounts() {
            if (!amzBbTable) return;
            const shown = amzBbTable.getDataCount('active');
            const total = amzBbTable.getDataCount();
            $('#amz-bb-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            $('#amz-bb-selected').text('Selected: ' + amzBbTable.getSelectedData().length);
        }

        function amzBbApplyFilters() {
            if (!amzBbTable) return;
            const q = ($('#amz-bb-search').val() || '').toString().trim().toLowerCase();
            const invGt0 = $('#amz-bb-inv-gt0').is(':checked');
            amzBbTable.setFilter(function(data) {
                if (invGt0 && !(parseFloat(data.inv) > 0)) return false;
                if (!q) return true;
                const hay = [data.parent, data.sku, data.asin].map(v => String(v || '').toLowerCase()).join(' ');
                return hay.indexOf(q) !== -1;
            });
            amzBbUpdateCounts();
        }

        function initAmzBuyboxTable() {
            amzBbTable = new Tabulator('#amz-buybox-table', {
                height: '70vh',
                layout: 'fitDataStretch',
                placeholder: 'Loading…',
                selectableRows: true,
                selectableRowsRangeMode: 'click',
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [50, 100, 250, 500],
                ajaxURL: @json(route('amz.buybox.data')),
                ajaxConfig: 'GET',
                ajaxResponse: function(url, params, response) {
                    if (response && response.meta) {
                        $('#amz-bb-cached').text('Cached: ' + (response.meta.buybox_cached || 0).toLocaleString());
                        if (response.meta.refreshed_at) {
                            $('#amz-bb-status-line').text('Loaded · ' + response.meta.refreshed_at + ' · SP-API getListingOffers columns');
                        }
                    }
                    return (response && response.data) ? response.data : [];
                },
                columns: [
                    {
                        formatter: 'rowSelection',
                        titleFormatter: 'rowSelection',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 40,
                        frozen: true,
                    },
                    {
                        title: 'Image',
                        field: 'image',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 52,
                        frozen: true,
                        formatter: function(cell) {
                            const url = cell.getValue();
                            if (!url) return '<span class="text-muted">—</span>';
                            return '<img class="amz-bb-thumb" src="' + amzBbEsc(url) + '" alt="">';
                        },
                    },
                    { title: 'Parent', field: 'parent', width: 110, frozen: true },
                    { title: 'SKU', field: 'sku', width: 120, frozen: true },
                    {
                        title: 'INV',
                        field: 'inv',
                        hozAlign: 'center',
                        width: 60,
                        frozen: true,
                        formatter: function(cell) {
                            const n = parseFloat(cell.getValue()) || 0;
                            return Math.round(n).toLocaleString('en-US');
                        },
                    },
                    {
                        title: 'OV L30',
                        field: 'ov_l30',
                        hozAlign: 'center',
                        width: 60,
                        sorter: 'number',
                        formatter: function(cell) {
                            return Math.round(parseFloat(cell.getValue()) || 0).toLocaleString('en-US');
                        },
                    },
                    {
                        title: 'Dil',
                        field: 'dil_pct',
                        hozAlign: 'center',
                        width: 55,
                        sorter: 'number',
                        headerTooltip: 'Dil% = OV L30 / INV × 100',
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const inv = parseFloat(row.inv) || 0;
                            const ov = parseFloat(row.ov_l30) || 0;
                            if (inv <= 0) return '<span style="color:#6c757d;">0%</span>';
                            const dil = (ov / inv) * 100;
                            let color = '#e83e8c';
                            if (dil < 16.66) color = '#a00211';
                            else if (dil < 25) color = '#ffc107';
                            else if (dil < 50) color = '#28a745';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                        },
                    },
                    {
                        title: 'Amz L30',
                        field: 'amz_l30',
                        hozAlign: 'center',
                        width: 65,
                        sorter: 'number',
                        headerTooltip: 'Amazon units ordered L30 (A L30)',
                        formatter: function(cell) {
                            return Math.round(parseFloat(cell.getValue()) || 0).toLocaleString('en-US');
                        },
                    },

                    // —— Amazon SP-API Buy Box / Listing Offers ——
                    Object.assign(textCol('ASIN', 'asin', 100), { visible: false }),
                    textCol('Status', 'status', 90),
                    Object.assign(textCol('Condition', 'item_condition', 80), { visible: false }),
                    numCol('Total Offers', 'total_offer_count', 80),
                    numCol('Offers AMZ', 'offer_count_amazon', 75),
                    numCol('Offers MFN', 'offer_count_merchant', 75),
                    ynCol('BB Winner (Us)', 'is_buy_box_winner', 90),
                    ynCol('My Offer', 'my_offer', 70),
                    moneyCol('BB Listing', 'buybox_listing_price'),
                    moneyCol('BB Landed', 'buybox_landed_price'),
                    moneyCol('BB Ship', 'buybox_shipping', 75),
                    Object.assign(textCol('BB Currency', 'buybox_currency', 70), { visible: false }),
                    moneyCol('Lowest Listing', 'lowest_listing_price'),
                    moneyCol('Lowest Landed', 'lowest_landed_price'),
                    moneyCol('Lowest Ship', 'lowest_shipping', 80),
                    textCol('Lowest Channel', 'lowest_fulfillment_channel', 90),
                    moneyCol('List Price', 'list_price'),
                    moneyCol('Comp. Threshold', 'competitive_price_threshold', 100),
                    moneyCol('Suggested Lower+Ship', 'suggested_lower_price_plus_shipping', 120),
                    moneyCol('Our Listing', 'our_listing_price'),
                    moneyCol('Our Ship', 'our_shipping', 75),
                    moneyCol('Our Landed', 'our_landed_price'),
                    ynCol('Our FBA', 'is_fulfilled_by_amazon', 70),
                    ynCol('Featured Merchant', 'is_featured_merchant', 90),
                    ynCol('Prime', 'is_prime', 60),
                    ynCol('National Prime', 'is_national_prime', 90),
                    textCol('Our SubCond', 'our_subcondition', 80),
                    numCol('Our Feedback %', 'our_feedback_rating', 90),
                    numCol('Our Feedback #', 'our_feedback_count', 90),
                    numCol('Ship Min Hrs', 'our_ship_min_hours', 80),
                    numCol('Ship Max Hrs', 'our_ship_max_hours', 80),
                    Object.assign(textCol('Ships From', 'our_ships_from_country', 75), { visible: false }),
                    textCol('BB Seller ID', 'bb_seller_id', 110),
                    ynCol('BB FBA', 'bb_is_fulfilled_by_amazon', 65),
                    ynCol('BB Featured', 'bb_is_featured_merchant', 80),
                    ynCol('BB Prime', 'bb_is_prime', 70),
                    moneyCol('BB Offer Listing', 'bb_listing_price'),
                    moneyCol('BB Offer Ship', 'bb_shipping', 85),
                    moneyCol('BB Offer Landed', 'bb_landed_price'),
                    numCol('BB Feedback %', 'bb_feedback_rating', 90),
                    numCol('BB Feedback #', 'bb_feedback_count', 90),
                    textCol('BB SubCond', 'bb_subcondition', 80),
                    textCol('BB Ships From', 'bb_ships_from_country', 85),
                    numCol('Sales Rank', 'sales_rank', 80),
                    textCol('Rank Category', 'sales_rank_category', 110),
                    textCol('Fetched At', 'fetched_at', 120),
                    {
                        title: 'Error',
                        field: 'error_message',
                        width: 180,
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (!v) return '<span class="text-muted">—</span>';
                            return '<span class="text-danger" title="' + amzBbEsc(v) + '">' + amzBbEsc(String(v).substring(0, 80)) + '</span>';
                        },
                    },
                ],
            });

            amzBbTable.on('dataProcessed', amzBbUpdateCounts);
            amzBbTable.on('rowSelectionChanged', amzBbUpdateCounts);
            amzBbTable.on('pageLoaded', amzBbUpdateCounts);

            amzBbTable.on('tableBuilt', function() {
                requestAnimationFrame(function() {
                    amzBbApplyColumnVisibilityFromServer();
                    amzBbBuildColumnDropdown();
                });
            });
        }

        $(function() {
            initAmzBuyboxTable();

            $('#amz-bb-search').on('keyup input', function() { amzBbApplyFilters(); });
            $('#amz-bb-inv-gt0').on('change', function() { amzBbApplyFilters(); });

            const colMenu = document.getElementById('amz-bb-column-dropdown-menu');

            if (colMenu) {
                colMenu.addEventListener('change', function(e) {
                    if (e.target.type !== 'checkbox' || !amzBbTable) return;
                    const field = e.target.getAttribute('data-field');
                    const col = amzBbTable.getColumn(field);
                    if (!col) return;
                    if (e.target.checked) col.show();
                    else col.hide();
                    amzBbSaveColumnVisibilityToServer();
                });
            }

            $('#amz-bb-refresh-btn').on('click', function() {
                if (amzBbTable) amzBbTable.replaceData();
            });

            let amzBbPullPollTimer = null;

            function amzBbApplyPullStatus(st) {
                if (!st) return;
                const msg = st.message || '';
                const done = Number(st.done || 0);
                const total = Number(st.total || 0);
                const ok = Number(st.ok || 0);
                const fail = Number(st.fail || 0);
                const lotIndex = st.lot_index || 0;
                const lots = st.lots || '?';
                const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
                const countLabel = total > 0
                    ? (done.toLocaleString() + ' / ' + total.toLocaleString())
                    : String(done);

                if (st.running) {
                    $('#amz-bb-pull-count')
                        .removeClass('d-none')
                        .text('Fetched: ' + countLabel + ' (' + pct + '%) · ok ' + ok + ' · fail ' + fail);
                    const lot = lotIndex ? ('Lot ' + lotIndex + '/' + lots + ' · ') : '';
                    $('#amz-bb-status-line').text(
                        lot + 'Fetched ' + countLabel + ' · ok ' + ok + ' · fail ' + fail
                        + (msg ? ' · ' + msg : '')
                    );
                    $('#amz-bb-pull-btn').prop('disabled', true)
                        .html('<i class="fa fa-spinner fa-spin me-1"></i> Pulling… ' + countLabel);
                } else {
                    if (total > 0 || done > 0) {
                        $('#amz-bb-pull-count')
                            .removeClass('d-none')
                            .text('Last pull: ' + countLabel + ' · ok ' + ok + ' · fail ' + fail);
                    } else {
                        $('#amz-bb-pull-count').addClass('d-none').text('Fetched: —');
                    }
                    $('#amz-bb-status-line').text(msg || 'Pull idle');
                    $('#amz-bb-pull-btn').prop('disabled', false)
                        .html('<i class="fas fa-cloud-download-alt me-1"></i> Pull Buy Box');
                }
            }

            function amzBbStopPullPoll() {
                if (amzBbPullPollTimer) {
                    clearInterval(amzBbPullPollTimer);
                    amzBbPullPollTimer = null;
                }
            }

            function amzBbStartPullPoll() {
                amzBbStopPullPoll();
                amzBbPullPollTimer = setInterval(function() {
                    $.getJSON(@json(route('amz.buybox.pull.status')))
                        .done(function(resp) {
                            const st = resp && resp.status ? resp.status : {};
                            amzBbApplyPullStatus(st);
                            if (!st.running) {
                                amzBbStopPullPoll();
                                if (amzBbTable) amzBbTable.replaceData();
                            }
                        });
                }, 3000);
            }

            // Resume progress UI if a pull is already running
            $.getJSON(@json(route('amz.buybox.pull.status'))).done(function(resp) {
                const st = resp && resp.status ? resp.status : {};
                amzBbApplyPullStatus(st);
                if (st.running) amzBbStartPullPoll();
            });

            $('#amz-bb-pull-btn').on('click', function() {
                if (!amzBbTable) return;
                const selected = amzBbTable.getSelectedData().map(r => r.sku).filter(Boolean);
                // Selection optional: empty = all INV ≥ 1. Clear selection if you meant everything.
                const scopeMsg = selected.length
                    ? (selected.length + ' selected SKU(s) (INV < 1 omitted)')
                    : 'all SKUs with INV ≥ 1';
                if (!confirm(
                    'Start background Buy Box pull for ' + scopeMsg + '?\n\n' +
                    '· Lots of 40 SKUs\n' +
                    '· Skips INV < 1\n' +
                    '· Tip: leave rows unselected to pull every SKU with INV ≥ 1\n' +
                    '· Runs via artisan amazon:pull-buybox (cron-style)\n' +
                    '· You can leave this page — progress is saved'
                )) {
                    return;
                }

                const $btn = $(this);
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Starting…');
                $('#amz-bb-status-line').text('Starting background pull…');

                const payload = {};
                if (selected.length) payload.skus = selected;

                $.ajax({
                    url: @json(route('amz.buybox.pull')),
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: payload,
                    success: function(resp) {
                        $('#amz-bb-status-line').text(resp.message || 'Pull started');
                        amzBbApplyPullStatus(resp.status || { running: true, message: resp.message });
                        amzBbStartPullPoll();
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false)
                            .html('<i class="fas fa-cloud-download-alt me-1"></i> Pull Buy Box');
                        const msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                            || 'Failed to start pull';
                        $('#amz-bb-status-line').text(msg);
                        alert(msg);
                    },
                });
            });
        });
    </script>
@endsection
