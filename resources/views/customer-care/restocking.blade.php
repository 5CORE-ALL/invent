@extends('layouts.vertical', ['title' => 'Restocking', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #restocking-table .tabulator-header .tabulator-col {
            background: #d9d9d9;
            color: #000;
            font-weight: 700;
        }
        #restocking-table .tabulator-row .tabulator-cell { border-right: 1px solid #e3e3e3; }
        .rs-thumb {
            width: 44px;
            height: 44px;
            object-fit: contain;
            border-radius: 4px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 1px;
            display: inline-block;
        }
        .rs-thumb-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 4px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #adb5bd;
        }
        /* Order ID: red = not restocked yet, green = restocked (as in the sheet) */
        #restocking-table .tabulator-cell.rs-order-pending { background: #ff0000; color: #000; }
        #restocking-table .tabulator-cell.rs-order-done { background: #00ff00; color: #000; }
        #restocking-table .tabulator-cell.rs-recovered-done { background: #00ff00; }
        #restocking-table .tabulator-cell.rs-sku { font-weight: 700; color: #6c757d; }
        .rs-status { font-size: 0.85rem; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Restocking',
        'sub_title'  => 'Customer Care',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <input type="text" id="rs-search" class="form-control form-control-sm" style="max-width:320px"
                           placeholder="Search order ID, channel or SKU...">
                    <select id="rs-filter-status" class="form-select form-select-sm" style="max-width:180px">
                        <option value="">All</option>
                        <option value="pending">Not restocked</option>
                        <option value="done">Restocked</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-primary" id="rs-add-btn">
                        <i class="fas fa-plus me-1"></i>Add row
                    </button>
                    <span class="ms-auto small text-muted rs-status" id="rs-status">Loading…</span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="restocking-table" style="height: calc(100vh - 290px);"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    const channels = @json($channels);
    const urlData = @json(route('customer.care.restocking.data'));
    const urlStore = @json(route('customer.care.restocking.store'));
    const urlRow = @json(url('/customer-care/restocking'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let table = null;

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function api(url, opts) {
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        };
        return fetch(url, Object.assign({}, opts, { headers })).then(function (r) {
            return r.text().then(function (text) {
                let j = null;
                try { j = text ? JSON.parse(text) : null; } catch (e) { j = null; }
                if (!r.ok) {
                    let msg = (j && j.message) || ('HTTP ' + r.status);
                    if (j && j.errors) msg = Object.values(j.errors).flat().join(' ');
                    throw new Error(msg);
                }
                return j;
            });
        });
    }

    function setStatus(text, isError) {
        $('#rs-status').text(text).toggleClass('text-danger', !!isError).toggleClass('text-muted', !isError);
    }

    function formatDate(v) {
        if (!v) return '';
        const d = new Date(v + 'T00:00:00');
        if (isNaN(d)) return escapeHtml(v);
        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit' });
    }

    function money(cell) {
        const v = cell.getValue();
        if (v === null || v === undefined || v === '') return '';
        return Number(v).toFixed(2);
    }

    const moneyCol = {
        hozAlign: 'right',
        headerHozAlign: 'right',
        sorter: 'number',
        editor: 'number',
        editorParams: { min: 0, step: 0.01 },
        formatter: money,
        bottomCalc: 'sum',
        bottomCalcParams: { precision: 2 },
        bottomCalcFormatter: function (cell) { return Number(cell.getValue() || 0).toFixed(2); },
    };

    function saveField(cell) {
        const row = cell.getRow();
        const data = row.getData();
        const field = cell.getField();
        const payload = {};
        payload[field] = data[field] === '' ? null : data[field];
        setStatus('Saving…');
        api(urlRow + '/' + data.id, { method: 'PUT', body: JSON.stringify(payload) })
            .then(function (resp) {
                // refresh derived values (e.g. thumbnail after a SKU change)
                row.update(resp.row);
                setStatus('Saved.');
            })
            .catch(function (e) {
                cell.restoreOldValue();
                setStatus(e.message || 'Save failed', true);
            });
    }

    function applyFilters() {
        const q = $('#rs-search').val().trim().toLowerCase();
        const st = $('#rs-filter-status').val();
        table.setFilter(function (d) {
            if (st === 'pending' && d.restocked) return false;
            if (st === 'done' && !d.restocked) return false;
            if (!q) return true;
            return [d.order_id, d.channel, d.sku].some(function (v) {
                return String(v || '').toLowerCase().includes(q);
            });
        });
    }

    $(document).ready(function () {
        table = new Tabulator('#restocking-table', {
            layout: 'fitDataStretch',
            index: 'id',
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [50, 100, 200, 500],
            placeholder: 'No restocking rows yet. Click "Add row".',
            initialSort: [{ column: 'date', dir: 'desc' }],
            columns: [
                {
                    title: 'Image',
                    field: 'image',
                    headerSort: false,
                    width: 80,
                    hozAlign: 'center',
                    formatter: function (cell) {
                        const src = cell.getValue();
                        if (!src) return '<span class="rs-thumb-placeholder" title="No image"><i class="fas fa-image"></i></span>';
                        return '<img src="' + escapeHtml(src) + '" class="rs-thumb" loading="lazy" onerror="this.style.display=\'none\'">';
                    },
                },
                {
                    title: 'Date',
                    field: 'date',
                    width: 110,
                    editor: 'date',
                    formatter: function (cell) { return formatDate(cell.getValue()); },
                    cellEdited: saveField,
                },
                {
                    title: 'Order ID',
                    field: 'order_id',
                    minWidth: 240,
                    editor: 'input',
                    formatter: function (cell) {
                        const d = cell.getRow().getData();
                        const el = cell.getElement();
                        el.classList.toggle('rs-order-done', !!d.restocked);
                        el.classList.toggle('rs-order-pending', !d.restocked);
                        return escapeHtml(cell.getValue());
                    },
                    cellEdited: saveField,
                },
                {
                    title: 'Channel',
                    field: 'channel',
                    width: 130,
                    editor: 'list',
                    editorParams: { values: channels, autocomplete: true, freetext: true, clearable: true, listOnEmpty: true },
                    cellEdited: saveField,
                },
                {
                    title: 'SKU',
                    field: 'sku',
                    minWidth: 180,
                    editor: 'input',
                    cssClass: 'rs-sku',
                    cellEdited: saveField,
                },
                {
                    title: 'QTY',
                    field: 'qty',
                    width: 80,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    sorter: 'number',
                    editor: 'number',
                    editorParams: { min: 0, step: 1 },
                    bottomCalc: 'sum',
                    cellEdited: saveField,
                },
                Object.assign({ title: 'Selling Price', field: 'selling_price', width: 130, cellEdited: saveField }, moneyCol),
                Object.assign({ title: 'Refunded Amount', field: 'refunded_amount', width: 150, cellEdited: saveField }, moneyCol),
                Object.assign({
                    title: 'Recovered Price',
                    field: 'recovered_price',
                    width: 150,
                    cellEdited: saveField,
                    formatter: function (cell) {
                        const d = cell.getRow().getData();
                        cell.getElement().classList.toggle('rs-recovered-done', !!d.restocked && d.recovered_price !== null && d.recovered_price !== '');
                        return money(cell);
                    },
                }, moneyCol),
                {
                    title: 'Restocked',
                    field: 'restocked',
                    width: 110,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    formatter: 'tickCross',
                    editor: true,
                    editorParams: { trueValue: true, falseValue: false },
                    cellEdited: function (cell) {
                        saveField(cell);
                    },
                },
                {
                    title: '',
                    field: 'id',
                    width: 50,
                    headerSort: false,
                    hozAlign: 'center',
                    formatter: function () { return '<i class="fas fa-trash text-danger" style="cursor:pointer" title="Delete row"></i>'; },
                    cellClick: function (e, cell) {
                        const d = cell.getRow().getData();
                        if (!confirm('Delete this restocking row' + (d.order_id ? ' (' + d.order_id + ')' : '') + '?')) return;
                        api(urlRow + '/' + d.id, { method: 'DELETE' })
                            .then(function () { cell.getRow().delete(); setStatus('Deleted.'); })
                            .catch(function (err) { setStatus(err.message || 'Delete failed', true); });
                    },
                },
            ],
        });

        // `restocked` toggles recolor the Order ID / Recovered Price cells, so re-render the row.
        table.on('cellEdited', function (cell) {
            if (cell.getField() === 'restocked') {
                cell.getRow().reformat();
            }
        });

        api(urlData).then(function (resp) {
            table.setData(resp.data || []);
            setStatus((resp.data || []).length + ' rows');
        }).catch(function (e) {
            setStatus(e.message || 'Could not load data', true);
        });

        $('#rs-add-btn').on('click', function () {
            api(urlStore, {
                method: 'POST',
                body: JSON.stringify({ date: new Date().toISOString().slice(0, 10), qty: 1 }),
            }).then(function (resp) {
                table.addRow(resp.row, true);
                setStatus('Row added.');
            }).catch(function (e) {
                setStatus(e.message || 'Could not add row', true);
            });
        });

        $('#rs-search').on('input', applyFilters);
        $('#rs-filter-status').on('change', applyFilters);
    });
</script>
@endsection
