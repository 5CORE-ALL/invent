@extends('layouts.vertical', ['title' => 'Shipping Issues (Label)', 'sidenav' => 'condensed'])
{{-- Shipping Issues (Label) — same data/APIs as All Issues (dispatch_issue_issues), filtered to the "Shipping" department. Cloned from chargeback_issues. --}}

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
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
        }

        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        .tabulator-paginator label {
            margin-right: 5px;
        }

        .sku-thumb {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 3px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }

        .sku-thumb-placeholder {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            border-radius: 3px;
            color: #adb5bd;
        }

        .issue-loss-input {
            width: 100%;
            max-width: 5.5rem;
            font-size: 0.8125rem;
            padding: 0.15rem 0.3rem;
            text-align: right;
        }

        .issue-img-thumb {
            max-height: 36px;
            max-width: 54px;
            object-fit: cover;
            border-radius: 4px;
            vertical-align: middle;
            border: 1px solid #dee2e6;
        }

        .what-happened-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            background-color: #dc3545;
            vertical-align: middle;
        }

        .what-happened-dot-damaged {
            background-color: #b8860b;
        }

        .status-dot-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            vertical-align: middle;
            cursor: help;
        }

        .status-dot-missing {
            background-color: #dc3545;
        }

        .status-dot-available {
            background-color: #198754;
        }

        #shipping-tabulator .tabulator-cell {
            position: relative;
        }

        /* Tracking cells reveal full value on hover */
        #shipping-tabulator .tabulator-cell:hover:has(.tracking-cell) {
            overflow: visible;
            z-index: 30;
        }

        .tracking-cell {
            white-space: nowrap;
            cursor: default;
        }

        .tracking-dot {
            font-size: 1.1em;
            color: #6c757d;
        }

        .tracking-full {
            display: none;
            background: #fff;
            padding: 0 4px;
            box-shadow: 1px 0 2px rgba(0, 0, 0, 0.08);
        }

        .copy-tracking-btn,
        .copy-order-btn {
            color: #0d6efd;
            font-size: 0.8rem;
            line-height: 1;
            padding: 0 2px;
            border: none;
            background: none;
            cursor: pointer;
        }

        .copy-tracking-btn {
            display: none;
        }

        .tracking-cell:hover .tracking-dot {
            display: none;
        }

        .tracking-cell:hover .tracking-full,
        .tracking-cell:hover .copy-tracking-btn {
            display: inline;
        }

        .copy-order-btn.copied,
        .copy-tracking-btn.copied {
            color: #198754;
        }

        .issue-link-icon {
            color: #0d6efd;
            line-height: 1;
            padding: 2px 4px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .issue-link-icon:hover {
            color: #0a58ca;
            background: rgba(13, 110, 253, 0.1);
        }

        .sil-row-btn {
            border: none;
            background: transparent;
            padding: 1px 5px;
            cursor: pointer;
            color: #2563eb;
        }

        .sil-row-btn.sil-danger {
            color: #dc2626;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            'use strict';

            const DEPARTMENT = 'Shipping';
            const CSRF = @json(csrf_token());
            const URLS = {
                list: @json(route('customer.care.dispatch.issues.list.index')),
                store: @json(route('customer.care.dispatch.issues.list.store')),
                updateBase: @json(route('customer.care.dispatch.issues.list.index', [], false)),
                skuDetails: @json(route('customer.care.dispatch.issues.sku.details')),
                archiveBase: @json(url('/customer-care/all-issues/issues')),
                dropdownOptions: @json(route('customer.care.dispatch.issues.dropdown.options.index')),
                l30Issues: @json(route('customer.care.dispatch.issues.l30.issues')),
                l30Loss: @json(route('customer.care.dispatch.issues.l30.loss')),
                nfeBase: @json(url('/customer-care/all-issues/issues')),
                colVisGet: @json(route('tabulator.column.visibility.user.get')),
                colVisSet: @json(route('tabulator.column.visibility.user.set')),
            };
            const COLVIS_CHANNEL = 'shipping_issues_label';

            const money = function (n) {
                const v = Math.round(Number(n) || 0);
                return '$' + v.toLocaleString();
            };

            const jsonHeaders = {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF,
            };

            const editableFields = [
                'sku', 'parent', 'qty', 'order_qty', 'order_number', 'marketplace_1', 'marketplace_2',
                'what_happened', 'issue', 'issue_remark', 'action_1', 'action_1_remark', 'replacement_tracking',
                'c_action_1', 'c_action_1_remark', 'tracking_number', 'refund_amount', 'total_loss',
                'issue_link', 'close_note',
            ];

            function buildPayload(data) {
                const payload = { department: [DEPARTMENT] };
                editableFields.forEach(function (key) {
                    let val = data[key];
                    if (val === undefined) val = null;
                    if (typeof val === 'string') val = val.trim();
                    payload[key] = val === '' ? null : val;
                });
                payload.sku = (payload.sku ?? '').toString();
                payload.qty = data.qty === null || data.qty === undefined || data.qty === '' ? 0 : Number(data.qty);
                return payload;
            }

            async function persistRow(rowData) {
                const id = rowData.id;
                const isUpdate = !!id;
                const url = isUpdate ? (URLS.updateBase + '/' + id) : URLS.store;
                const res = await fetch(url, {
                    method: isUpdate ? 'PUT' : 'POST',
                    headers: jsonHeaders,
                    body: JSON.stringify(buildPayload(rowData)),
                });
                if (!res.ok) {
                    let msg = 'Save failed (' + res.status + ').';
                    try {
                        const err = await res.json();
                        if (err && err.message) msg = err.message;
                    } catch (e) { /* ignore */ }
                    throw new Error(msg);
                }
                return res.json();
            }

            // ---- cell helpers (mirror the All Issues page) ----
            function escapeHtml(value) {
                const el = document.createElement('div');
                el.textContent = String(value ?? '');
                return el.innerHTML;
            }

            function escAttr(value) {
                return String(value ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            }

            function statusDot(has, tip) {
                const cls = has ? 'status-dot-available' : 'status-dot-missing';
                const t = String(tip ?? '').trim() || (has ? '' : 'No data');
                const title = t ? ' title="' + escAttr(t) + '"' : '';
                return '<span class="status-dot-indicator ' + cls + '"' + title + '></span>';
            }

            function dash(v) {
                const t = String(v ?? '').trim();
                return t === '' ? '—' : escapeHtml(t);
            }

            const fmtImage = function (cell) {
                const url = cell.getValue();
                return url
                    ? '<img src="' + escAttr(url) + '" class="sku-thumb" alt="">'
                    : '<span class="sku-thumb-placeholder"><i class="bi bi-image"></i></span>';
            };

            const fmtSku = function (cell) {
                const d = cell.getData();
                const badge = d.group_id
                    ? ' <span class="badge bg-warning text-dark" style="font-size:.7rem;" title="Grouped entry">G</span>'
                    : '';
                return '<span title="' + escAttr(d.sku) + '">' + escapeHtml(d.sku) + '</span>' + badge;
            };

            const fmtOrderNum = function (cell) {
                const v = String(cell.getValue() || '').trim();
                if (!v) return '—';
                return '<button class="copy-order-btn" data-copy="' + escAttr(v) +
                    '" title="' + escAttr(v) + '"><i class="bi bi-clipboard"></i></button>';
            };

            function parseLossInput(raw) {
                const t = String(raw ?? '').trim().replace(/[$,]/g, '');
                if (t === '') return null;
                const n = parseFloat(t);
                return isNaN(n) ? null : Math.round(n * 100) / 100;
            }

            function formatLossInputValue(n) {
                if (n == null || n === '' || isNaN(parseFloat(n))) return '';
                return String(Math.round(parseFloat(n)));
            }

            function lossInputDisplayValue(d) {
                const tl = d?.total_loss;
                if (tl != null && tl !== '' && !isNaN(parseFloat(tl))) {
                    return formatLossInputValue(tl);
                }
                const price = d?.amz_price;
                if (price != null && price !== '' && !isNaN(parseFloat(price))) {
                    return formatLossInputValue(price);
                }
                return '';
            }

            function lossTooltipText(d) {
                const tl = d?.total_loss;
                const hasOverride = tl != null && tl !== '' && !isNaN(parseFloat(tl));
                const price = d?.amz_price;
                if (hasOverride) {
                    const parts = ['Loss $' + Number(tl).toFixed(2)];
                    if (price != null && price !== '' && !isNaN(parseFloat(price))) {
                        parts.push('(Amz $' + Number(price).toFixed(2) + ')');
                    }
                    return parts.join(' ');
                }
                if (price == null || isNaN(parseFloat(price))) {
                    return 'Click to enter Loss $';
                }
                const loss = d?.amz_loss;
                const orderQty = d?.order_qty;
                const qty = d?.qty;
                const lossQty = (orderQty != null && !isNaN(parseFloat(orderQty)) && parseFloat(orderQty) > 0)
                    ? orderQty
                    : qty;
                const parts = ['Amz price $' + Number(price).toFixed(2)];
                if (lossQty != null && !isNaN(parseFloat(lossQty))) {
                    parts.push('× qty ' + lossQty);
                }
                if (loss != null && !isNaN(parseFloat(loss))) {
                    parts.push('= total loss $' + Number(loss).toFixed(2));
                }
                return parts.join(' ');
            }

            const fmtLoss = function (cell) {
                const d = cell.getData();
                const v = lossInputDisplayValue(d);
                return '<input type="number" step="0.01" class="form-control form-control-sm issue-loss-input" ' +
                    'value="' + escAttr(v) + '" data-original="' + escAttr(v) + '" ' +
                    'data-issue-id="' + escAttr(String(d.id)) + '" inputmode="decimal" ' +
                    'autocomplete="off" aria-label="Loss $" title="' + escAttr(lossTooltipText(d)) + '">';
            };

            async function saveTotalLossFromInput(input) {
                const id = input.getAttribute('data-issue-id');
                if (!id) return;
                const parsed = parseLossInput(input.value);
                const newDisplay = parsed == null ? '' : formatLossInputValue(parsed);
                const original = input.getAttribute('data-original') || '';
                if (newDisplay === original) {
                    input.value = original;
                    return;
                }
                try {
                    const res = await fetch(URLS.updateBase + '/' + encodeURIComponent(id) + '/total-loss', {
                        method: 'PATCH',
                        headers: jsonHeaders,
                        body: JSON.stringify({ total_loss: parsed }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(data.message || 'Save failed');
                    const saved = data.total_loss != null ? data.total_loss : null;
                    const row = table ? table.getRow(id) : null;
                    if (row) {
                        row.update({ total_loss: saved });
                    }
                    if (table) {
                        const rows = table.getData();
                        updateDataBadges(rows);
                        computeL30Badges(rows);
                    }
                    if (!row) {
                        const next = lossInputDisplayValue({
                            total_loss: saved,
                            amz_price: null,
                        });
                        input.value = next;
                        input.setAttribute('data-original', next);
                    }
                } catch (e) {
                    alert(e.message || 'Could not save Loss $');
                    input.value = original;
                }
            }

            const fmtWhatHappened = function (cell) {
                const t = String(cell.getValue() || '').trim();
                if (!t) return '—';
                if (t.toLowerCase() === '0 stock') return '<span class="what-happened-dot" title="0 Stock"></span>';
                if (t.toLowerCase() === 'damaged') return '<span class="what-happened-dot what-happened-dot-damaged" title="Damaged"></span>';
                // Show the full text, wrapping onto new lines when it's longer than the column.
                return '<div style="white-space: normal; word-break: break-word; line-height: 1.3;">' + escapeHtml(t) + '</div>';
            };

            const fmtAction = function (cell) {
                const d = cell.getData();
                const a = String(d.action_1 || '').trim();
                const r = String(d.action_1_remark || '').trim();
                if (!a) return r ? escapeHtml(r) : '—';
                return r ? escapeHtml(a + ': ' + r) : escapeHtml(a);
            };

            const fmtTracking = function (cell) {
                const t = String(cell.getValue() || '').trim();
                if (!t) return '—';
                return '<span class="tracking-cell"><span class="tracking-dot">•</span>' +
                    '<span class="tracking-full">' + escapeHtml(t) + '</span>' +
                    '<button class="copy-tracking-btn" data-copy="' + escAttr(t) +
                    '" title="Copy tracking"><i class="bi bi-clipboard"></i></button></span>';
            };

            const fmtIssueImg = function (cell) {
                const u = String(cell.getValue() || '').trim();
                if (!u) return '—';
                return '<a href="' + escAttr(u) + '" target="_blank" rel="noopener"><img src="' + escAttr(u) +
                    '" class="issue-img-thumb" loading="lazy" alt=""></a>';
            };

            const fmtLink = function (cell) {
                const t = String(cell.getValue() || '').trim();
                if (!t) return '—';
                let href = t;
                if (!/^https?:\/\//i.test(t)) href = (/^\/\//.test(t) ? 'https:' : 'https://') + t;
                return '<a href="' + escAttr(href) + '" target="_blank" rel="noopener noreferrer" class="issue-link-icon" title="' +
                    escAttr(t) + '"><i class="bi bi-link-45deg fs-5"></i></a>';
            };

            const fmtRootCause = function (cell) {
                const d = cell.getData();
                const root = String(d.issue || '').trim();
                const rmk = String(d.issue_remark || '').trim();
                const tip = (!root && !rmk) ? 'No data' : (!root ? rmk : (rmk ? root + ': ' + rmk : root));
                return statusDot(!!(root || rmk), tip);
            };

            const fmtRootCauseFixed = function (cell) {
                const d = cell.getData();
                const fx = String(d.c_action_1 || '').trim();
                const rmk = String(d.c_action_1_remark || '').trim();
                const tip = (!fx && !rmk) ? 'No data' : (!fx ? rmk : (rmk ? fx + ': ' + rmk : fx));
                return statusDot(!!(fx || rmk), tip);
            };

            const fmtCtn = function () {
                return statusDot(false, 'No matching product_master row');
            };

            const fmtCreatedAt = function (cell) {
                const d = cell.getData();
                const disp = String(d.created_at_short || '').trim();
                if (!disp) return '';
                const raw = d.created_at ? new Date(String(d.created_at).replace(' ', 'T')) : null;
                const stale = raw && !isNaN(raw.getTime()) && (Date.now() - raw.getTime()) > 14 * 24 * 60 * 60 * 1000;
                return stale ? '<span class="text-danger">' + escapeHtml(disp) + '</span>' : escapeHtml(disp);
            };

            // Merged "Created By" + "Created At" (e.g. "Riya Sheth · 8 Jul").
            const fmtCreatedByAt = function (cell) {
                const d = cell.getData();
                const name = String(d.created_by || '').trim();
                const disp = String(d.created_at_short || '').trim();
                if (!name && !disp) return '—';
                const raw = d.created_at ? new Date(String(d.created_at).replace(' ', 'T')) : null;
                const stale = raw && !isNaN(raw.getTime()) && (Date.now() - raw.getTime()) > 14 * 24 * 60 * 60 * 1000;
                const namePart = name ? escapeHtml(name) : '';
                let datePart = '';
                if (disp) {
                    datePart = stale ? '<span class="text-danger">' + escapeHtml(disp) + '</span>' : escapeHtml(disp);
                }
                if (namePart && datePart) return namePart + ' · ' + datePart;
                return namePart || datePart;
            };

            const fmtActions = function () {
                return '<div><button type="button" class="sil-row-btn sil-edit" title="Edit"><i class="bi bi-pencil-fill"></i></button>' +
                    '<button type="button" class="sil-row-btn sil-danger sil-archive" title="Archive"><i class="bi bi-archive-fill"></i></button></div>';
            };

            // NFE (No Fault of Executive): 'yes' → red dot, 'no' → yellow dot, unset → empty circle.
            const fmtNfe = function (cell) {
                const v = String(cell.getValue() || '').trim().toLowerCase();
                let color = 'transparent';
                let border = '1px solid #ced4da';
                if (v === 'yes') { color = '#dc3545'; border = 'none'; }
                else if (v === 'no') { color = '#ffc107'; border = 'none'; }
                return '<span class="sil-nfe-dot" style="display:inline-block;width:12px;height:12px;border-radius:50%;' +
                    'background-color:' + color + ';border:' + border + ';cursor:pointer;" title="No Fault of Executive"></span>';
            };

            let table;

            document.addEventListener('DOMContentLoaded', function () {
                table = new Tabulator('#shipping-tabulator', {
                    layout: 'fitDataStretch',
                    height: '100%',
                    placeholder: 'No shipping issues yet.',
                    index: 'id',
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [25, 50, 100, 200],
                    paginationCounter: 'rows',
                    reactiveData: false,
                    columnDefaults: { tooltip: true },
                    columns: [
                        { title: '#', field: 'id', width: 55 },
                        { title: '', field: 'image_url', width: 56, formatter: fmtImage, headerSort: false },
                        { title: 'SKU', field: 'sku', width: 120, formatter: fmtSku },
                        { title: 'Ord', field: 'order_number', width: 60, formatter: fmtOrderNum, hozAlign: 'center' },
                        { title: 'Loss $', field: 'total_loss', width: 90, formatter: fmtLoss, tooltip: false, headerSort: false },
                        { title: 'QTY', field: 'order_qty', width: 60, formatter: function (c) { return dash(c.getValue()); } },
                        { title: 'MKT', field: 'marketplace_1', width: 90, formatter: function (c) { return dash(c.getValue()); } },
                        { title: 'Issue?', field: 'what_happened', width: 180, formatter: fmtWhatHappened, hozAlign: 'center', variableHeight: true },
                        { title: 'Action', field: 'action_1', width: 120, formatter: fmtAction },
                        { title: 'Tracking', field: 'tracking_number', width: 80, formatter: fmtTracking, hozAlign: 'center', visible: false },
                        { title: 'Track R', field: 'replacement_tracking', width: 80, formatter: fmtTracking, hozAlign: 'center', visible: false },
                        { title: 'Img 1', field: 'image_1_url', width: 60, formatter: fmtIssueImg, headerSort: false, hozAlign: 'center', visible: false },
                        { title: 'Img 2', field: 'image_2_url', width: 60, formatter: fmtIssueImg, headerSort: false, hozAlign: 'center', visible: false },
                        { title: 'Link', field: 'issue_link', width: 50, formatter: fmtLink, headerSort: false, hozAlign: 'center', visible: false },
                        { title: 'Root Cause Found', field: 'issue', width: 60, formatter: fmtRootCause, hozAlign: 'center', visible: false },
                        { title: 'Instructions CTN', field: '_ctn', width: 60, formatter: fmtCtn, headerSort: false, hozAlign: 'center', visible: false },
                        { title: 'Root Cause Fixed', field: 'c_action_1', width: 60, formatter: fmtRootCauseFixed, hozAlign: 'center', visible: false },
                        { title: 'NFE', field: 'nfe', width: 55, formatter: fmtNfe, headerSort: false, hozAlign: 'center', headerTooltip: 'No Fault of Executive' },
                        { title: 'Dept', field: 'department', width: 90, formatter: function (c) { return dash(c.getValue()); }, visible: false },
                        { title: 'Close', field: '_actions', width: 70, formatter: fmtActions, headerSort: false, hozAlign: 'center', frozen: true },
                        { title: 'Created By', field: 'created_by', width: 170, formatter: fmtCreatedByAt },
                    ],
                });

                table.on('cellClick', function (e, cell) {
                    const copyBtn = e.target.closest('.copy-order-btn, .copy-tracking-btn');
                    if (copyBtn) {
                        const text = copyBtn.getAttribute('data-copy') || '';
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(text).then(function () {
                                copyBtn.classList.add('copied');
                                copyBtn.innerHTML = '<i class="bi bi-clipboard-check"></i>';
                                setTimeout(function () {
                                    copyBtn.classList.remove('copied');
                                    copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>';
                                }, 1200);
                            });
                        }
                        return;
                    }
                    if (cell.getField() === 'nfe') {
                        openNfeModal(cell.getRow().getData());
                        return;
                    }
                    if (cell.getField() !== '_actions') return;
                    const target = e.target.closest('button');
                    if (!target) return;
                    const data = cell.getRow().getData();
                    if (target.classList.contains('sil-edit')) {
                        openModal(data);
                    } else if (target.classList.contains('sil-archive')) {
                        archiveRow(data.id);
                    }
                });

                table.on('tableBuilt', function () {
                    loadColumnVisibility();
                    refreshAll();
                    loadDropdownOptions('root_cause_found', 'sil-root-cause-found-datalist');
                    loadDropdownOptions('root_cause_fixed', 'sil-root-cause-fixed-datalist');
                });

                wireUi();
            });

            function buildColumnsMenu() {
                const menu = document.getElementById('sil-columns-menu');
                if (!menu) return;
                const labels = { image_url: 'Image', _ctn: 'Instructions CTN', _actions: 'Close' };
                menu.innerHTML = '';
                table.getColumns().forEach(function (col) {
                    const field = col.getField();
                    if (!field) return;
                    const def = col.getDefinition();
                    const title = labels[field] || (def.title && def.title.trim() ? def.title : field);
                    const id = 'sil-col-' + field;
                    const wrap = document.createElement('div');
                    wrap.className = 'form-check';
                    wrap.innerHTML = '<input class="form-check-input" type="checkbox" id="' + id + '"' +
                        (col.isVisible() ? ' checked' : '') + '>' +
                        '<label class="form-check-label" for="' + id + '">' + escapeHtml(title) + '</label>';
                    const input = wrap.querySelector('input');
                    input.addEventListener('change', function () {
                        if (this.checked) { col.show(); } else { col.hide(); }
                        saveColumnVisibility();
                    });
                    menu.appendChild(wrap);
                });
            }

            async function loadColumnVisibility() {
                try {
                    const res = await fetch(URLS.colVisGet + '?channel=' + encodeURIComponent(COLVIS_CHANNEL), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const map = await res.json();
                    if (map && typeof map === 'object') {
                        table.getColumns().forEach(function (col) {
                            const f = col.getField();
                            if (!f || !(f in map)) return;
                            if (map[f]) { col.show(); } else { col.hide(); }
                        });
                    }
                } catch (e) { /* ignore */ }
                buildColumnsMenu();
            }

            async function saveColumnVisibility() {
                const visibility = {};
                table.getColumns().forEach(function (col) {
                    const f = col.getField();
                    if (f) visibility[f] = col.isVisible();
                });
                try {
                    await fetch(URLS.colVisSet, {
                        method: 'POST',
                        headers: jsonHeaders,
                        body: JSON.stringify({ channel: COLVIS_CHANNEL, visibility: visibility }),
                    });
                } catch (e) { /* ignore */ }
            }

            async function loadData() {
                try {
                    const res = await fetch(URLS.list + '?department=' + encodeURIComponent(DEPARTMENT), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const json = await res.json();
                    const rows = Array.isArray(json && json.data) ? json.data : [];
                    table.replaceData(rows);
                    updateDataBadges(rows);
                    computeL30Badges(rows);
                } catch (e) {
                    console.error('Failed to load shipping issues', e);
                }
            }

            function setBadge(id, value) {
                const el = document.getElementById(id);
                if (el) el.textContent = value;
            }

            function updateDataBadges(rows) {
                let totalLoss = 0;
                let totalRefund = 0;
                let exccLoss = 0;
                let execFaultCount = 0;
                rows.forEach(function (r) {
                    const rowLoss = (r.total_loss != null && r.total_loss !== '' && !isNaN(parseFloat(r.total_loss)))
                        ? Number(r.total_loss)
                        : (Number(r.amz_loss) || 0);
                    totalLoss += rowLoss;
                    totalRefund += Number(r.refund_amount) || 0;
                    if (String(r.nfe || '').trim().toLowerCase() === 'yes') {
                        exccLoss += rowLoss;
                        execFaultCount++;
                    }
                });
                setBadge('sil-stat-total', rows.length.toLocaleString());
                setBadge('sil-stat-loss', money(totalLoss));
                setBadge('sil-stat-refund', money(totalRefund));
                setBadge('sil-stat-excc-loss', money(exccLoss));
                setBadge('sil-stat-exec-fault', execFaultCount.toLocaleString());
            }

            function computeL30Badges(rows) {
                const cutoff = new Date();
                cutoff.setHours(0, 0, 0, 0);
                cutoff.setDate(cutoff.getDate() - 29);
                let issues = 0;
                let loss = 0;
                rows.forEach(function (r) {
                    const raw = r.created_at;
                    if (!raw) return;
                    const d = new Date(String(raw).replace(' ', 'T'));
                    if (isNaN(d.getTime()) || d < cutoff) return;
                    issues++;
                    loss += (r.total_loss != null && r.total_loss !== '' && !isNaN(parseFloat(r.total_loss)))
                        ? Number(r.total_loss)
                        : (Number(r.amz_loss) || 0);
                });
                setBadge('sil-stat-l30-issues', issues.toLocaleString());
                setBadge('sil-stat-l30-loss', money(loss));
            }

            function refreshAll() {
                loadData();
            }

            async function archiveRow(id) {
                if (!id || !confirm('Archive this issue?')) return;
                try {
                    const res = await fetch(URLS.archiveBase + '/' + id + '/archive', {
                        method: 'POST',
                        headers: jsonHeaders,
                        body: '{}',
                    });
                    if (!res.ok) {
                        const err = await res.json().catch(function () { return {}; });
                        throw new Error(err.message || 'Archive failed (' + res.status + ').');
                    }
                    refreshAll();
                } catch (e) {
                    alert(e.message || 'Archive failed.');
                }
            }

            // ---- NFE (No Fault of Executive) modal ----
            let nfeModal;
            let nfeRowId = null;

            function openNfeModal(data) {
                nfeRowId = data.id;
                const current = String(data.nfe || '').trim().toLowerCase();
                document.querySelectorAll('input[name="sil-nfe-choice"]').forEach(function (r) {
                    r.checked = (r.value === current);
                });
                if (!nfeModal) nfeModal = new bootstrap.Modal(document.getElementById('sil-nfe-modal'));
                nfeModal.show();
            }

            async function saveNfe() {
                if (!nfeRowId) return;
                const chosen = document.querySelector('input[name="sil-nfe-choice"]:checked');
                const value = chosen ? chosen.value : null;
                const btn = document.getElementById('sil-nfe-save');
                btn.disabled = true;
                try {
                    const res = await fetch(URLS.nfeBase + '/' + nfeRowId + '/nfe', {
                        method: 'PATCH',
                        headers: jsonHeaders,
                        body: JSON.stringify({ nfe: value }),
                    });
                    if (!res.ok) {
                        const err = await res.json().catch(function () { return {}; });
                        throw new Error(err.message || 'Save failed (' + res.status + ').');
                    }
                    const row = table.getRow(nfeRowId);
                    if (row) row.update({ nfe: value });
                    updateDataBadges(table.getData());
                    nfeModal.hide();
                } catch (e) {
                    alert(e.message || 'Save failed.');
                } finally {
                    btn.disabled = false;
                }
            }

            // ---- Modal handling ----
            const modalFields = [
                'sku', 'parent', 'qty', 'order_qty', 'order_number', 'marketplace_1', 'marketplace_2',
                'what_happened', 'issue', 'issue_remark', 'action_1', 'action_1_remark', 'replacement_tracking',
                'c_action_1', 'c_action_1_remark', 'tracking_number', 'refund_amount', 'total_loss',
                'issue_link', 'close_note',
            ];
            let modal;

            function openModal(data) {
                const form = document.getElementById('sil-issue-form');
                form.reset();
                document.getElementById('sil-id').value = (data && data.id) || '';
                document.getElementById('sil-issue-modal-title').textContent = data && data.id
                    ? 'Edit Shipping Issue #' + data.id
                    : 'Add Shipping Issue';
                if (data) {
                    modalFields.forEach(function (f) {
                        const el = document.getElementById('sil-' + f);
                        if (el) el.value = data[f] === null || data[f] === undefined ? '' : data[f];
                    });
                }
                modal.show();
            }

            function wireUi() {
                modal = new bootstrap.Modal(document.getElementById('sil-issue-modal'));
                const form = document.getElementById('sil-issue-form');

                document.getElementById('sil-add').addEventListener('click', function () { openModal(null); });
                document.getElementById('sil-refresh').addEventListener('click', refreshAll);
                document.getElementById('sil-nfe-save').addEventListener('click', saveNfe);

                document.getElementById('shipping-tabulator')?.addEventListener('focusout', function (e) {
                    const inp = e.target.closest('.issue-loss-input');
                    if (inp) saveTotalLossFromInput(inp);
                });
                document.getElementById('shipping-tabulator')?.addEventListener('keydown', function (e) {
                    const inp = e.target.closest('.issue-loss-input');
                    if (inp && e.key === 'Enter') {
                        e.preventDefault();
                        inp.blur();
                    }
                });

                document.getElementById('sil-search').addEventListener('input', function () {
                    const term = this.value.trim();
                    if (!term) {
                        table.clearFilter(false);
                        return;
                    }
                    // Search only within the "Issue?" column (what_happened).
                    table.setFilter('what_happened', 'like', term);
                });

                form.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const data = { id: document.getElementById('sil-id').value || null };
                    modalFields.forEach(function (f) {
                        const el = document.getElementById('sil-' + f);
                        data[f] = el ? el.value : null;
                    });
                    if (!data.sku || !data.sku.trim()) { alert('SKU is required.'); return; }
                    if (data.qty === '' || data.qty === null) { alert('QTY is required.'); return; }

                    const btn = document.getElementById('sil-save-btn');
                    btn.disabled = true;
                    try {
                        await persistRow(data);
                        modal.hide();
                        refreshAll();
                    } catch (err) {
                        alert(err.message || 'Save failed.');
                    } finally {
                        btn.disabled = false;
                    }
                });

                document.getElementById('sil-sku').addEventListener('blur', async function () {
                    const sku = this.value.trim();
                    if (!sku) return;
                    try {
                        const res = await fetch(URLS.skuDetails + '?sku=' + encodeURIComponent(sku), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const info = await res.json();
                        if (info && info.found) {
                            if (!document.getElementById('sil-parent').value && info.parent) {
                                document.getElementById('sil-parent').value = info.parent;
                            }
                            if (!document.getElementById('sil-qty').value && info.qty !== undefined && info.qty !== null) {
                                document.getElementById('sil-qty').value = info.qty;
                            }
                        }
                    } catch (e) { /* ignore lookup errors */ }
                });
            }

            async function loadDropdownOptions(fieldType, datalistId) {
                try {
                    const res = await fetch(URLS.dropdownOptions + '?field_type=' + encodeURIComponent(fieldType), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const json = await res.json();
                    const opts = Array.isArray(json && json.data) ? json.data : [];
                    const dl = document.getElementById(datalistId);
                    if (dl) {
                        dl.innerHTML = opts.map(function (o) {
                            return '<option value="' + String(o).replace(/"/g, '&quot;') + '"></option>';
                        }).join('');
                    }
                } catch (e) { /* ignore */ }
            }
        })();
    </script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shipping Issues (Label)',
        'sub_title' => 'Customer Care',
    ])

    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-primary" id="sil-add">
                        <i class="fa-solid fa-plus"></i> Add Issue
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="sil-refresh" title="Refresh">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" id="sil-columns-btn"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            <i class="fa-solid fa-table-columns"></i> Columns
                        </button>
                        <div class="dropdown-menu p-2" id="sil-columns-menu" style="max-height: 60vh; overflow-y: auto; min-width: 220px;">
                        </div>
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2 flex-grow-1 justify-content-between">
                        <span class="badge fs-6 p-2" style="display: none; color: black; font-weight: bold; background-color: #c7d2fe;"
                            title="Total active shipping issues">Total Issues: <span id="sil-stat-total">…</span></span>
                        <span class="badge fs-6 p-2" style="color: black; font-weight: bold; background-color: #fed7aa;"
                            title="Issues created in the last 30 days">L30 Issues: <span id="sil-stat-l30-issues">…</span></span>
                        <span class="badge fs-6 p-2" style="color: black; font-weight: bold; background-color: #fecaca;"
                            title="Total loss in the last 30 days">L30 Loss: <span id="sil-stat-l30-loss">…</span></span>
                        <span class="badge fs-6 p-2" style="color: black; font-weight: bold; background-color: #f5d0fe;"
                            title="Total loss across all shipping issues">Total Loss: <span id="sil-stat-loss">…</span></span>
                        <span class="badge fs-6 p-2" style="display: none; color: black; font-weight: bold; background-color: #a7f3d0;"
                            title="Total refund amount across all shipping issues">Total Refund: <span
                                id="sil-stat-refund">…</span></span>
                        <span class="badge fs-6 p-2" style="color: #fff; font-weight: bold; background-color: #dc3545;"
                            title="Total loss for issues marked Yes in the NFE column">Excc Loss: <span
                                id="sil-stat-excc-loss">…</span></span>
                        <span class="badge fs-6 p-2" style="color: #fff; font-weight: bold; background-color: #dc3545;"
                            title="Count of issues marked Yes in the NFE column">Exec Fault: <span
                                id="sil-stat-exec-fault">…</span></span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="shipping-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sil-search" class="form-control form-control-sm" maxlength="50"
                            placeholder="Search issue (max 50 characters)...">
                    </div>
                    <div id="shipping-tabulator" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add / Edit modal --}}
    <div class="modal fade" id="sil-issue-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="sil-issue-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="sil-issue-modal-title">Add Shipping Issue</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="sil-id" name="id">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">SKU <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="sil-sku" name="sku" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Parent</label>
                                <input type="text" class="form-control" id="sil-parent" name="parent">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">QTY <span class="text-danger">*</span></label>
                                <input type="number" step="any" class="form-control" id="sil-qty" name="qty" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Order QTY</label>
                                <input type="number" step="any" class="form-control" id="sil-order_qty" name="order_qty">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Order #</label>
                                <input type="text" class="form-control" id="sil-order_number" name="order_number">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Marketplace 1</label>
                                <input type="text" class="form-control" id="sil-marketplace_1" name="marketplace_1"
                                    list="sil-marketplace-datalist">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Marketplace 2</label>
                                <input type="text" class="form-control" id="sil-marketplace_2" name="marketplace_2"
                                    list="sil-marketplace-datalist">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">What Happened</label>
                                <input type="text" class="form-control" id="sil-what_happened" name="what_happened">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Root Cause Found</label>
                                <input type="text" class="form-control" id="sil-issue" name="issue"
                                    list="sil-root-cause-found-datalist">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Issue Remark</label>
                                <input type="text" class="form-control" id="sil-issue_remark" name="issue_remark">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Action 1</label>
                                <input type="text" class="form-control" id="sil-action_1" name="action_1">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Action 1 Remark</label>
                                <input type="text" class="form-control" id="sil-action_1_remark" name="action_1_remark">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Replacement Tracking</label>
                                <input type="text" class="form-control" id="sil-replacement_tracking"
                                    name="replacement_tracking">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Root Cause Fixed</label>
                                <input type="text" class="form-control" id="sil-c_action_1" name="c_action_1"
                                    list="sil-root-cause-fixed-datalist">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Root Cause Fixed Remark</label>
                                <input type="text" class="form-control" id="sil-c_action_1_remark" name="c_action_1_remark">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Tracking #</label>
                                <input type="text" class="form-control" id="sil-tracking_number" name="tracking_number">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Refund Amount</label>
                                <input type="number" step="any" class="form-control" id="sil-refund_amount"
                                    name="refund_amount">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Loss</label>
                                <input type="number" step="any" class="form-control" id="sil-total_loss" name="total_loss">
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">Issue Link</label>
                                <input type="url" class="form-control" id="sil-issue_link" name="issue_link">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-control" value="Shipping" disabled>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Close Note</label>
                                <input type="text" class="form-control" id="sil-close_note" name="close_note">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="sil-save-btn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- NFE (No Fault of Executive) modal --}}
    <div class="modal fade" id="sil-nfe-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Fault of Executive</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="sil-nfe-choice" id="sil-nfe-yes" value="yes">
                        <label class="form-check-label d-flex align-items-center gap-2" for="sil-nfe-yes">
                            <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#dc3545;"></span>
                            Yes
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="sil-nfe-choice" id="sil-nfe-no" value="no">
                        <label class="form-check-label d-flex align-items-center gap-2" for="sil-nfe-no">
                            <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#ffc107;"></span>
                            No
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="sil-nfe-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    <datalist id="sil-marketplace-datalist">
        @foreach (($marketplaces ?? collect()) as $mp)
            <option value="{{ $mp }}"></option>
        @endforeach
    </datalist>
    <datalist id="sil-root-cause-found-datalist"></datalist>
    <datalist id="sil-root-cause-fixed-datalist"></datalist>
@endsection
