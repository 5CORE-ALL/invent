@extends('layouts.vertical', ['title' => 'Cancelled Orders', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .sco-channel-name {
            font-weight: 600;
            color: #1e293b;
            text-decoration: none;
        }
        .sco-channel-name.has-link { color: #0d6efd; }
        .sco-channel-name.has-link:hover { text-decoration: underline; }

        #sales-cancelled-order-table.tabulator .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        #sales-cancelled-order-table.tabulator .tabulator-header .tabulator-col {
            background-color: #e6e6e6;
            height: 80px !important;
            overflow: visible;
        }
        #sales-cancelled-order-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
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
            overflow: visible;
            text-overflow: clip;
            pointer-events: none;
        }
        #sales-cancelled-order-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable {
            cursor: pointer;
        }
        #sales-cancelled-order-table .tabulator-row .tabulator-cell {
            vertical-align: middle;
        }
        #sales-cancelled-order-table .tabulator-row .tabulator-cell:has(.sco-order-id-wrap),
        #sales-cancelled-order-table .tabulator-row .tabulator-cell:has(.sco-text-dot-wrap),
        #sales-cancelled-order-table .tabulator-row .tabulator-cell:has(.sco-tracking-cell) {
            overflow: visible !important;
        }
        #sales-cancelled-order-table .tabulator-row:has(.sco-tracking-cell:hover) {
            z-index: 8;
        }

        #sco-toolbar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 8px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        #sco-toolbar-row1,
        #sco-toolbar-row2 {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 6px;
            min-width: 0;
            overflow-x: auto;
        }
        #sco-toolbar .sco-summary-badge {
            font-size: 0.72rem !important;
            padding: 0.2rem 0.45rem !important;
            line-height: 1.15;
            font-weight: 600 !important;
            white-space: nowrap;
            flex-shrink: 0;
        }
        #sco-toolbar .form-control-sm,
        #sco-toolbar .form-select-sm,
        #sco-toolbar .input-group-sm > .form-control,
        #sco-toolbar .input-group-sm > .input-group-text {
            min-height: 28px;
            height: 28px;
            padding-top: 0.15rem;
            padding-bottom: 0.15rem;
            font-size: 0.78rem;
        }
        #sco-toolbar .sco-filter-field { flex-shrink: 0; }
        #sco-toolbar #sco-order-search {
            min-width: 180px;
            flex: 1 1 180px;
        }
        #sco-date-filter-hint {
            font-size: 0.7rem;
            white-space: nowrap;
            color: #64748b;
            margin-left: auto;
            flex-shrink: 0;
        }
        .sco-oc-missing { color: #adb5bd; }
        .sco-status-badge {
            display: inline-block;
            background: #f8d7da;
            color: #842029;
            font-weight: 500;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            border-radius: 50rem;
            border: 1px solid #f5c2c7;
            line-height: 1.2;
            white-space: nowrap;
        }
        .sco-order-id-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .sco-order-id-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #f06548;
            box-shadow: 0 0 0 2px rgba(240, 101, 72, 0.22);
            cursor: default;
        }
        .sco-order-id-popover {
            display: none;
            position: absolute;
            left: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 40;
            min-width: 160px;
            max-width: 320px;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
            align-items: center;
            gap: 8px;
        }
        .sco-order-id-wrap:hover .sco-order-id-popover { display: flex; }
        .sco-order-id-text {
            font-size: 0.8rem;
            font-weight: 600;
            color: #0f172a;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sco-order-id-text a { color: #0d6efd; text-decoration: none; }
        .sco-order-id-text a:hover { text-decoration: underline; }
        .sco-order-id-copy,
        .sco-sku-copy,
        .sco-tracking-copy {
            border: none;
            background: #f1f5f9;
            color: #475569;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            padding: 0;
        }
        .sco-sku-copy { width: 22px; height: 22px; font-size: 11px; }
        .sco-order-id-copy:hover,
        .sco-sku-copy:hover,
        .sco-tracking-copy:hover { background: #e2e8f0; color: #0f172a; }
        .sco-order-id-copy.copied,
        .sco-sku-copy.copied,
        .sco-tracking-copy.copied { background: #d1e7dd; color: #0f5132; }
        .sco-text-dot-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .sco-text-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #6c8cff;
            box-shadow: 0 0 0 2px rgba(108, 140, 255, 0.22);
        }
        .sco-text-dot-box {
            display: none;
            position: absolute;
            right: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 40;
            min-width: 160px;
            max-width: 360px;
            background: #fff;
            color: #334155;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            font-size: 0.8rem;
            font-weight: 500;
            line-height: 1.35;
            white-space: normal;
            text-align: left;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
        }
        .sco-text-dot-wrap:hover .sco-text-dot-box { display: block; }
        .sco-sku-cell {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            max-width: 100%;
        }
        .sco-sku-cell code {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sco-tracking-cell {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .sco-ch-orders-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .sco-ch-orders-dot.red { background: #f06548; box-shadow: 0 0 0 2px rgba(240, 101, 72, 0.2); }
        .sco-ch-orders-dot.green { background: #0ab39c; box-shadow: 0 0 0 2px rgba(10, 179, 156, 0.2); }
        .sco-tracking-popover {
            display: none;
            position: absolute;
            left: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 50;
            min-width: 180px;
            max-width: 360px;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
            align-items: center;
            gap: 8px;
        }
        .sco-tracking-cell:hover .sco-tracking-popover { display: flex; }
        .sco-tracking-num {
            font-size: 0.8rem;
            font-weight: 600;
            color: #0f172a;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sco-dt-cell {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            line-height: 1.15;
            font-weight: 700;
            color: #0f172a;
        }
        .sco-dt-date { white-space: nowrap; }
        .sco-dt-time {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            white-space: nowrap;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Cancelled Orders',
        'sub_title'  => 'Sales',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2">
                    <div id="sco-toolbar" class="mb-2">
                        <div id="sco-toolbar-row1" role="group" aria-label="Summary metrics">
                            <span class="badge sco-summary-badge" style="background:#f8d7da; color:#842029; border:1px solid #f5c2c7;" title="Cancelled marketplace orders">
                                Orders: <span id="sco-order-count">0</span>
                            </span>
                            <span class="badge bg-primary sco-summary-badge" style="color: white;" title="Marketplaces with at least one cancelled order">
                                Marketplaces: <span id="sco-channel-count">0</span>
                            </span>
                            <span class="badge sco-summary-badge" style="background:#fff3cd; color:#856404; border:1px solid #ffe69c;" title="Sum of order amounts">
                                Amount: <span id="sco-amount-total">0.00</span>
                            </span>
                            <a href="{{ route('sales.order.fulfillment') }}" class="btn btn-sm btn-outline-secondary ms-1" title="Open Sales Order Fulfillment">
                                <i class="ri-truck-line me-1"></i>Fulfillment
                            </a>
                        </div>
                        <div id="sco-toolbar-row2">
                            <div class="sco-filter-field">
                                <label class="visually-hidden" for="sco-date-from">From</label>
                                <input type="date" id="sco-date-from" class="form-control form-control-sm" style="width:140px;" title="From date" value="{{ $scoDateFrom ?? '' }}">
                            </div>
                            <div class="sco-filter-field">
                                <label class="visually-hidden" for="sco-date-to">To</label>
                                <input type="date" id="sco-date-to" class="form-control form-control-sm" style="width:140px;" title="To date" value="{{ $scoDateTo ?? '' }}">
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="sco-apply-dates" title="Apply date filter">Apply</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="sco-clear-dates" title="Reset to last 30 days">Last 30 days</button>
                            <div class="sco-filter-field">
                                <label class="visually-hidden" for="sco-channel-filter">Marketplace</label>
                                <div class="input-group input-group-sm" style="width:160px;">
                                    <span class="input-group-text" title="Filter by marketplace"><i class="fas fa-search"></i></span>
                                    <input type="text" id="sco-channel-filter" class="form-control"
                                           list="sco-channel-datalist"
                                           placeholder="Marketplace…"
                                           autocomplete="off"
                                           title="Filter by marketplace">
                                </div>
                                <datalist id="sco-channel-datalist">
                                    @foreach(($scoChannels ?? []) as $chOpt)
                                        @if(($chOpt['slug'] ?? '') !== '')
                                            <option value="{{ $chOpt['label'] }}" data-slug="{{ $chOpt['slug'] }}"></option>
                                        @endif
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="sco-filter-field">
                                <label class="visually-hidden" for="sco-status-filter">Status</label>
                                <select id="sco-status-filter" class="form-select form-select-sm" style="width:150px;" title="Filter by status">
                                    <option value="">All statuses</option>
                                </select>
                            </div>
                            <div class="sco-filter-field flex-grow-1">
                                <label class="visually-hidden" for="sco-order-search">Search</label>
                                <input type="text" id="sco-order-search" class="form-control form-control-sm"
                                       placeholder="Search Marketplace, Order ID, SKU, Status…"
                                       autocomplete="off"
                                       title="Filter by Marketplace, Order ID, SKU, Status">
                            </div>
                            <div id="sco-date-filter-hint">Dates shown as 1 Apr · time EDT only</div>
                        </div>
                    </div>
                    <p class="small text-muted mb-2">Cancelled orders only, by marketplace. Defaults to the last 30 days.</p>
                    <div id="sales-cancelled-order-table" style="height: calc(100vh - 320px);"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function () {
    const SCO_TZ = 'America/New_York';
    const scoChannelSlugByLabel = {};
    @foreach(($scoChannels ?? []) as $chOpt)
        @if(($chOpt['slug'] ?? '') !== '' && ($chOpt['label'] ?? '') !== '')
            scoChannelSlugByLabel[@json(strtolower($chOpt['label']))] = @json($chOpt['slug']);
        @endif
    @endforeach

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function copyTextToClipboard(text, btn) {
        const done = function () {
            if (!btn) return;
            const prev = btn.innerHTML;
            btn.classList.add('copied');
            btn.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
            setTimeout(function () {
                btn.classList.remove('copied');
                btn.innerHTML = prev;
            }, 1200);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                window.prompt('Copy:', text);
            });
        } else {
            window.prompt('Copy:', text);
            done();
        }
    }

    function scoDateParams() {
        const fromEl = document.getElementById('sco-date-from');
        const toEl = document.getElementById('sco-date-to');
        return {
            date_from: fromEl ? (fromEl.value || '') : '',
            date_to: toEl ? (toEl.value || '') : '',
            tz: SCO_TZ,
        };
    }

    function scoUpdateDateHint() {
        const p = scoDateParams();
        const hint = document.getElementById('sco-date-filter-hint');
        if (!hint) return;
        if (!p.date_from && !p.date_to) {
            hint.textContent = 'Last 30 days · display EDT';
        } else {
            hint.textContent = 'Filter: ' + (p.date_from || '…') + ' → ' + (p.date_to || 'today') + ' · display EDT';
        }
    }

    function scoChannelFilterValue() {
        const el = document.getElementById('sco-channel-filter');
        return el ? String(el.value || '').trim() : '';
    }

    function scoResolvedChannelSlug() {
        const label = scoChannelFilterValue().toLowerCase();
        return scoChannelSlugByLabel[label] || '';
    }

    function scoStringSorter(a, b) {
        return String(a || '').localeCompare(String(b || ''), undefined, { numeric: true, sensitivity: 'base' });
    }

    function scoDateSorter(a, b) {
        const as = String(a || '').trim();
        const bs = String(b || '').trim();
        if (!as && !bs) return 0;
        if (!as) return -1;
        if (!bs) return 1;
        return as.localeCompare(bs);
    }

    function scoApplyLatestFirstSort(tbl) {
        if (!tbl || tbl._scoSorting) return;
        tbl._scoSorting = true;
        try {
            tbl.setSort([{ column: 'order_date', dir: 'desc' }]);
        } catch (e) {
            /* table not ready */
        } finally {
            tbl._scoSorting = false;
        }
    }

    const SCO_SOURCE_TZ = 'America/Los_Angeles';
    const SCO_DISPLAY_TZ = 'America/New_York';

    function scoWallClockToUtcMs(raw, timeZone) {
        const m = String(raw || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
        if (!m) {
            const d = new Date(String(raw || '').replace(' ', 'T'));
            return isNaN(d.getTime()) ? null : d.getTime();
        }
        const y = Number(m[1]);
        const mo = Number(m[2]);
        const d = Number(m[3]);
        const h = Number(m[4] || 0);
        const mi = Number(m[5] || 0);
        const s = Number(m[6] || 0);
        const desired = Date.UTC(y, mo - 1, d, h, mi, s);
        const dtf = new Intl.DateTimeFormat('en-US', {
            timeZone: timeZone,
            hourCycle: 'h23',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
        const wallAsUtc = function (instant) {
            const parts = dtf.formatToParts(new Date(instant));
            const get = function (type) {
                const p = parts.find(function (x) { return x.type === type; });
                return p ? Number(p.value) : 0;
            };
            return Date.UTC(get('year'), get('month') - 1, get('day'), get('hour'), get('minute'), get('second'));
        };
        let instant = desired;
        for (let i = 0; i < 3; i++) {
            instant = desired - (wallAsUtc(instant) - desired);
        }
        return instant;
    }

    function scoFormatEstParts(raw) {
        const s = String(raw || '').trim();
        if (!s) return null;
        const dateOnly = /^\d{4}-\d{2}-\d{2}$/.test(s);
        const ms = scoWallClockToUtcMs(s, dateOnly ? SCO_DISPLAY_TZ : SCO_SOURCE_TZ);
        if (ms == null) return null;
        const d = new Date(ms);
        const dateParts = new Intl.DateTimeFormat('en-US', {
            timeZone: SCO_DISPLAY_TZ,
            day: 'numeric',
            month: 'short',
        }).formatToParts(d);
        const day = (dateParts.find(function (p) { return p.type === 'day'; }) || {}).value || '';
        const month = (dateParts.find(function (p) { return p.type === 'month'; }) || {}).value || '';
        const dateLabel = (day + ' ' + month).trim();
        if (dateOnly) {
            return { date: dateLabel, time: '', title: dateLabel + ' EDT' };
        }
        const time = new Intl.DateTimeFormat('en-US', {
            timeZone: SCO_DISPLAY_TZ,
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).format(d);
        const title = new Intl.DateTimeFormat('en-US', {
            timeZone: SCO_DISPLAY_TZ,
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
        }).format(d) + ' EDT';
        return { date: dateLabel, time: time + ' EDT', title: title };
    }

    function scoFormatEstDateCell(cell) {
        const raw = cell.getValue();
        if (!raw) return '—';
        const parts = scoFormatEstParts(raw);
        if (!parts) return escapeHtml(raw);
        const wrap = document.createElement('span');
        wrap.className = 'sco-dt-cell';
        wrap.title = parts.title;
        const dateEl = document.createElement('span');
        dateEl.className = 'sco-dt-date';
        dateEl.textContent = parts.date;
        wrap.appendChild(dateEl);
        if (parts.time) {
            const timeEl = document.createElement('span');
            timeEl.className = 'sco-dt-time';
            timeEl.textContent = parts.time;
            wrap.appendChild(timeEl);
        }
        return wrap;
    }

    function formatMoney(v) {
        if (v === null || v === undefined || v === '') return '—';
        return Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function scoStatusFilterValue() {
        const el = document.getElementById('sco-status-filter');
        return el ? String(el.value || '').trim() : '';
    }

    function scoRebuildStatusOptions(rows) {
        const sel = document.getElementById('sco-status-filter');
        if (!sel) return;
        const prev = sel.value;
        const counts = {};
        (rows || []).forEach(function (row) {
            const label = String(row.status_label || row.status || '').trim();
            if (!label || label === '—') return;
            counts[label] = (counts[label] || 0) + 1;
        });
        const labels = Object.keys(counts).sort(function (a, b) {
            return a.localeCompare(b, undefined, { sensitivity: 'base' });
        });
        sel.innerHTML = '';
        const all = document.createElement('option');
        all.value = '';
        all.textContent = 'All statuses';
        sel.appendChild(all);
        labels.forEach(function (label) {
            const opt = document.createElement('option');
            opt.value = label;
            opt.textContent = label + ' (' + counts[label].toLocaleString() + ')';
            sel.appendChild(opt);
        });
        if (prev && counts[prev]) {
            sel.value = prev;
        }
    }

    function applyClientFilters(table) {
        if (!table) return;
        const q = ($('#sco-order-search').val() || '').trim().toLowerCase();
        const channel = scoChannelFilterValue();
        const slug = scoResolvedChannelSlug();
        const status = scoStatusFilterValue();

        if (!q && !channel && !status) {
            table.clearFilter(true);
            scoApplyLatestFirstSort(table);
            return;
        }

        table.setFilter(function (data) {
            const searchOk = !q || (
                String(data.channel_label || '').toLowerCase().includes(q)
                || String(data.order_id || '').toLowerCase().includes(q)
                || String(data.sku || '').toLowerCase().includes(q)
                || String(data.status_label || data.status || '').toLowerCase().includes(q)
                || String(data.tracking_number || '').toLowerCase().includes(q)
            );
            if (!searchOk) return false;
            if (status) {
                const rowStatus = String(data.status_label || data.status || '').trim();
                if (rowStatus.toLowerCase() !== status.toLowerCase()) return false;
            }
            if (!channel) return true;
            if (slug) {
                return String(data.mm_slug || '').toLowerCase() === slug;
            }
            const cq = channel.toLowerCase();
            return String(data.channel_label || '').toLowerCase().includes(cq)
                || String(data.mm_slug || '').toLowerCase().includes(cq);
        });
        scoApplyLatestFirstSort(table);
    }

    const table = new Tabulator('#sales-cancelled-order-table', {
        pagination: true,
        paginationMode: 'local',
        sortMode: 'local',
        filterMode: 'local',
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, 500, true],
        movableColumns: false,
        headerSortClickElement: 'header',
        layout: 'fitColumns',
        placeholder: 'Loading cancelled orders…',
        initialSort: [{ column: 'order_date', dir: 'desc' }],
        ajaxURL: '{{ route("sales.cancelled.order.data") }}',
        ajaxConfig: 'GET',
        ajaxParams: scoDateParams,
        ajaxRequestFunc: function (url, config, params) {
            return new Promise(function (resolve, reject) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    data: Object.assign({}, params || {}, scoDateParams()),
                    timeout: 0,
                    success: resolve,
                    error: reject,
                });
            });
        },
        ajaxResponse: function (url, params, response) {
            const rows = (response && response.success && Array.isArray(response.data))
                ? response.data
                : [];
            const count = (response && response.count != null) ? Number(response.count) : rows.length;
            const channels = (response && response.channel_count != null) ? Number(response.channel_count) : 0;
            const amount = (response && response.amount_total != null) ? Number(response.amount_total) : 0;
            const countEl = document.getElementById('sco-order-count');
            const chEl = document.getElementById('sco-channel-count');
            const amtEl = document.getElementById('sco-amount-total');
            if (countEl) countEl.textContent = count.toLocaleString();
            if (chEl) chEl.textContent = channels.toLocaleString();
            if (amtEl) amtEl.textContent = amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (response && response.success === false) {
                this.options.placeholder = response.message || 'Failed to load cancelled orders.';
            }
            scoRebuildStatusOptions(rows);
            return rows;
        },
        dataLoaded: function () {
            applyClientFilters(table);
            scoApplyLatestFirstSort(table);
        },
        columns: [
            {
                title: 'Marketplace',
                field: 'channel_label',
                minWidth: 110,
                headerHozAlign: 'center',
                headerSort: true,
                sorter: scoStringSorter,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const label = escapeHtml(cell.getValue() || '');
                    const url = (row.orders_url || '').trim();
                    if (url) {
                        return `<a href="${escapeHtml(url)}" target="_blank" class="sco-channel-name has-link">${label}</a>`;
                    }
                    return `<span class="sco-channel-name">${label}</span>`;
                },
            },
            {
                title: 'Order ID',
                field: 'order_id',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: scoStringSorter,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const orderId = (cell.getValue() || '').toString().trim();
                    if (!orderId) {
                        return '<span class="sco-oc-missing">—</span>';
                    }
                    const url = (row.order_url || '').trim();
                    const wrap = document.createElement('span');
                    wrap.className = 'sco-order-id-wrap';
                    const dot = document.createElement('span');
                    dot.className = 'sco-order-id-dot';
                    wrap.appendChild(dot);
                    const pop = document.createElement('span');
                    pop.className = 'sco-order-id-popover';
                    const text = document.createElement('span');
                    text.className = 'sco-order-id-text';
                    if (url) {
                        const a = document.createElement('a');
                        a.href = url;
                        a.target = '_blank';
                        a.rel = 'noopener noreferrer';
                        a.textContent = orderId;
                        a.addEventListener('click', function (ev) { ev.stopPropagation(); });
                        text.appendChild(a);
                    } else {
                        text.textContent = orderId;
                    }
                    pop.appendChild(text);
                    const copyBtn = document.createElement('button');
                    copyBtn.type = 'button';
                    copyBtn.className = 'sco-order-id-copy';
                    copyBtn.title = 'Copy Order ID';
                    copyBtn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';
                    copyBtn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        copyTextToClipboard(orderId, copyBtn);
                    });
                    pop.appendChild(copyBtn);
                    wrap.appendChild(pop);
                    return wrap;
                },
            },
            {
                title: 'Date',
                field: 'order_date',
                minWidth: 88,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: scoDateSorter,
                headerTooltip: 'Latest orders on top · 1 Apr · time EDT only',
                formatter: scoFormatEstDateCell,
            },
            {
                title: 'Status',
                field: 'status_label',
                minWidth: 110,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: scoStringSorter,
                headerTooltip: 'Original marketplace status',
                formatter: function (cell) {
                    const label = escapeHtml(cell.getValue() || cell.getRow().getData().status || '—');
                    return `<span class="sco-status-badge">${label}</span>`;
                },
            },
            {
                title: 'SKU',
                field: 'sku',
                minWidth: 220,
                headerHozAlign: 'center',
                headerSort: true,
                sorter: scoStringSorter,
                formatter: function (cell) {
                    const sku = (cell.getValue() || '').toString().trim();
                    if (!sku) return '—';
                    const wrap = document.createElement('span');
                    wrap.className = 'sco-sku-cell';
                    const code = document.createElement('code');
                    code.textContent = sku;
                    wrap.appendChild(code);
                    const copyBtn = document.createElement('button');
                    copyBtn.type = 'button';
                    copyBtn.className = 'sco-sku-copy';
                    copyBtn.title = 'Copy SKU';
                    copyBtn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';
                    copyBtn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        copyTextToClipboard(sku, copyBtn);
                    });
                    wrap.appendChild(copyBtn);
                    return wrap;
                },
            },
            {
                title: 'Product',
                field: 'display_title',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const title = (cell.getValue() || '').toString().trim();
                    if (!title) return '<span class="sco-oc-missing">—</span>';
                    const wrap = document.createElement('span');
                    wrap.className = 'sco-text-dot-wrap';
                    const dot = document.createElement('span');
                    dot.className = 'sco-text-dot';
                    wrap.appendChild(dot);
                    const box = document.createElement('span');
                    box.className = 'sco-text-dot-box';
                    box.textContent = title;
                    wrap.appendChild(box);
                    return wrap;
                },
            },
            {
                title: 'Qty',
                field: 'quantity',
                minWidth: 60,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
            },
            {
                title: 'Amount',
                field: 'amount',
                minWidth: 80,
                hozAlign: 'right',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) { return formatMoney(cell.getValue()); },
            },
            {
                title: 'Tracking',
                field: 'tracking_number',
                minWidth: 70,
                width: 78,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: scoStringSorter,
                formatter: function (cell) {
                    const tracking = (cell.getValue() || '').toString().trim();
                    const wrap = document.createElement('span');
                    wrap.className = 'sco-tracking-cell';
                    const dot = document.createElement('span');
                    dot.className = 'sco-ch-orders-dot ' + (tracking ? 'green' : 'red');
                    wrap.appendChild(dot);
                    if (!tracking) return wrap;
                    const pop = document.createElement('span');
                    pop.className = 'sco-tracking-popover';
                    const num = document.createElement('span');
                    num.className = 'sco-tracking-num';
                    num.textContent = tracking;
                    pop.appendChild(num);
                    const copyBtn = document.createElement('button');
                    copyBtn.type = 'button';
                    copyBtn.className = 'sco-tracking-copy';
                    copyBtn.title = 'Copy tracking number';
                    copyBtn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';
                    copyBtn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        copyTextToClipboard(tracking, copyBtn);
                    });
                    pop.appendChild(copyBtn);
                    wrap.appendChild(pop);
                    return wrap;
                },
            },
        ],
    });

    table.on('dataSorted', function (sorters) {
        if (table._scoSorting) return;
        const first = sorters && sorters[0];
        const ok = first && (first.field === 'order_date' || first.column === 'order_date') && first.dir === 'desc';
        if (!ok) {
            scoApplyLatestFirstSort(table);
        }
    });

    let searchTimer = null;
    $('#sco-order-search, #sco-channel-filter').on('input keyup', function () {
        if (searchTimer) clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { applyClientFilters(table); }, 150);
    });
    $('#sco-status-filter').on('change', function () {
        applyClientFilters(table);
    });

    document.getElementById('sco-apply-dates')?.addEventListener('click', function () {
        scoUpdateDateHint();
        table.setData();
    });
    document.getElementById('sco-clear-dates')?.addEventListener('click', function () {
        const fromEl = document.getElementById('sco-date-from');
        const toEl = document.getElementById('sco-date-to');
        if (fromEl) fromEl.value = @json($scoDateFrom ?? '');
        if (toEl) toEl.value = @json($scoDateTo ?? '');
        scoUpdateDateHint();
        table.setData();
    });
    ['sco-date-from', 'sco-date-to'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('change', scoUpdateDateHint);
    });
    scoUpdateDateHint();
})();
</script>
@endsection
