@extends('layouts.vertical', ['title' => 'Pricing by Channel', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #pbc-table .tabulator-col .tabulator-col-sorter { display: none; }
        #pbc-table.tabulator .tabulator-header .tabulator-col .tabulator-col-title {
            white-space: normal;
            text-align: center;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.2;
        }
        #pbc-table.tabulator .tabulator-cell { font-size: 12px; }
        .pbc-pill { font-weight: 600; white-space: nowrap; }
        .pbc-muted { color: #6c757d; }

        #pbc-column-menu.show {
            min-width: min(92vw, 880px);
            max-width: min(96vw, 940px);
            max-height: 70vh;
            overflow-y: auto;
            padding: 0.4rem 0.5rem 0.55rem;
        }
        #pbc-column-menu > li.col-vis-full { list-style: none; }
        #pbc-column-menu .col-vis-groups {
            display: grid;
            grid-template-columns: repeat(3, minmax(160px, 1fr));
            gap: 8px;
            margin: 0;
            padding: 0;
        }
        #pbc-column-menu .col-vis-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 6px;
            display: flex;
            flex-direction: column;
        }
        #pbc-column-menu .col-vis-group-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            margin: 0 0 6px;
            padding: 2px 4px;
            border-bottom: 1px solid #dee2e6;
            user-select: none;
            cursor: pointer;
        }
        #pbc-column-menu .col-vis-group-title input[type="checkbox"] { margin: 0; cursor: pointer; }
        #pbc-column-menu .col-vis-group-list {
            max-height: 230px;
            overflow-y: auto;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        #pbc-column-menu .col-vis-item { list-style: none; margin: 0; padding: 0; border-radius: 4px; }
        #pbc-column-menu .col-vis-item > label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 3px 5px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
            font-size: 0.8rem;
            user-select: none;
        }
        #pbc-column-menu .col-vis-item > label input[type="checkbox"] { margin: 0; width: 14px; height: 14px; }
        #pbc-column-menu .col-vis-item > label:hover { background: #e9ecef; }

        #pbc-edit-modal .pbc-edit-table th { font-size: 11px; white-space: nowrap; }
        #pbc-edit-modal .pbc-edit-table td { vertical-align: middle; font-size: 12.5px; }
        #pbc-edit-modal .pbc-new-prc { width: 92px; }
        #pbc-edit-modal tr.pbc-ch-off td { color: #6c757d; background: #f8f9fa; }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Pricing by Channel',
        'sub_title' => 'Pricing by Channel',
    ])
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 2000;"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <input type="search" id="pbc-sku-search" class="form-control form-control-sm" style="width: 180px;" placeholder="Search SKU...">
                    <input type="search" id="pbc-parent-search" class="form-control form-control-sm" style="width: 180px;" placeholder="Search Parent...">

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="pbcColumnDropdown"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Show / hide columns">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pbcColumnDropdown" id="pbc-column-menu"></ul>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" id="pbc-refresh-btn" title="Reload live data">
                        <i class="fa fa-sync"></i> Refresh
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="pbc-download-btn" title="Download CSV (every column)">
                        <i class="fa fa-download"></i>
                    </button>
                    <span class="badge bg-dark" id="pbc-count-badge">0 SKUs</span>
                    <span class="text-muted small">Active B2C channels: {{ count($channels) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-2">
        <div class="card-body p-1">
            <div id="pbc-table"></div>
        </div>
    </div>

    {{-- Edit prices of one SKU on every channel --}}
    <div class="modal fade" id="pbc-edit-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Edit price: <span id="pbc-edit-sku" class="text-primary"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2">
                        <strong>Save</strong> stores the New Prc as that channel's suggested price (SPRICE), nothing is sent to the channel.
                        <strong>Push</strong> saves it and then sends it to the channel. NPFT % / NROI % on the right update as you type.
                        Temu, Temu 2, Temu 3, PLS, Instagram and Newegg are view-only here.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered pbc-edit-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Channel</th>
                                    <th class="text-end">Current Prc</th>
                                    <th class="text-end">NPFT %</th>
                                    <th class="text-end">NROI %</th>
                                    <th>New Prc</th>
                                    <th class="text-end">New NPFT %</th>
                                    <th class="text-end">New NROI %</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="pbc-edit-body"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const PBC_CHANNELS = @json($channels);
    const PBC_COLUMN_CHANNEL = 'pricing_by_channel';
    const PBC_COLUMN_URL = '{{ url('/tabulator-column-visibility') }}';
    const PBC_CSRF = '{{ csrf_token() }}';

    // Checkbox groups of the column selector, in this order. The table itself runs
    // Basic, Pricing, NPFT %, NROI %, IDs, Links (+ Edit last).
    const PBC_GROUP_KEYS = ['basics', 'pricing', 'links', 'nroi', 'npft', 'ids'];
    const PBC_GROUP_LABELS = {
        basics: 'Basic', pricing: 'Pricing', links: 'Links', nroi: 'NROI %', npft: 'NPFT %', ids: 'IDs'
    };
    // Shown on first load (nothing saved yet): the six basic columns and the prices.
    const PBC_DEFAULT_VISIBLE_GROUPS = ['basics', 'pricing'];

    let pbcTable = null;

    function pbcGroupOf(field) {
        const f = String(field || '');
        if (f.indexOf('prc_') === 0) return 'pricing';
        if (f.indexOf('link_') === 0) return 'links';
        if (f.indexOf('nroi_') === 0) return 'nroi';
        if (f.indexOf('npft_') === 0) return 'npft';
        if (f.indexOf('id_') === 0) return 'ids';
        return 'basics';
    }

    function pbcEsc(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function pbcToast(msg, type) {
        const wrap = document.querySelector('.toast-container');
        if (!wrap) return;
        const el = document.createElement('div');
        el.className = 'alert alert-' + (type === 'error' ? 'danger' : 'success') + ' py-2 px-3 mb-2 shadow-sm';
        el.textContent = msg;
        wrap.appendChild(el);
        setTimeout(function() { el.remove(); }, type === 'error' ? 7000 : 3500);
    }

    /** NPFT % colour: under 10 red, 10-20 amber, 20+ green. */
    function pbcNpftColor(v) { return v < 10 ? '#dc3545' : (v < 20 ? '#e0a800' : '#28a745'); }
    /** NROI % colour: under 40 red, 40-75 amber, 75+ green. */
    function pbcNroiColor(v) { return v < 40 ? '#dc3545' : (v < 75 ? '#e0a800' : '#28a745'); }

    function pbcPctFormatter(colorFn) {
        return function(cell) {
            const v = parseFloat(cell.getValue());
            if (!isFinite(v) || cell.getValue() === null) return '<span class="pbc-muted">—</span>';
            return '<span class="pbc-pill" style="color:' + colorFn(v) + ';">' + v.toFixed(1) + '%</span>';
        };
    }

    function pbcBuildColumns() {
        const cols = [];

        // ---- Basic: the first six columns of CP Masters (Img, Parent, SKU, Status, UPC, Inv) ----
        cols.push({
            title: 'Img', field: 'image_path', width: 56, hozAlign: 'center', frozen: true, headerSort: false,
            formatter: function(cell) {
                const v = cell.getValue();
                if (!v) return '';
                return '<img src="' + pbcEsc(v) + '" alt="" style="width:40px;height:40px;object-fit:cover;">';
            }
        });
        cols.push({
            title: 'Parent', field: 'parent', width: 110, frozen: true, sorter: 'alphanum',
            headerFilter: 'input', headerFilterPlaceholder: 'Parent...', tooltip: true,
            formatter: function(cell) { return pbcEsc(String(cell.getValue() || '').replace(/^PARENT\s+/i, '')); }
        });
        cols.push({
            title: 'SKU', field: 'sku', width: 170, frozen: true, sorter: 'alphanum',
            headerFilter: 'input', headerFilterPlaceholder: 'SKU...', cssClass: 'text-primary fw-bold', tooltip: true
        });
        cols.push({
            title: 'Status', field: 'status', width: 80, hozAlign: 'center', sorter: 'string',
            headerFilter: 'list',
            headerFilterParams: { values: { '': 'All', active: 'active', inactive: 'inactive', DC: 'DC', upcoming: 'upcoming', '2BDC': '2BDC' }, clearable: true },
            headerFilterFunc: function(hv, rv) { return !hv || String(rv || '').toLowerCase() === String(hv).toLowerCase(); }
        });
        cols.push({
            title: 'UPC', field: 'upc', width: 110, hozAlign: 'center', sorter: 'string',
            headerFilter: 'input', headerFilterPlaceholder: 'UPC...'
        });
        cols.push({ title: 'Inv', field: 'inv', width: 60, hozAlign: 'center', sorter: 'number' });

        // ---- Pricing: Prc <channel> ----
        PBC_CHANNELS.forEach(function(ch) {
            cols.push({
                title: 'Prc ' + ch.label, field: 'prc_' + ch.key, width: 84, hozAlign: 'center',
                sorter: 'number', sorterParams: { alignEmptyValues: 'bottom' },
                visible: PBC_DEFAULT_VISIBLE_GROUPS.indexOf('pricing') !== -1,
                headerTooltip: 'Current ' + ch.label + ' price of this SKU',
                formatter: function(cell) {
                    const v = parseFloat(cell.getValue());
                    if (!(v > 0)) return '<span class="pbc-muted">—</span>';
                    return '<span class="pbc-pill">$' + v.toFixed(2) + '</span>';
                }
            });
        });

        // ---- NPFT % <channel> ----
        PBC_CHANNELS.forEach(function(ch) {
            cols.push({
                title: 'NPFT% ' + ch.label, field: 'npft_' + ch.key, width: 84, hozAlign: 'center', visible: false,
                sorter: 'number', sorterParams: { alignEmptyValues: 'bottom' },
                headerTooltip: ch.label + ' NPFT % (net profit % of price, after ads) — same as Pricing Errors Fix',
                formatter: pbcPctFormatter(pbcNpftColor)
            });
        });

        // ---- NROI % <channel> ----
        PBC_CHANNELS.forEach(function(ch) {
            cols.push({
                title: 'NROI% ' + ch.label, field: 'nroi_' + ch.key, width: 84, hozAlign: 'center', visible: false,
                sorter: 'number', sorterParams: { alignEmptyValues: 'bottom' },
                headerTooltip: ch.label + ' NROI % (net profit ÷ LP, after ads) — same as Pricing Errors Fix',
                formatter: pbcPctFormatter(pbcNroiColor)
            });
        });

        // ---- Unique id of the SKU on each channel ----
        PBC_CHANNELS.forEach(function(ch) {
            cols.push({
                title: 'ID ' + ch.label, field: 'id_' + ch.key, width: 120, hozAlign: 'center', visible: false,
                sorter: 'string', sorterParams: { alignEmptyValues: 'bottom' },
                headerFilter: 'input', headerFilterPlaceholder: 'ID...',
                headerTooltip: 'Listing / product id of this SKU on ' + ch.label,
                formatter: function(cell) {
                    const v = String(cell.getValue() || '').trim();
                    if (!v) return '<span class="pbc-muted">—</span>';
                    return '<span title="' + pbcEsc(v) + '" style="font-size:11.5px;">' + pbcEsc(v) + '</span>';
                }
            });
        });

        // ---- Buyer link on each channel ----
        PBC_CHANNELS.forEach(function(ch) {
            cols.push({
                title: 'Link ' + ch.label, field: 'link_' + ch.key, width: 80, hozAlign: 'center', visible: false,
                sorter: function(a, b) { return (a ? 1 : 0) - (b ? 1 : 0); },
                headerTooltip: 'Buyer link of this SKU on ' + ch.label,
                formatter: function(cell) {
                    const v = String(cell.getValue() || '').trim();
                    if (!/^https?:\/\//i.test(v)) return '<span class="pbc-muted">—</span>';
                    return '<a href="' + pbcEsc(v) + '" target="_blank" rel="noopener noreferrer" class="text-info" title="' + pbcEsc(v)
                        + '" style="text-decoration:none;"><i class="fa fa-link"></i> Open</a>';
                }
            });
        });

        // ---- Edit (always last, stays on screen while scrolling) ----
        cols.push({
            title: 'Edit', field: '_edit', width: 64, hozAlign: 'center', frozen: true, headerSort: false,
            formatter: function() {
                return '<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 pbc-edit-btn" title="Edit price on all channels"><i class="fa fa-pen"></i></button>';
            },
            cellClick: function(e, cell) {
                if (e.target.closest('.pbc-edit-btn')) pbcOpenEdit(cell.getRow());
            }
        });

        return cols;
    }

    // ===================== column selector (checkbox groups) =====================
    function pbcSyncGroupHeader(groupEl) {
        if (!groupEl) return;
        const headerCb = groupEl.querySelector('.col-vis-group-toggle');
        const itemCbs = groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]');
        if (!headerCb || !itemCbs.length) return;
        let checked = 0;
        itemCbs.forEach(function(cb) { if (cb.checked) checked++; });
        headerCb.checked = checked === itemCbs.length;
        headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
    }

    function pbcSaveVisibility() {
        if (!pbcTable) return;
        const visibility = {};
        pbcTable.getColumns().forEach(function(col) {
            const def = col.getDefinition();
            if (def.field && def.field !== '_edit') visibility[def.field] = col.isVisible();
        });
        fetch(PBC_COLUMN_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': PBC_CSRF },
            body: JSON.stringify({ channel: PBC_COLUMN_CHANNEL, visibility: visibility })
        }).catch(function(err) { console.error('Error saving column visibility:', err); });
    }

    function pbcFetchVisibility() {
        return fetch(PBC_COLUMN_URL + '?channel=' + encodeURIComponent(PBC_COLUMN_CHANNEL), {
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': PBC_CSRF }
        }).then(function(r) { return r.json(); }).then(function(j) {
            return (j && typeof j === 'object') ? j : {};
        }).catch(function() { return {}; });
    }

    function pbcApplySavedVisibility() {
        return pbcFetchVisibility().then(function(saved) {
            pbcTable.getColumns().forEach(function(col) {
                const def = col.getDefinition();
                if (def.field && Object.prototype.hasOwnProperty.call(saved, def.field)) {
                    if (saved[def.field]) col.show(); else col.hide();
                }
            });
        });
    }

    function pbcBuildColumnMenu() {
        const menu = document.getElementById('pbc-column-menu');
        if (!menu || !pbcTable) return;
        menu.innerHTML = '';

        const allLi = document.createElement('li');
        allLi.className = 'col-vis-full';
        allLi.innerHTML = '<a class="dropdown-item py-1" href="#" id="pbc-show-all"><i class="fa fa-eye"></i> Show All</a>';
        menu.appendChild(allLi);

        const groupsLi = document.createElement('li');
        groupsLi.className = 'col-vis-full';
        const wrap = document.createElement('div');
        wrap.className = 'col-vis-groups';
        const lists = {};
        const groupEls = {};
        PBC_GROUP_KEYS.forEach(function(cat) {
            const group = document.createElement('div');
            group.className = 'col-vis-group';
            const title = document.createElement('label');
            title.className = 'col-vis-group-title';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.className = 'col-vis-group-toggle';
            cb.title = 'Select / deselect all in ' + PBC_GROUP_LABELS[cat];
            title.appendChild(cb);
            title.appendChild(document.createTextNode(PBC_GROUP_LABELS[cat]));
            group.appendChild(title);
            const list = document.createElement('ul');
            list.className = 'col-vis-group-list';
            group.appendChild(list);
            wrap.appendChild(group);
            lists[cat] = list;
            groupEls[cat] = group;
        });

        pbcTable.getColumns().forEach(function(col) {
            const def = col.getDefinition();
            if (!def.field || def.field === '_edit') return;
            const cat = pbcGroupOf(def.field);
            const li = document.createElement('li');
            li.className = 'col-vis-item';
            const label = document.createElement('label');
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = def.field;
            cb.checked = col.isVisible();
            label.appendChild(cb);
            label.appendChild(document.createTextNode(def.title || def.field));
            label.title = def.title || def.field;
            li.appendChild(label);
            lists[cat].appendChild(li);
        });

        PBC_GROUP_KEYS.forEach(function(cat) { pbcSyncGroupHeader(groupEls[cat]); });
        groupsLi.appendChild(wrap);
        menu.appendChild(groupsLi);
    }

    function pbcBindColumnMenu() {
        const menu = document.getElementById('pbc-column-menu');
        menu.addEventListener('change', function(e) {
            if (e.target.type !== 'checkbox') return;
            if (e.target.classList.contains('col-vis-group-toggle')) {
                const on = e.target.checked;
                const groupEl = e.target.closest('.col-vis-group');
                groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]').forEach(function(cb) {
                    cb.checked = on;
                    const col = pbcTable.getColumn(cb.value);
                    if (col) { if (on) col.show(); else col.hide(); }
                });
                e.target.indeterminate = false;
                pbcSaveVisibility();
                return;
            }
            const col = pbcTable.getColumn(e.target.value);
            if (!col) return;
            if (e.target.checked) col.show(); else col.hide();
            pbcSyncGroupHeader(e.target.closest('.col-vis-group'));
            pbcSaveVisibility();
        });
        menu.addEventListener('click', function(e) {
            if (!e.target.closest('#pbc-show-all')) return;
            e.preventDefault();
            e.stopPropagation();
            pbcTable.getColumns().forEach(function(col) { col.show(); });
            pbcBuildColumnMenu();
            pbcSaveVisibility();
        });
    }

    // ===================== edit modal =====================
    let pbcEditRow = null;
    const PBC_NO_PREVIEW = ['temu', 'temu2']; // their NPFT / NROI use the Temu price chain, not price × margin

    function pbcPreview(price, ch, info) {
        if (PBC_NO_PREVIEW.indexOf(ch.key) !== -1) return null;
        const lp = parseFloat(info && info.lp) || 0;
        const ship = parseFloat(info && info.ship) || 0;
        const margin = parseFloat(info && info.margin) || 0;
        const ads = parseFloat(info && info.ads_pct) || 0;
        if (!(price > 0) || !(lp > 0) || !(margin > 0)) return null;
        const gross = price * margin - lp - ship;
        const gpft = gross / price * 100;
        const groi = gross / lp * 100;
        if (ch.no_ads) return { gpft: gpft, groi: groi, npft: gpft, nroi: groi };
        return { gpft: gpft, groi: groi, npft: gpft - ads, nroi: (gross - price * ads / 100) / lp * 100 };
    }

    function pbcPctText(v, colorFn) {
        if (v == null || !isFinite(v)) return '<span class="pbc-muted">—</span>';
        return '<span class="pbc-pill" style="color:' + colorFn(v) + ';">' + v.toFixed(1) + '%</span>';
    }

    function pbcOpenEdit(row) {
        pbcEditRow = row;
        const d = row.getData();
        document.getElementById('pbc-edit-sku').textContent = d.sku;
        const body = document.getElementById('pbc-edit-body');
        body.innerHTML = '';
        PBC_CHANNELS.forEach(function(ch) {
            const k = ch.key;
            const cur = parseFloat(d['prc_' + k]);
            const info = (d.channels || {})[k] || null;
            const listed = cur > 0 || !!d['id_' + k] || !!d['link_' + k];
            if (!listed && !info) return;
            const tr = document.createElement('tr');
            tr.dataset.channel = k;
            if (!ch.can_save) tr.className = 'pbc-ch-off';
            const sprice = info && parseFloat(info.sprice) > 0 ? parseFloat(info.sprice) : '';
            tr.innerHTML =
                '<td class="fw-semibold">' + pbcEsc(ch.label) + '</td>'
                + '<td class="text-end">' + (cur > 0 ? '<span class="pbc-pill">$' + cur.toFixed(2) + '</span>' : '<span class="pbc-muted">—</span>') + '</td>'
                + '<td class="text-end">' + pbcPctText(parseFloat(d['npft_' + k]), pbcNpftColor) + '</td>'
                + '<td class="text-end">' + pbcPctText(parseFloat(d['nroi_' + k]), pbcNroiColor) + '</td>'
                + '<td>' + (ch.can_save
                    ? '<input type="number" step="0.01" min="0.01" class="form-control form-control-sm pbc-new-prc" value="' + (sprice !== '' ? sprice.toFixed(2) : '') + '" placeholder="' + (cur > 0 ? cur.toFixed(2) : '') + '">'
                    : '<span class="pbc-muted small">view only</span>') + '</td>'
                + '<td class="text-end pbc-new-npft"></td>'
                + '<td class="text-end pbc-new-nroi"></td>'
                + '<td class="text-center text-nowrap">'
                + (ch.can_save ? '<button type="button" class="btn btn-sm btn-outline-success py-0 px-2 pbc-save-one" title="Save as suggested price (SPRICE)">Save</button> ' : '')
                + (ch.can_push ? '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 pbc-push-one" title="Save, then push this price to ' + pbcEsc(ch.label) + '">Push</button>' : '')
                + '</td>';
            body.appendChild(tr);
            pbcRefreshPreview(tr);
        });
        if (!body.children.length) {
            body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">This SKU is not on any active B2C channel.</td></tr>';
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('pbc-edit-modal')).show();
    }

    function pbcChannelOf(tr) {
        return PBC_CHANNELS.filter(function(c) { return c.key === tr.dataset.channel; })[0];
    }

    function pbcRefreshPreview(tr) {
        const ch = pbcChannelOf(tr);
        const input = tr.querySelector('.pbc-new-prc');
        const price = input ? parseFloat(input.value) : NaN;
        const info = ((pbcEditRow.getData().channels) || {})[ch.key] || null;
        const p = pbcPreview(price, ch, info);
        tr.querySelector('.pbc-new-npft').innerHTML = p ? pbcPctText(p.npft, pbcNpftColor) : '<span class="pbc-muted">—</span>';
        tr.querySelector('.pbc-new-nroi').innerHTML = p ? pbcPctText(p.nroi, pbcNroiColor) : '<span class="pbc-muted">—</span>';
    }

    function pbcPostJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': PBC_CSRF },
            body: JSON.stringify(payload)
        }).then(function(r) {
            return r.json().catch(function() { return {}; }).then(function(j) {
                if (!r.ok || j.success === false || j.error) {
                    throw new Error(j.message || j.error || ('HTTP ' + r.status));
                }
                return j;
            });
        });
    }

    function pbcSaveSprice(tr) {
        const ch = pbcChannelOf(tr);
        const d = pbcEditRow.getData();
        const price = parseFloat(tr.querySelector('.pbc-new-prc').value);
        if (!(price > 0)) return Promise.reject(new Error('Enter a New Prc above 0'));
        const info = (d.channels || {})[ch.key] || null;
        const p = pbcPreview(price, ch, info) || { gpft: 0, groi: 0, npft: 0, nroi: 0 };
        // Same payload Pricing Errors Fix sends: SGPFT gross %, SROI net ROI, SGROI gross ROI, SPFT net %.
        return pbcPostJson('/cvr-master-save-suggested-data', {
            sku: d.sku,
            marketplace: ch.key,
            sprice: price,
            sgpft: +p.gpft.toFixed(2),
            sroi: +p.nroi.toFixed(2),
            sgroi: +p.groi.toFixed(2),
            spft: +p.npft.toFixed(2),
            skip_pair_sync: 1
        }).then(function() {
            const channels = Object.assign({}, d.channels || {});
            channels[ch.key] = Object.assign({}, channels[ch.key] || {}, { sprice: price });
            pbcEditRow.update({ channels: channels });
            return price;
        });
    }

    function pbcAfterChange() {
        // The cached page data is stale once a price changed.
        fetch('{{ route('pricing.by.channel.forget') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': PBC_CSRF, 'Accept': 'application/json' } }).catch(function() {});
    }

    document.addEventListener('input', function(e) {
        if (!e.target.classList.contains('pbc-new-prc')) return;
        pbcRefreshPreview(e.target.closest('tr'));
    });

    document.addEventListener('click', function(e) {
        const saveBtn = e.target.closest('.pbc-save-one');
        const pushBtn = e.target.closest('.pbc-push-one');
        if (!saveBtn && !pushBtn) return;
        const btn = saveBtn || pushBtn;
        const tr = btn.closest('tr');
        const ch = pbcChannelOf(tr);
        const d = pbcEditRow.getData();
        const price = parseFloat(tr.querySelector('.pbc-new-prc').value);
        if (!(price > 0)) { pbcToast('Enter a New Prc above 0 first', 'error'); return; }
        if (pushBtn && !confirm('Push $' + price.toFixed(2) + ' to ' + ch.label + ' for ' + d.sku + '?\n\nThis changes the live price on the channel.')) return;

        const label = btn.textContent;
        btn.disabled = true;
        btn.textContent = '…';
        pbcSaveSprice(tr).then(function() {
            if (!pushBtn) { pbcToast(ch.label + ' suggested price saved: $' + price.toFixed(2)); return null; }
            return pbcPostJson('/cvr-master-push-price', { sku: d.sku, price: price, marketplace: ch.key }).then(function(j) {
                pbcToast(ch.label + ' price pushed: $' + price.toFixed(2) + (j && j.message ? ' — ' + j.message : ''));
            });
        }).then(function() {
            pbcAfterChange();
        }).catch(function(err) {
            pbcToast((pushBtn ? 'Push' : 'Save') + ' failed for ' + ch.label + ': ' + err.message, 'error');
        }).finally(function() {
            btn.disabled = false;
            btn.textContent = label;
        });
    });

    // ===================== table =====================
    function pbcApplyFilters() {
        if (!pbcTable) return;
        const sku = document.getElementById('pbc-sku-search').value.trim().toLowerCase();
        const parent = document.getElementById('pbc-parent-search').value.trim().toLowerCase();
        const filters = [];
        if (sku) filters.push({ field: 'sku', type: 'like', value: sku });
        if (parent) filters.push({ field: 'parent', type: 'like', value: parent });
        pbcTable.setFilter(filters);
    }

    function pbcCsvEscape(v) {
        if (v == null) return '';
        const s = String(v);
        return /[",\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
    }

    function pbcDownload() {
        if (!pbcTable) return;
        const cols = pbcTable.getColumns().filter(function(c) { const f = c.getDefinition().field; return f && f !== '_edit'; });
        const lines = [cols.map(function(c) { return pbcCsvEscape(c.getDefinition().title || c.getDefinition().field); }).join(',')];
        (pbcTable.getData('active') || []).forEach(function(r) {
            lines.push(cols.map(function(c) { return pbcCsvEscape(r[c.getDefinition().field]); }).join(','));
        });
        const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'pricing_by_channel.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function() { URL.revokeObjectURL(a.href); }, 1000);
    }

    function pbcUpdateCount() {
        const n = pbcTable ? (pbcTable.getDataCount('active') || 0) : 0;
        document.getElementById('pbc-count-badge').textContent = n.toLocaleString() + ' SKUs';
    }

    $(document).ready(function() {
        pbcTable = new Tabulator('#pbc-table', {
            ajaxURL: '{{ route('pricing.by.channel.data') }}',
            ajaxResponse: function(url, params, response) {
                if (response && response.error) {
                    pbcToast(response.error, 'error');
                    return [];
                }
                return (response && Array.isArray(response.rows)) ? response.rows : [];
            },
            ajaxError: function(error) { pbcToast('Could not load pricing data', 'error'); },
            layout: 'fitData',
            layoutColumnsOnNewData: true,
            renderHorizontal: 'virtual',
            height: '75vh',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200],
            paginationCounter: 'rows',
            columns: pbcBuildColumns()
        });

        pbcTable.on('tableBuilt', function() {
            pbcApplySavedVisibility().then(pbcBuildColumnMenu);
        });
        pbcTable.on('dataLoaded', pbcUpdateCount);
        pbcTable.on('dataFiltered', pbcUpdateCount);

        pbcBindColumnMenu();
        document.getElementById('pbc-sku-search').addEventListener('input', pbcApplyFilters);
        document.getElementById('pbc-parent-search').addEventListener('input', pbcApplyFilters);
        document.getElementById('pbc-download-btn').addEventListener('click', pbcDownload);
        document.getElementById('pbc-refresh-btn').addEventListener('click', function() {
            pbcTable.setData('{{ route('pricing.by.channel.data') }}?refresh=1');
        });
    });
</script>
@endsection
