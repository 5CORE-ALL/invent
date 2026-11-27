@extends('layouts.vertical', ['title' => 'GOFO Exceptions', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .ge-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 12px; }
        .ge-toolbar .ge-search { max-width: 300px; }
        .ge-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; background: #f1f5f9; color: #334155; font-size: 12.5px; font-weight: 600; }
        .ge-chip b { color: #0f172a; font-size: 14px; }
        .ge-chip.loss b { color: #dc2626; }
        .ge-legend { display: inline-flex; gap: 10px; align-items: center; font-size: 12px; color: #64748b; margin-left: auto; }
        .ge-legend i { display: inline-block; width: 12px; height: 12px; border-radius: 3px; vertical-align: -2px; margin-right: 4px; }
        .ge-legend .man { background: #fde047; }
        .ge-legend .aut { background: #cbd5e1; }

        #gofo-exceptions-table.tabulator { font-size: 13px; border-radius: 10px; }
        #gofo-exceptions-table .tabulator-header .tabulator-col { background: #cbd5e1; }
        #gofo-exceptions-table .tabulator-header .tabulator-col.ge-manual { background: #fde68a; }
        #gofo-exceptions-table .tabulator-header .tabulator-col .tabulator-col-title { font-weight: 700; color: #0f172a; white-space: normal; text-align: center; }
        #gofo-exceptions-table .tabulator-row .tabulator-cell { padding: 6px 8px; vertical-align: middle; }
        #gofo-exceptions-table .tabulator-row .tabulator-cell.ge-edit { cursor: text; }
        #gofo-exceptions-table .tabulator-row .tabulator-cell.ge-edit:hover { background: #fffbeb; }
        #gofo-exceptions-table .tabulator-row.ge-busy { opacity: .55; pointer-events: none; }
        #gofo-exceptions-table .tabulator-row.ge-new .tabulator-cell { background: #f0fdf4; }

        .ge-trk { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-weight: 600; color: #0f172a; }
        .ge-trk.empty, .ge-empty { color: #94a3b8; font-weight: 400; font-style: italic; }
        .ge-badge { display: inline-block; margin-top: 2px; padding: 1px 7px; border-radius: 999px; font-size: 10.5px; font-weight: 700; background: #e0e7ff; color: #3730a3; }
        .ge-badge.warn { background: #fee2e2; color: #b91c1c; }
        .ge-sku { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-weight: 600; }
        .ge-thumb { width: 44px; height: 44px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; cursor: zoom-in; background: #fff; }
        .ge-noimg { display: inline-flex; width: 44px; height: 44px; align-items: center; justify-content: center; border-radius: 8px; border: 1px dashed #cbd5e1; color: #cbd5e1; }
        .ge-loss { font-weight: 800; color: #dc2626; }
        .ge-loss small { display: block; font-size: 10px; font-weight: 600; color: #94a3b8; }
        .ge-loss small.man { color: #b45309; }
        .ge-proof { display: flex; align-items: center; gap: 6px; cursor: pointer; min-width: 150px; }
        .ge-proof .ge-pthumbs { display: flex; }
        .ge-proof .ge-pthumbs img { width: 30px; height: 30px; object-fit: cover; border-radius: 6px; border: 2px solid #fff; margin-right: -8px; box-shadow: 0 0 0 1px #e2e8f0; }
        .ge-proof .ge-pmore { margin-left: 10px; font-size: 11px; font-weight: 700; color: #475569; }
        .ge-proof .ge-pnote { font-size: 12px; color: #475569; max-width: 190px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ge-proof .ge-padd { font-size: 12px; color: #2563eb; font-weight: 600; }
        .ge-del { border: 0; background: transparent; color: #cbd5e1; padding: 2px 6px; border-radius: 6px; }
        .ge-del:hover { color: #dc2626; background: #fee2e2; }

        .ge-drop { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 18px; text-align: center; color: #64748b; cursor: pointer; transition: .15s; }
        .ge-drop.over, .ge-drop:hover { border-color: #6366f1; background: #eef2ff; color: #4338ca; }
        .ge-files { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 10px; margin-top: 12px; }
        .ge-file { position: relative; border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; aspect-ratio: 1; background: #f8fafc; }
        .ge-file img { width: 100%; height: 100%; object-fit: cover; cursor: zoom-in; }
        .ge-file button { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; border: 0; border-radius: 50%; background: rgba(15, 23, 42, .75); color: #fff; font-size: 12px; line-height: 1; }
        .ge-file button:hover { background: #dc2626; }

        .ge-lightbox { position: fixed; inset: 0; z-index: 2000; background: rgba(15, 23, 42, .88); display: none; align-items: center; justify-content: center; cursor: zoom-out; }
        .ge-lightbox.show { display: flex; }
        .ge-lightbox img { max-width: 92vw; max-height: 90vh; border-radius: 10px; box-shadow: 0 20px 60px rgba(0, 0, 0, .5); }

        .ge-toasts { position: fixed; right: 18px; bottom: 18px; z-index: 2100; display: flex; flex-direction: column; gap: 8px; }
        .ge-toast { max-width: 360px; padding: 10px 14px; border-radius: 10px; color: #fff; font-size: 13px; font-weight: 600; box-shadow: 0 8px 24px rgba(0, 0, 0, .2); background: #0f172a; }
        .ge-toast.ok { background: #15803d; }
        .ge-toast.warn { background: #b45309; }
        .ge-toast.err { background: #b91c1c; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'GOFO Exceptions',
        'sub_title'  => 'Damaged / oversize / discarded parcels',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="ge-toolbar">
                        <button type="button" class="btn btn-primary btn-sm" id="ge-add"><i class="fas fa-plus me-1"></i> Add exception</button>
                        <input type="search" id="ge-search" class="form-control form-control-sm ge-search" placeholder="Search tracking, order, SKU, notes…">
                        <span class="ge-chip">Rows <b id="ge-count">0</b></span>
                        <span class="ge-chip loss">Total loss <b id="ge-total">$0.00</b></span>
                        <span class="ge-legend">
                            <span><i class="man"></i>You enter</span>
                            <span><i class="aut"></i>Auto-fetched from tracking</span>
                        </span>
                    </div>

                    <div id="gofo-exceptions-table"></div>
                    <div class="text-muted small mt-2">
                        Click a cell to edit it. Type the tracking number and the order ID, SKU, qty, SKU image and loss are filled in automatically.
                        Loss = SKU selling price on our site × qty; type your own amount to override it, or clear the cell to go back to the automatic value.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="geProofModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0"><i class="fas fa-paperclip me-2"></i>Proof &amp; notes</h5>
                        <div class="small text-muted" id="ge-proof-sub"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold" for="ge-notes">Notes</label>
                    <textarea id="ge-notes" class="form-control" rows="4" maxlength="5000" placeholder="What happened, who was told, what was agreed…"></textarea>
                    <div class="d-flex justify-content-end mt-2">
                        <button type="button" class="btn btn-success btn-sm" id="ge-notes-save"><i class="fas fa-check me-1"></i>Save notes</button>
                    </div>

                    <hr>
                    <label class="form-label fw-semibold">Images</label>
                    <div class="ge-drop" id="ge-drop" tabindex="0">
                        <i class="fas fa-cloud-arrow-up fa-lg d-block mb-1"></i>
                        Click to choose images, drag them here, or paste a screenshot (Ctrl+V)
                        <div class="small mt-1" id="ge-upload-status"></div>
                    </div>
                    <input type="file" id="ge-file-input" accept="image/*" multiple class="d-none">
                    <div class="ge-files" id="ge-files"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="ge-lightbox" id="ge-lightbox"><img alt="" src=""></div>
    <div class="ge-toasts" id="ge-toasts"></div>
@endsection

@section('script-bottom')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const URLS = {
        data: @json(route('gofo.exceptions.data')),
        store: @json(route('gofo.exceptions.store')),
        base: @json(url('/gofo-exceptions')),
    };

    const MAIL_RESPONSES = ['Return', 'Discarded', 'Discarded by GOFO'];
    const CC_ACTIONS = ['Replaced', 'Refund requested', 'Refunded', 'Send replacement'];
    const RESP_DEPTS = ['Impossible'];

    let table = null;
    let suppress = false;
    let proofRowId = null;
    let proofModal = null;

    /* ------------------------------------------------------------ helpers */
    function toast(message, kind) {
        const box = document.getElementById('ge-toasts');
        const el = document.createElement('div');
        el.className = 'ge-toast ' + (kind || '');
        el.textContent = message;
        box.appendChild(el);
        setTimeout(function () { el.remove(); }, kind === 'err' ? 6500 : 3800);
    }

    async function post(url, body) {
        const isForm = body instanceof FormData;
        const res = await fetch(url, {
            method: 'POST',
            headers: Object.assign({
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            }, isForm ? {} : { 'Content-Type': 'application/json' }),
            body: isForm ? body : JSON.stringify(body || {}),
            credentials: 'same-origin',
        });
        let json = null;
        try { json = await res.json(); } catch (e) { /* not json */ }
        if (!res.ok || !json || json.success === false) {
            let msg = (json && json.message) || ('Request failed (' + res.status + ')');
            if (json && json.errors) {
                const first = Object.values(json.errors)[0];
                if (first && first[0]) msg = first[0];
            }
            throw new Error(msg);
        }
        return json;
    }

    function fmtMoney(v) {
        const n = Number(v);
        return Number.isFinite(n) ? '$' + n.toFixed(2) : '';
    }

    function fmtDate(v) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(v || '');
        return m ? (Number(m[2]) + '/' + Number(m[3]) + '/' + m[1]) : '';
    }

    function el(tag, cls, text) {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = text;
        return n;
    }

    function openLightbox(src) {
        const box = document.getElementById('ge-lightbox');
        box.querySelector('img').src = src;
        box.classList.add('show');
    }
    document.getElementById('ge-lightbox').addEventListener('click', function () { this.classList.remove('show'); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') document.getElementById('ge-lightbox').classList.remove('show');
    });

    function refreshStats() {
        if (!table) return;
        const rows = table.getData('active');
        let total = 0;
        rows.forEach(function (r) { const n = Number(r.loss_amount); if (Number.isFinite(n)) total += n; });
        document.getElementById('ge-count').textContent = rows.length;
        document.getElementById('ge-total').textContent = fmtMoney(total) || '$0.00';
    }

    /** Push server data into a row without it counting as a user edit. */
    function applyRow(row, data) {
        suppress = true;
        try {
            row.update(data);
            row.reformat();
        } finally {
            setTimeout(function () { suppress = false; }, 0);
        }
        refreshStats();
    }

    /* ------------------------------------------------------------ editors */
    function dateEditor(cell, onRendered, success, cancel) {
        const input = document.createElement('input');
        input.type = 'date';
        input.style.cssText = 'width:100%;box-sizing:border-box;padding:4px;';
        input.value = cell.getValue() || '';
        let done = false;
        function commit() { if (done) return; done = true; success(input.value || null); }
        onRendered(function () { input.focus(); });
        input.addEventListener('change', commit);
        input.addEventListener('blur', commit);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') commit();
            if (e.key === 'Escape') { done = true; cancel(); }
        });
        return input;
    }

    function listParams(field, seeds) {
        return {
            autocomplete: true,
            freetext: true,
            clearable: true,
            listOnEmpty: true,
            allowEmpty: true,
            valuesLookup: function (cell) {
                const seen = new Map();
                seeds.concat(cell.getTable().getData().map(function (r) { return r[field]; })).forEach(function (v) {
                    v = (v == null ? '' : String(v)).trim();
                    if (v && !seen.has(v.toLowerCase())) seen.set(v.toLowerCase(), v);
                });
                return Array.from(seen.values()).sort(function (a, b) { return a.localeCompare(b); });
            },
        };
    }

    function textFormatter(cell) {
        const v = (cell.getValue() == null ? '' : String(cell.getValue())).trim();
        return v ? v.replace(/&/g, '&amp;').replace(/</g, '&lt;') : '<span class="ge-empty">—</span>';
    }

    /* ------------------------------------------------------------ columns */
    const columns = [
        {
            title: 'Mail Date', field: 'mail_date', width: 112, hozAlign: 'center', headerSort: true,
            cssClass: 'ge-edit', editor: dateEditor,
            formatter: function (cell) { const t = fmtDate(cell.getValue()); return t || '<span class="ge-empty">—</span>'; },
        },
        {
            title: 'Tracking Number', field: 'tracking_number', minWidth: 210, headerSort: true,
            cssClass: 'ge-edit', editor: 'input',
            formatter: function (cell) {
                const v = (cell.getValue() || '').toString().trim();
                const d = cell.getRow().getData();
                const wrap = el('div');
                wrap.appendChild(el('span', 'ge-trk' + (v ? '' : ' empty'), v || 'type tracking number…'));
                if (v && d.lookup_status === 'not_found') {
                    wrap.appendChild(document.createElement('br'));
                    wrap.appendChild(el('span', 'ge-badge warn', 'No order found'));
                }
                return wrap;
            },
        },
        {
            title: 'Resp Dept', field: 'resp_dept', minWidth: 140, cssClass: 'ge-edit',
            editor: 'list', editorParams: listParams('resp_dept', RESP_DEPTS), formatter: textFormatter,
        },
        {
            title: 'Mail Response', field: 'mail_response', minWidth: 150, cssClass: 'ge-edit',
            editor: 'list', editorParams: listParams('mail_response', MAIL_RESPONSES), formatter: textFormatter,
        },
        {
            title: 'CC Action', field: 'cc_action', minWidth: 150, cssClass: 'ge-edit',
            editor: 'list', editorParams: listParams('cc_action', CC_ACTIONS), formatter: textFormatter,
        },
        {
            title: 'Proof / Notes', field: 'files_count', minWidth: 200, headerSort: false,
            formatter: function (cell) {
                const d = cell.getRow().getData();
                const files = d.files || [];
                const note = (d.notes || '').trim();
                const wrap = el('div', 'ge-proof');
                wrap.title = 'Click to add images and notes';
                if (files.length) {
                    const th = el('span', 'ge-pthumbs');
                    files.slice(0, 3).forEach(function (f) {
                        const img = el('img');
                        img.src = f.url; img.alt = '';
                        th.appendChild(img);
                    });
                    wrap.appendChild(th);
                    if (files.length > 3) wrap.appendChild(el('span', 'ge-pmore', '+' + (files.length - 3)));
                }
                if (note) {
                    wrap.appendChild(el('span', 'ge-pnote', note));
                } else if (!files.length) {
                    wrap.appendChild(el('span', 'ge-padd', '＋ Add image / note'));
                }
                return wrap;
            },
            cellClick: function (e, cell) { openProof(cell.getRow()); },
        },
        {
            title: 'Order ID', field: 'order_id', minWidth: 150, cssClass: 'ge-edit', editor: 'input',
            formatter: function (cell) {
                const v = (cell.getValue() || '').toString().trim();
                const d = cell.getRow().getData();
                if (!v) return '<span class="ge-empty">—</span>';
                const wrap = el('div');
                wrap.appendChild(el('span', 'ge-sku', v));
                if (d.channel_label) {
                    wrap.appendChild(document.createElement('br'));
                    wrap.appendChild(el('span', 'ge-badge', d.channel_label));
                }
                return wrap;
            },
        },
        {
            title: 'SKU', field: 'sku', minWidth: 170, cssClass: 'ge-edit', editor: 'input',
            formatter: function (cell) {
                const v = (cell.getValue() || '').toString().trim();
                if (!v) return '<span class="ge-empty">—</span>';
                const s = el('span', 'ge-sku', v);
                const t = (cell.getRow().getData().product_title || '').trim();
                if (t) s.title = t;
                return s;
            },
        },
        {
            title: 'Qty', field: 'quantity', width: 70, hozAlign: 'center', cssClass: 'ge-edit',
            editor: 'number', editorParams: { min: 1, step: 1 },
            formatter: function (cell) { const v = cell.getValue(); return v ? String(v) : '<span class="ge-empty">—</span>'; },
        },
        {
            title: 'SKU Image', field: 'sku_image', width: 86, hozAlign: 'center', headerSort: false,
            formatter: function (cell) {
                const src = (cell.getValue() || '').toString().trim();
                if (!src) { const n = el('span', 'ge-noimg'); n.innerHTML = '<i class="far fa-image"></i>'; return n; }
                const img = el('img', 'ge-thumb');
                img.src = src; img.alt = ''; img.loading = 'lazy';
                img.addEventListener('click', function (ev) { ev.stopPropagation(); openLightbox(src); });
                return img;
            },
        },
        {
            title: 'Loss $', field: 'loss_amount', width: 108, hozAlign: 'right', cssClass: 'ge-edit',
            editor: 'number', editorParams: { min: 0, step: 0.01 },
            formatter: function (cell) {
                const v = cell.getValue();
                const d = cell.getRow().getData();
                if (v === null || v === undefined || v === '') return '<span class="ge-empty">—</span>';
                const wrap = el('div', 'ge-loss', fmtMoney(v));
                const s = el('small', d.loss_manual ? 'man' : '', d.loss_manual ? 'set by hand' : 'SKU price × qty');
                s.title = d.loss_manual ? 'Clear the cell to use the SKU price again' : 'Selling price on our site' + (d.sku_price ? ' (' + fmtMoney(d.sku_price) + ')' : '') + ' × qty';
                wrap.appendChild(s);
                return wrap;
            },
        },
        {
            title: '', field: 'id', width: 46, hozAlign: 'center', headerSort: false,
            formatter: function () { return '<button type="button" class="ge-del" title="Delete this row"><i class="fas fa-trash"></i></button>'; },
            cellClick: function (e, cell) { e.stopPropagation(); deleteRow(cell.getRow()); },
        },
    ];

    const MANUAL_FIELDS = ['mail_date', 'tracking_number', 'resp_dept', 'mail_response', 'cc_action', 'files_count', 'loss_amount'];

    /* ------------------------------------------------------------ table */
    table = new Tabulator('#gofo-exceptions-table', {
        ajaxURL: URLS.data,
        ajaxConfig: { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
        ajaxResponse: function (url, params, response) { return (response && response.data) || []; },
        index: 'id',
        layout: 'fitDataStretch',
        height: '70vh',
        placeholder: 'No GOFO exceptions yet — click “Add exception”.',
        movableColumns: false,
        columns: columns,
        columnDefaults: { headerHozAlign: 'center', vertAlign: 'middle' },
    });

    table.on('tableBuilt', function () {
        // Header tint: yellow = typed by you, grey = fetched from the tracking number.
        table.getColumns().forEach(function (col) {
            const f = col.getField();
            if (MANUAL_FIELDS.indexOf(f) !== -1) col.getElement().classList.add('ge-manual');
        });
    });
    table.on('dataLoaded', refreshStats);
    table.on('dataProcessed', refreshStats);
    table.on('renderComplete', refreshStats);
    table.on('dataFiltered', refreshStats);
    table.on('rowDeleted', refreshStats);
    table.on('rowAdded', refreshStats);

    table.on('cellEdited', function (cell) {
        if (suppress) return;
        saveCell(cell);
    });

    async function saveCell(cell) {
        const field = cell.getField();
        const row = cell.getRow();
        const id = row.getData().id;
        const rowEl = row.getElement();
        rowEl.classList.add('ge-busy');
        if (field === 'tracking_number') toast('Looking up the order…');
        try {
            const res = await post(URLS.base + '/' + id + '/update', { field: field, value: cell.getValue() });
            applyRow(row, res.row);
            if (field === 'tracking_number') {
                if (res.lookup && res.lookup.found) {
                    toast('Matched ' + (res.lookup.label || 'order') + ' ' + (res.lookup.order_id || ''), 'ok');
                } else if (res.lookup) {
                    toast('No order found for this tracking number. You can type the Order ID / SKU yourself.', 'warn');
                }
                if (res.duplicate) toast('This tracking number is already on another row.', 'warn');
            }
        } catch (e) {
            suppress = true;
            try { cell.restoreOldValue(); } finally { setTimeout(function () { suppress = false; }, 0); }
            toast(e.message || 'Could not save.', 'err');
        } finally {
            rowEl.classList.remove('ge-busy');
        }
    }

    async function deleteRow(row) {
        const d = row.getData();
        const label = d.tracking_number || ('row #' + d.id);
        if (!window.confirm('Delete ' + label + '? Its images and notes are removed too.')) return;
        try {
            await post(URLS.base + '/' + d.id + '/delete', {});
            row.delete();
            toast('Deleted.', 'ok');
        } catch (e) {
            toast(e.message || 'Could not delete.', 'err');
        }
    }

    document.getElementById('ge-add').addEventListener('click', async function () {
        const btn = this;
        btn.disabled = true;
        try {
            const res = await post(URLS.store, {});
            const row = await table.addRow(res.row, true);
            row.getElement().classList.add('ge-new');
            table.scrollToRow(row, 'top', false).catch(function () {});
            const cell = row.getCell('tracking_number');
            if (cell) setTimeout(function () { cell.edit(); }, 60);
        } catch (e) {
            toast(e.message || 'Could not add a row.', 'err');
        } finally {
            btn.disabled = false;
        }
    });

    document.getElementById('ge-search').addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        if (!q) { table.clearFilter(true); return; }
        const fields = ['tracking_number', 'order_id', 'sku', 'resp_dept', 'mail_response', 'cc_action', 'notes', 'channel_label'];
        table.setFilter(function (data) {
            return fields.some(function (f) { return (data[f] || '').toString().toLowerCase().indexOf(q) !== -1; });
        });
    });

    /* ------------------------------------------------------------ proof & notes modal */
    function proofRow() { return proofRowId ? table.getRow(proofRowId) : null; }

    function renderProof() {
        const row = proofRow();
        if (!row) return;
        const d = row.getData();
        document.getElementById('ge-proof-sub').textContent =
            [d.tracking_number, d.order_id, d.sku].filter(Boolean).join(' · ') || 'New exception';
        const box = document.getElementById('ge-files');
        box.innerHTML = '';
        (d.files || []).forEach(function (f) {
            const wrap = el('div', 'ge-file');
            const img = el('img');
            img.src = f.url; img.alt = f.name || '';
            img.title = f.name || '';
            img.addEventListener('click', function () { openLightbox(f.url); });
            const del = el('button');
            del.type = 'button'; del.title = 'Remove image'; del.innerHTML = '&times;';
            del.addEventListener('click', function () { removeFile(f.path); });
            wrap.appendChild(img); wrap.appendChild(del);
            box.appendChild(wrap);
        });
    }

    function openProof(row) {
        proofRowId = row.getData().id;
        document.getElementById('ge-notes').value = row.getData().notes || '';
        document.getElementById('ge-upload-status').textContent = '';
        renderProof();
        const modalEl = document.getElementById('geProofModal');
        proofModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        proofModal.show();
    }

    document.getElementById('geProofModal').addEventListener('hidden.bs.modal', function () { proofRowId = null; });

    document.getElementById('ge-notes-save').addEventListener('click', async function () {
        const row = proofRow();
        if (!row) return;
        const btn = this;
        btn.disabled = true;
        try {
            const res = await post(URLS.base + '/' + row.getData().id + '/update', {
                field: 'notes', value: document.getElementById('ge-notes').value,
            });
            applyRow(row, res.row);
            toast('Notes saved.', 'ok');
        } catch (e) {
            toast(e.message || 'Could not save notes.', 'err');
        } finally {
            btn.disabled = false;
        }
    });

    async function uploadFiles(fileList) {
        const row = proofRow();
        if (!row) return;
        const images = Array.from(fileList || []).filter(function (f) { return /^image\//.test(f.type); });
        if (!images.length) { toast('Only image files can be uploaded.', 'warn'); return; }
        const status = document.getElementById('ge-upload-status');
        const fd = new FormData();
        images.forEach(function (f) { fd.append('files[]', f, f.name || 'pasted-image.png'); });
        status.textContent = 'Uploading ' + images.length + ' image' + (images.length > 1 ? 's' : '') + '…';
        try {
            const res = await post(URLS.base + '/' + row.getData().id + '/files', fd);
            applyRow(row, res.row);
            renderProof();
            status.textContent = '';
            toast('Image' + (images.length > 1 ? 's' : '') + ' uploaded.', 'ok');
        } catch (e) {
            status.textContent = '';
            toast(e.message || 'Upload failed.', 'err');
        }
    }

    async function removeFile(path) {
        const row = proofRow();
        if (!row) return;
        if (!window.confirm('Remove this image?')) return;
        try {
            const res = await post(URLS.base + '/' + row.getData().id + '/files/delete', { path: path });
            applyRow(row, res.row);
            renderProof();
        } catch (e) {
            toast(e.message || 'Could not remove the image.', 'err');
        }
    }

    const drop = document.getElementById('ge-drop');
    const fileInput = document.getElementById('ge-file-input');
    drop.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () { uploadFiles(fileInput.files); fileInput.value = ''; });
    ['dragenter', 'dragover'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('over'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('over'); });
    });
    drop.addEventListener('drop', function (e) { uploadFiles(e.dataTransfer && e.dataTransfer.files); });
    document.addEventListener('paste', function (e) {
        if (!proofRowId || !e.clipboardData) return;
        const files = Array.from(e.clipboardData.files || []);
        if (files.length) { e.preventDefault(); uploadFiles(files); }
    });
})();
</script>
@endsection
