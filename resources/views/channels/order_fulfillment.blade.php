@extends('layouts.vertical', ['title' => 'Order Fulfillment', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #order-fulfillment-table.tabulator .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        #order-fulfillment-table.tabulator .tabulator-header .tabulator-col {
            background-color: #e6e6e6;
            height: 80px !important;
        }
        #order-fulfillment-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
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
            color: black !important;
        }
        #order-fulfillment-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable {
            cursor: pointer;
        }
        #order-fulfillment-table .tabulator-row .tabulator-cell {
            vertical-align: middle;
        }
        #of-toolbar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 8px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        #of-toolbar .of-summary-badge {
            font-size: 0.72rem !important;
            padding: 0.2rem 0.45rem !important;
            line-height: 1.15;
            font-weight: 600 !important;
            white-space: nowrap;
        }
        #of-channels-badge {
            cursor: pointer;
            border: 0;
        }
        #of-channels-badge:hover { filter: brightness(1.12); }
        #of-toolbar .form-control-sm,
        #of-toolbar .form-select-sm {
            min-height: 28px;
            height: 28px;
            font-size: 0.78rem;
        }
        .of-paid {
            display: inline-block;
            background: #d1e7dd;
            color: #0f5132;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.2rem 0.55rem;
            border-radius: 50rem;
        }
        .of-unpaid {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.2rem 0.55rem;
            border-radius: 50rem;
        }
        .of-dt-cell { display: flex; flex-direction: column; line-height: 1.15; }
        .of-dt-date { font-weight: 600; color: #0f172a; }
        .of-dt-time { font-size: 0.72rem; color: #64748b; }
        .of-sku { font-size: 0.78rem; }
        .of-edit-btn {
            color: #0d6efd;
            line-height: 1;
        }
        .of-edit-btn:hover { color: #0a58ca; }
        .of-tracking {
            font-size: 0.78rem;
            font-weight: 600;
            color: #0f172a;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $ofPageTitle ?? 'Order Fulfillment',
        'sub_title'  => 'Sales',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2">
                    <div id="of-toolbar" class="mb-2">
                        <span class="badge bg-dark of-summary-badge" id="of-rows-badge" title="Number of rows currently shown after filters">Rows: <span id="of-order-count">0</span></span>
                        <button type="button" class="badge bg-secondary of-summary-badge" id="of-channels-badge" title="Show connected marketplaces">Channels: <span id="of-channel-count">0</span></button>
                        <span class="badge of-summary-badge" style="background:#d1e7dd; color:#0f5132;">Paid: <span id="of-paid-count">0</span></span>
                        <span class="badge of-summary-badge" style="background:#fff3cd; color:#856404;">Unpaid: <span id="of-unpaid-count">0</span></span>
                        <input type="date" id="of-date-from" class="form-control form-control-sm" style="width:140px;" value="{{ $ofDateFrom ?? '' }}" min="{{ $ofDateEarliest ?? '2026-09-15' }}" title="From date">
                        <input type="date" id="of-date-to" class="form-control form-control-sm" style="width:140px;" value="{{ $ofDateTo ?? '' }}" min="{{ $ofDateEarliest ?? '2026-09-15' }}" title="To date">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="of-apply-dates">Apply</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="of-clear-dates">Last 30 days</button>
                        <input type="text" id="of-channel-filter" class="form-control form-control-sm" style="width:150px;" list="of-channel-datalist" placeholder="Channel…" autocomplete="off">
                        <datalist id="of-channel-datalist">
                            @foreach(($ofChannels ?? []) as $chOpt)
                                @if(($chOpt['slug'] ?? '') !== '')
                                    <option value="{{ $chOpt['label'] }}"></option>
                                @endif
                            @endforeach
                        </datalist>
                        <select id="of-paid-filter" class="form-select form-select-sm" style="width:130px;" title="Paid or unpaid">
                            <option value="">Paid / Unpaid</option>
                            <option value="Paid">Paid</option>
                            <option value="Unpaid">Unpaid</option>
                        </select>
                        <input type="text" id="of-search" class="form-control form-control-sm" style="min-width:180px; flex:1;" placeholder="Search order id…" autocomplete="off" title="Filter by order id">
                    </div>
                    <p class="small text-muted mb-2">@if(!empty($ofDeliveredOnly))Orders from 15 Sep 2026 whose marketplace status or carrier tracking status is Delivered. The date filter defaults to the last 30 days.@else Orders from 15 Sep 2026. The date filter defaults to the last 30 days. Search matches the order id. Carrier and tracking status come from the tracking number (USPS, GOFO, FedEx, UPS).@endif</p>
                    <div id="order-fulfillment-table" style="height: calc(100vh - 280px);"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="of-channels-modal" tabindex="-1" aria-labelledby="of-channels-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="of-channels-modal-label">Connected marketplaces</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <ul class="list-group list-group-flush" id="of-channel-list"></ul>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="of-tracking-modal" tabindex="-1" aria-labelledby="of-tracking-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="of-tracking-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="of-tracking-modal-label">Add tracking number</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="of-track-id">
                        <input type="hidden" id="of-track-slug">
                        <div class="mb-2">
                            <div class="small text-muted">Channel</div>
                            <div id="of-track-channel" class="fw-semibold"></div>
                        </div>
                        <div class="mb-2">
                            <div class="small text-muted">Order ID</div>
                            <div id="of-track-order" class="fw-semibold"></div>
                        </div>
                        <div class="mb-3">
                            <div class="small text-muted">SKU</div>
                            <div id="of-track-sku"></div>
                        </div>
                        <label for="of-track-number" class="form-label">Tracking number</label>
                        <input type="text" class="form-control" id="of-track-number" maxlength="128" required autocomplete="off">
                        <div id="of-track-error" class="text-danger small mt-2" style="display:none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="of-track-save">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function () {
    const defaultFrom = @json($ofDateFrom ?? '');
    const defaultTo = @json($ofDateTo ?? '');
    const earliestDate = @json($ofDateEarliest ?? '2026-09-15');
    const deliveredOnly = @json((bool) ($ofDeliveredOnly ?? false));
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function clampDateInput(id) {
        const el = document.getElementById(id);
        if (!el || !el.value) return;
        if (earliestDate && el.value < earliestDate) el.value = earliestDate;
    }

    function dateParams() {
        clampDateInput('of-date-from');
        clampDateInput('of-date-to');
        const params = {
            date_from: document.getElementById('of-date-from')?.value || defaultFrom,
            date_to: document.getElementById('of-date-to')?.value || defaultTo,
        };
        if (deliveredOnly) params.delivered = 1;
        return params;
    }

    function formatDateTime(raw) {
        const m = String(raw || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
        if (!m) return null;
        const day = String(Number(m[3]));
        const month = months[Number(m[2]) - 1] || m[2];
        const date = day + ' ' + month + ' ' + m[1];
        if (!m[4]) return { date: date, time: '' };
        let hour = Number(m[4]);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12 || 12;
        return { date: date, time: hour + ':' + m[5] + ' ' + ampm };
    }

    function setCount(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = Number(value || 0).toLocaleString();
    }

    function countsFromRows(rows) {
        const channels = {};
        let paid = 0;
        let unpaid = 0;
        (rows || []).forEach(function (row) {
            if (row.mm_slug) channels[row.mm_slug] = true;
            if (row.paid) paid++;
            else unpaid++;
        });
        setCount('of-order-count', rows.length);
        setCount('of-channel-count', Object.keys(channels).length);
        setCount('of-paid-count', paid);
        setCount('of-unpaid-count', unpaid);
    }

    function applyFilters(table) {
        const q = String(document.getElementById('of-search')?.value || '').trim().toLowerCase();
        const channel = String(document.getElementById('of-channel-filter')?.value || '').trim().toLowerCase();
        const paid = String(document.getElementById('of-paid-filter')?.value || '').trim();
        if (!q && !channel && !paid) {
            table.clearFilter(true);
            countsFromRows(table.getData());
            return;
        }
        table.setFilter(function (data) {
            if (paid && String(data.paid_label || '') !== paid) return false;
            if (channel && !String(data.channel || '').toLowerCase().includes(channel)) return false;
            if (!q) return true;
            return String(data.order_id || '').toLowerCase().includes(q);
        });
        countsFromRows(table.getData('active'));
    }

    const table = new Tabulator('#order-fulfillment-table', {
        pagination: true,
        paginationMode: 'local',
        sortMode: 'local',
        filterMode: 'local',
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, 500, true],
        index: 'id',
        layout: 'fitColumns',
        placeholder: 'Loading orders…',
        initialSort: [{ column: 'order_date', dir: 'asc' }],
        ajaxURL: @json(route('order.fulfillment.data')),
        ajaxConfig: 'GET',
        ajaxRequestFunc: function (url, config, params) {
            return new Promise(function (resolve, reject) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    data: Object.assign({}, params || {}, dateParams()),
                    timeout: 90000,
                    success: resolve,
                    error: function (xhr) {
                        let message = 'Could not load orders. Narrow the dates and try again.';
                        try {
                            const body = JSON.parse(xhr.responseText || '{}');
                            if (body && body.message) message = body.message;
                        } catch (e) { /* keep default */ }
                        if (table) table.options.placeholder = message;
                        reject(xhr);
                    },
                });
            });
        },
        ajaxResponse: function (url, params, response) {
            const rows = (response && response.success && Array.isArray(response.data)) ? response.data : [];
            if (response && response.date_from) {
                const from = document.getElementById('of-date-from');
                if (from) from.value = response.date_from;
            }
            if (response && response.date_to) {
                const to = document.getElementById('of-date-to');
                if (to) to.value = response.date_to;
            }
            setCount('of-order-count', response && response.count != null ? response.count : rows.length);
            setCount('of-channel-count', response && response.channel_count);
            setCount('of-paid-count', response && response.paid_count);
            setCount('of-unpaid-count', response && response.unpaid_count);
            if (response && response.success === false) {
                this.options.placeholder = response.message || 'Failed to load orders.';
            }
            return rows;
        },
        dataLoaded: function () {
            table.setSort([{ column: 'order_date', dir: 'asc' }]);
            applyFilters(table);
            setTimeout(function () {
                fillTracking(0);
                fillTrackingStatus(0);
            }, 400);
        },
        columns: [
            {
                title: 'Channels',
                field: 'channel',
                minWidth: 120,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    return '<span style="font-weight:600;">' + escapeHtml(cell.getValue() || '—') + '</span>';
                },
            },
            {
                title: 'Order ID',
                field: 'order_id',
                minWidth: 160,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const value = String(cell.getValue() || '').trim();
                    return value ? escapeHtml(value) : '—';
                },
            },
            {
                title: 'SKU',
                field: 'sku',
                minWidth: 180,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const sku = String(cell.getValue() || '').trim();
                    return sku ? '<code class="of-sku">' + escapeHtml(sku) + '</code>' : '—';
                },
            },
            {
                title: 'Order Date and Time',
                field: 'order_date',
                minWidth: 140,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const parts = formatDateTime(cell.getValue());
                    if (!parts) return '—';
                    let html = '<span class="of-dt-cell"><span class="of-dt-date">' + escapeHtml(parts.date) + '</span>';
                    if (parts.time) html += '<span class="of-dt-time">' + escapeHtml(parts.time) + '</span>';
                    return html + '</span>';
                },
            },
            {
                title: 'Paid / Unpaid',
                field: 'paid_label',
                minWidth: 110,
                hozAlign: 'center',
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const value = String(cell.getValue() || '');
                    if (value === 'Paid') return '<span class="of-paid">Paid</span>';
                    if (value === 'Unpaid') return '<span class="of-unpaid">Unpaid</span>';
                    return '—';
                },
            },
            {
                title: 'Status',
                field: 'status',
                minWidth: 140,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const value = String(cell.getValue() || '').trim();
                    return value && value !== '—' ? escapeHtml(value) : '—';
                },
            },
            {
                title: 'Tracking',
                field: 'tracking',
                minWidth: 150,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const value = String(cell.getValue() || '').trim();
                    if (!value) {
                        return cell.getRow().getData().tracking_checked ? '—' : '…';
                    }
                    const source = String(cell.getRow().getData().tracking_source || '');
                    const label = source === '4seller' ? '4Seller' : (source === 'gofo' ? 'GOFO' : (source === 'veeqo' ? 'Veeqo' : (source === 'manual' ? 'Manual' : '')));
                    const title = label ? ' title="' + escapeHtml(label) + '"' : '';
                    return '<span class="of-tracking"' + title + '>' + escapeHtml(value) + '</span>';
                },
            },
            {
                title: 'Carrier',
                field: 'carrier',
                minWidth: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const value = String(cell.getValue() || '').trim();
                    return value ? escapeHtml(value) : '—';
                },
            },
            {
                title: 'Tracking Status',
                field: 'tracking_status',
                minWidth: 150,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const value = String(cell.getValue() || '').trim();
                    return value ? escapeHtml(value) : '—';
                },
            },
            {
                title: 'Inv.',
                field: 'inv',
                minWidth: 70,
                width: 80,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    const value = cell.getValue();
                    if (value === null || value === undefined || value === '') return '—';
                    return Number(value).toLocaleString();
                },
            },
            {
                title: 'Edit',
                field: 'id',
                width: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-sm btn-link of-edit-btn';
                    btn.title = 'Add tracking number';
                    btn.innerHTML = '<i class="fas fa-pen" aria-hidden="true"></i>';
                    btn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        openTrackingModal(cell.getRow().getData());
                    });
                    return btn;
                },
            },
        ],
    });

    ['of-search', 'of-channel-filter', 'of-paid-filter'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('input', function () { applyFilters(table); });
        document.getElementById(id)?.addEventListener('change', function () { applyFilters(table); });
    });

    document.getElementById('of-apply-dates')?.addEventListener('click', function () {
        table.setData();
    });
    document.getElementById('of-date-from')?.addEventListener('change', function () {
        table.setData();
    });
    document.getElementById('of-date-to')?.addEventListener('change', function () {
        table.setData();
    });
    function rowsForCounts() {
        const q = String(document.getElementById('of-search')?.value || '').trim();
        const channel = String(document.getElementById('of-channel-filter')?.value || '').trim();
        const paid = String(document.getElementById('of-paid-filter')?.value || '').trim();
        if (!q && !channel && !paid) return table.getData();
        return table.getData('active');
    }

    function openChannelsModal() {
        const grouped = {};
        rowsForCounts().forEach(function (row) {
            const slug = String(row.mm_slug || row.channel || '').trim();
            if (!slug) return;
            if (!grouped[slug]) {
                grouped[slug] = { label: String(row.channel || slug), count: 0 };
            }
            grouped[slug].count += 1;
        });
        const list = Object.keys(grouped).map(function (slug) { return grouped[slug]; })
            .sort(function (a, b) { return a.label.localeCompare(b.label); });
        const ul = document.getElementById('of-channel-list');
        if (ul) {
            ul.innerHTML = list.length
                ? list.map(function (ch) {
                    return '<li class="list-group-item d-flex justify-content-between align-items-center">'
                        + '<span>' + escapeHtml(ch.label) + '</span>'
                        + '<span class="badge bg-light text-dark border">' + Number(ch.count).toLocaleString() + '</span>'
                        + '</li>';
                }).join('')
                : '<li class="list-group-item text-muted">No marketplaces in this view.</li>';
        }
        const el = document.getElementById('of-channels-modal');
        if (window.bootstrap && bootstrap.Modal && el) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }
    }

    document.getElementById('of-channels-badge')?.addEventListener('click', openChannelsModal);

    document.getElementById('of-clear-dates')?.addEventListener('click', function () {
        const from = document.getElementById('of-date-from');
        const to = document.getElementById('of-date-to');
        if (from) from.value = defaultFrom;
        if (to) to.value = defaultTo;
        table.setData();
    });

    const lookupUrl = @json(route('order.fulfillment.tracking.lookup'));
    const saveUrl = @json(route('order.fulfillment.tracking.save'));
    const statusUrl = @json(route('order.fulfillment.tracking.status'));
    let trackingLookupRunning = false;
    let trackingFailCount = 0;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function applyTrackingUpdate(update) {
        const row = table.getRow(update.id);
        if (!row) return;
        row.update({
            tracking: update.tracking || '',
            tracking_source: update.tracking_source || '',
            tracking_checked: true,
            carrier: update.carrier || '',
            tracking_status: update.tracking_status || '',
            tracking_status_checked: !!update.tracking_status_checked,
        });
    }

    function applyStatusUpdate(update) {
        const number = String(update.tracking || '').replace(/\s+/g, '').toUpperCase();
        if (!number) return;
        table.getRows().forEach(function (row) {
            const data = row.getData();
            const current = String(data.tracking || '').replace(/\s+/g, '').toUpperCase();
            if (current !== number) return;
            row.update({
                carrier: update.carrier || data.carrier || '',
                tracking_status: update.tracking_status || '',
                tracking_status_checked: true,
            });
        });
    }

    function fillTracking(attempt) {
        if (trackingLookupRunning || attempt > 120) return;
        const pending = table.getData().filter(function (row) {
            return row && row.id && !row.tracking_checked && !String(row.tracking || '').trim();
        }).slice(0, 1);
        if (!pending.length) return;
        trackingLookupRunning = true;
        $.ajax({
            url: lookupUrl,
            type: 'POST',
            dataType: 'json',
            timeout: 48000,
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: {
                rows: pending.map(function (row) {
                    return {
                        id: row.id,
                        mm_slug: row.mm_slug || '',
                        order_id: row.order_id || '',
                        sku: row.sku || '',
                        source_id: row.source_id || 0,
                    };
                }),
            },
        }).done(function (res) {
            const updates = (res && Array.isArray(res.updates)) ? res.updates : [];
            updates.forEach(applyTrackingUpdate);
            trackingFailCount = 0;
            trackingLookupRunning = false;
            if (updates.some(function (update) { return String(update.tracking || '').trim(); })) {
                fillTrackingStatus(0);
            }
            fillTracking(attempt + 1);
        }).fail(function () {
            trackingLookupRunning = false;
            trackingFailCount += 1;
            if (trackingFailCount < 3) {
                setTimeout(function () { fillTracking(attempt); }, 1500);
                return;
            }
            pending.forEach(function (row) {
                const live = table.getRow(row.id);
                if (live && !String(live.getData().tracking || '').trim()) {
                    live.update({ tracking_checked: true });
                }
            });
            trackingFailCount = 0;
            fillTracking(attempt + 1);
        });
    }

    let statusLookupRunning = false;

    function fillTrackingStatus(attempt) {
        if (statusLookupRunning || attempt > 3) return;
        const seen = {};
        const numbers = [];
        table.getData().forEach(function (row) {
            const number = String(row.tracking || '').trim();
            const key = number.replace(/\s+/g, '').toUpperCase();
            if (!key || row.tracking_status_checked || seen[key]) return;
            seen[key] = true;
            numbers.push(number);
        });
        const batch = numbers.slice(0, 10);
        if (!batch.length) return;
        statusLookupRunning = true;
        $.ajax({
            url: statusUrl,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: { numbers: batch },
        }).done(function (res) {
            const updates = (res && Array.isArray(res.updates)) ? res.updates : [];
            updates.forEach(applyStatusUpdate);
            statusLookupRunning = false;
            if (updates.length && numbers.length > batch.length && attempt < 3) {
                fillTrackingStatus(attempt + 1);
            }
        }).fail(function () {
            statusLookupRunning = false;
        });
    }

    function openTrackingModal(row) {
        document.getElementById('of-track-id').value = row.id || '';
        document.getElementById('of-track-slug').value = row.mm_slug || '';
        document.getElementById('of-track-channel').textContent = row.channel || '—';
        document.getElementById('of-track-order').textContent = row.order_id || '—';
        document.getElementById('of-track-sku').textContent = row.sku || '—';
        document.getElementById('of-track-number').value = row.tracking || '';
        const err = document.getElementById('of-track-error');
        err.style.display = 'none';
        err.textContent = '';
        const el = document.getElementById('of-tracking-modal');
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }
        setTimeout(function () { document.getElementById('of-track-number')?.focus(); }, 200);
    }

    document.getElementById('of-tracking-form')?.addEventListener('submit', function (ev) {
        ev.preventDefault();
        const err = document.getElementById('of-track-error');
        const saveBtn = document.getElementById('of-track-save');
        const number = String(document.getElementById('of-track-number')?.value || '').trim();
        const id = document.getElementById('of-track-id')?.value || '';
        if (!number) {
            err.textContent = 'Enter a tracking number.';
            err.style.display = 'block';
            return;
        }
        saveBtn.disabled = true;
        $.ajax({
            url: saveUrl,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: {
                id: id,
                mm_slug: document.getElementById('of-track-slug')?.value || '',
                order_id: document.getElementById('of-track-order')?.textContent === '—' ? '' : document.getElementById('of-track-order')?.textContent,
                sku: document.getElementById('of-track-sku')?.textContent === '—' ? '' : document.getElementById('of-track-sku')?.textContent,
                tracking_number: number,
            },
        }).done(function (res) {
            if (!res || res.success === false) {
                err.textContent = (res && res.message) || 'Could not save the tracking number.';
                err.style.display = 'block';
                return;
            }
            applyTrackingUpdate(res);
            fillTrackingStatus(0);
            const el = document.getElementById('of-tracking-modal');
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).hide();
            }
        }).fail(function (xhr) {
            const message = xhr && xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Could not save the tracking number.';
            err.textContent = message;
            err.style.display = 'block';
        }).always(function () {
            saveBtn.disabled = false;
        });
    });
})();
</script>
@endsection
