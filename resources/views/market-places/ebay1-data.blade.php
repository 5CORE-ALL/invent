@extends('layouts.vertical', ['title' => 'Ebay1 Data', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .e1d-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .e1d-card .card-body { padding: 1rem 1.25rem; }
        .e1d-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-bottom: .75rem; }
        .e1d-toolbar input, .e1d-toolbar select {
            height: 32px; border-radius: 8px; border: 1px solid #cbd5e1; padding: 0 .6rem; font-size: .84rem;
        }
        .e1d-toolbar input { min-width: 240px; }
        .e1d-toolbar .btn { padding: .3rem .65rem; font-size: .8rem; }
        .e1d-badges { display: flex; flex-wrap: wrap; gap: .4rem; margin-left: auto; }
        .e1d-badge { padding: .25rem .6rem; border-radius: 6px; font-size: .78rem; font-weight: 700; color: #fff; }
        .e1d-badge.req { background: #16a34a; }
        .e1d-badge.nrl { background: #dc2626; }
        .e1d-badge.listed { background: #0284c7; }
        .e1d-badge.missing { background: #be123c; }
        .e1d-badge.rows { background: #334155; }
        .e1d-tabs {
            display: flex; flex-wrap: nowrap; gap: 4px; overflow-x: auto; overflow-y: hidden;
            border-bottom: 2px solid #e2e8f0; margin-bottom: .75rem; padding-bottom: 0;
            scrollbar-width: thin;
        }
        .e1d-tab {
            flex: 0 0 auto; max-width: 280px; display: inline-flex; align-items: center; gap: 6px;
            border: 1px solid #e2e8f0; border-bottom: none; border-radius: 8px 8px 0 0;
            background: #f8fafc; color: #475569; padding: .4rem .75rem; font-size: .8rem; font-weight: 600;
            cursor: pointer; white-space: nowrap;
        }
        .e1d-tab .e1d-tab-name { overflow: hidden; text-overflow: ellipsis; }
        .e1d-tab .e1d-tab-count {
            background: #e2e8f0; color: #334155; border-radius: 10px; padding: 0 .45rem; font-size: .72rem;
        }
        .e1d-tab:hover { background: #eff6ff; color: #1d4ed8; }
        .e1d-tab.active { background: #2c6ed5; border-color: #2c6ed5; color: #fff; }
        .e1d-tab.active .e1d-tab-count { background: rgba(255,255,255,.25); color: #fff; }
        #ebay1-data-table { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; background: #fff; }
        #ebay1-data-table .tabulator-header { background: linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color: #fff; }
        #ebay1-data-table .tabulator-col { background: transparent !important; border-right: 1px solid rgba(255,255,255,.15); }
        #ebay1-data-table .tabulator-col .tabulator-col-title { color: #fff; font-size: 12px; font-weight: 700; }
        #ebay1-data-table .tabulator-header .tabulator-col.tabulator-frozen { background: #2c6ed5 !important; }
        #ebay1-data-table .tabulator-row:nth-child(even) { background: #f8fafc; }
        #ebay1-data-table .tabulator-row:hover { background: #e8f0fe !important; }
        #ebay1-data-table .tabulator-cell { font-size: 13px; color: #475569; }
        .e1d-pill { display: inline-block; padding: .1rem .5rem; border-radius: 6px; font-size: .75rem; font-weight: 700; color: #fff; }
        .e1d-pill.green { background: #16a34a; }
        .e1d-pill.red { background: #dc2626; }
        .e1d-pill.amber { background: #d97706; }
        .e1d-pill.blue { background: #0284c7; }
        .e1d-pill.grey { background: #64748b; }
        .e1d-muted { color: #94a3b8; }
        .e1d-modal { position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 2000; display: flex; align-items: center; justify-content: center; }
        .e1d-modal[hidden] { display: none; }
        .e1d-addlist { max-height: 220px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
        .e1d-addlist:empty { display: none; }
        .e1d-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 6px; }
        .e1d-chips:empty { display: none; }
        .e1d-chip { background: #dcfce7; color: #166534; border-radius: 12px; padding: 1px 4px 1px 10px; font-size: .78rem; font-weight: 600; }
        .e1d-chip button { border: 0; background: transparent; color: #166534; font-weight: 700; padding: 0 4px; }
        .e1d-addopt { display: flex; align-items: center; gap: 6px; padding: .3rem .6rem; font-size: .84rem; color: #334155; cursor: pointer; margin: 0; }
        .e1d-addopt:hover { background: #eff6ff; }
        .e1d-addlist button { display: block; width: 100%; text-align: left; border: 0; background: #fff; padding: .35rem .6rem; font-size: .84rem; color: #334155; }
        .e1d-addlist button:hover, .e1d-addlist button.active { background: #eff6ff; color: #1d4ed8; }
        .e1d-addlist button.new { color: #15803d; font-weight: 700; border-top: 1px solid #e2e8f0; }
        .e1d-modal-box { background: #fff; border-radius: 12px; padding: 1.1rem 1.25rem; width: min(480px, 92vw); box-shadow: 0 10px 40px rgba(0,0,0,.25); }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', [
        'page_title' => 'Ebay1 Data',
        'sub_title' => 'Every eBay 1 listing row, category wise',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card e1d-card">
                <div class="card-body">
                    <div class="e1d-toolbar">
                        <input type="search" id="e1dSearch" placeholder="Search SKU, parent, title or item ID…" autocomplete="off">
                        <select id="e1dListed" title="Listing state">
                            <option value="all">All listing states</option>
                            <option value="Listed">Listed</option>
                            <option value="Missing">Missing L</option>
                            <option value="Created">Created (not live yet)</option>
                        </select>
                        <select id="e1dNr" title="Required or not">
                            <option value="all">REQ + NRL</option>
                            <option value="REQ">REQ only</option>
                            <option value="NR">NRL only</option>
                        </select>
                        <select id="e1dStock" title="Stock">
                            <option value="all">Any stock</option>
                            <option value="in">In stock (INV &gt; 0)</option>
                            <option value="out">Out of stock</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="e1dRefresh" title="Reload the data">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" id="e1dExport" title="Download the rows of this tab as CSV">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                        <button type="button" class="btn btn-sm btn-warning" id="e1dPull" title="Reads the live eBay 1 listing of every SKU once and stores its category and item specifics">
                            <i class="fas fa-cloud-download-alt"></i> <span id="e1dPullLabel">Pull item specifics (one time)</span>
                        </button>
                        <span class="small text-muted" id="e1dPullStatus"></span>
                        <button type="button" class="btn btn-sm btn-primary" id="e1dPush" title="Push the item specifics of the ticked rows, or of the whole open tab, to the live eBay 1 listings" disabled>
                            <i class="fas fa-cloud-upload-alt"></i> <span id="e1dPushLabel">Push to eBay 1</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-success" id="e1dAddCol" title="Add a column to the open category tab" disabled>
                            <i class="fas fa-plus"></i> Add Column
                        </button>
                        <button type="button" class="btn btn-sm btn-info text-white" id="e1dSrcBtn" title="Choose which Technical Specifications column a column of this tab always reads from" disabled>
                            <i class="fas fa-link"></i> Source Data
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" id="e1dDelCol" title="Delete one item-specific column of the open category tab, here and on the live eBay 1 listings" disabled>
                            <i class="fas fa-trash-alt"></i> Delete Column
                        </button>
                        <div class="e1d-badges" id="e1dBadges"></div>
                    </div>

                    <div class="e1d-tabs" id="e1dTabs" role="tablist" aria-label="eBay categories"></div>

                    <div id="ebay1-data-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="e1d-modal" id="e1dAddModal" hidden>
        <div class="e1d-modal-box" role="dialog" aria-modal="true" aria-labelledby="e1dAddTitle">
            <h5 id="e1dAddTitle" class="mb-2"><i class="fas fa-plus-circle text-success"></i> Add Column</h5>
            <p class="small text-muted mb-2" id="e1dAddIntro"></p>
            <label class="form-label small fw-bold" for="e1dAddInput">Search a column, or type a new name</label>
            <div class="e1d-chips" id="e1dAddChips"></div>
            <input type="text" id="e1dAddInput" class="form-control form-control-sm mb-1" placeholder="e.g. Impedance" autocomplete="off">
            <div class="e1d-addlist" id="e1dAddList" role="listbox"></div>
            <div class="small my-2" id="e1dAddHint"></div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-light" id="e1dAddCancel">Cancel</button>
                <button type="button" class="btn btn-sm btn-success" id="e1dAddGo" disabled>Add column</button>
            </div>
        </div>
    </div>

    <div class="e1d-modal" id="e1dSrcModal" hidden>
        <div class="e1d-modal-box" role="dialog" aria-modal="true" aria-labelledby="e1dSrcTitle">
            <h5 id="e1dSrcTitle" class="mb-2"><i class="fas fa-link text-info"></i> Source Data</h5>
            <p class="small text-muted mb-2" id="e1dSrcIntro"></p>
            <label class="form-label small fw-bold" for="e1dSrcCol">Column of this tab</label>
            <select id="e1dSrcCol" class="form-select form-select-sm mb-2"></select>
            <label class="form-label small fw-bold" for="e1dSrcInput">Technical Specifications column to read from</label>
            <input type="text" id="e1dSrcInput" class="form-control form-control-sm mb-1" placeholder="Search a column…" autocomplete="off">
            <div class="e1d-addlist" id="e1dSrcList" role="listbox"></div>
            <div class="small my-2" id="e1dSrcHint"></div>
            <div class="d-flex justify-content-between gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="e1dSrcClear" title="Stop syncing this column">Remove source</button>
                <span class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-light" id="e1dSrcCancel">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="e1dSrcSave" disabled>Save</button>
                </span>
            </div>
        </div>
    </div>

    <div class="e1d-modal" id="e1dDelModal" hidden>
        <div class="e1d-modal-box" role="dialog" aria-modal="true" aria-labelledby="e1dDelTitle">
            <h5 id="e1dDelTitle" class="mb-2"><i class="fas fa-trash-alt text-danger"></i> Delete Column</h5>
            <p class="small text-muted mb-2" id="e1dDelIntro"></p>
            <label class="e1d-addopt fw-bold border-bottom mb-1"><input type="checkbox" id="e1dDelAll"> Columns of this tab (tick one or more)</label>
            <div class="e1d-addlist mb-2" id="e1dDelList"></div>
            <div class="alert alert-danger small py-2 mb-2" id="e1dDelWarn"></div>
            <div class="small mb-2" id="e1dDelProgress"></div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-light" id="e1dDelCancel">Cancel</button>
                <button type="button" class="btn btn-sm btn-danger" id="e1dDelGo">Delete column</button>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            'use strict';

            const ALL = '__all__';
            const NO_CATEGORY = 'No category';
            let table = null;
            let tableReady = false;
            let rows = [];
            let activeTab = ALL;
            let searchTimer = null;

            function esc(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            }

            function catKey(row) {
                return String(row.category || '').trim() || NO_CATEGORY;
            }

            // Tab label: the last part of the category path; the whole path is the tooltip.
            function catLabel(key) {
                if (key === NO_CATEGORY) return key;
                const parts = key.split(/\s*(?:>|:|\/)\s*/).filter(Boolean);
                return parts.length ? parts[parts.length - 1] : key;
            }

            function isParentRow(row) {
                const sku = String(row.sku || '').trim();
                return /parent/i.test(sku) || (String(row.parent || '').trim() !== '' && sku.toLowerCase() === String(row.parent).trim().toLowerCase());
            }

            function inStock(row) {
                return Number(row.inv) > 0;
            }

            function isMissing(row) {
                return inStock(row) && row.nr_req !== 'NR' && row.listed !== 'Listed' && row.listed !== 'Created';
            }

            function passesControls(row) {
                const q = String(document.getElementById('e1dSearch').value || '').trim().toLowerCase();
                if (q) {
                    const hay = (row.sku + ' ' + row.parent + ' ' + row.ebay_title + ' ' + row.item_id).toLowerCase();
                    if (hay.indexOf(q) === -1) return false;
                }
                const listed = document.getElementById('e1dListed').value;
                if (listed === 'Listed' && row.listed !== 'Listed') return false;
                if (listed === 'Created' && row.listed !== 'Created') return false;
                if (listed === 'Missing' && !isMissing(row)) return false;
                const nr = document.getElementById('e1dNr').value;
                if (nr === 'REQ' && row.nr_req === 'NR') return false;
                if (nr === 'NR' && row.nr_req !== 'NR') return false;
                const stock = document.getElementById('e1dStock').value;
                if (stock === 'in' && !inStock(row)) return false;
                if (stock === 'out' && inStock(row)) return false;
                return true;
            }

            function passesTab(row) {
                return activeTab === ALL || catKey(row) === activeTab;
            }

            // Tabs are counted with the search and filters applied, so the numbers match what the tab then shows.
            function renderTabs() {
                const counts = new Map();
                let total = 0;
                rows.forEach(function (row) {
                    if (!passesControls(row)) return;
                    total++;
                    const key = catKey(row);
                    counts.set(key, (counts.get(key) || 0) + 1);
                });
                const keys = Array.from(counts.keys()).sort(function (a, b) {
                    if (a === NO_CATEGORY) return 1;
                    if (b === NO_CATEGORY) return -1;
                    return catLabel(a).localeCompare(catLabel(b));
                });
                if (activeTab !== ALL && !counts.has(activeTab)) activeTab = ALL;

                const holder = document.getElementById('e1dTabs');
                holder.innerHTML = '';
                function addTab(key, label, title, count) {
                    const tab = document.createElement('button');
                    tab.type = 'button';
                    tab.className = 'e1d-tab' + (key === activeTab ? ' active' : '');
                    tab.setAttribute('role', 'tab');
                    tab.title = title;
                    tab.innerHTML = '<span class="e1d-tab-name">' + esc(label) + '</span><span class="e1d-tab-count">' + count + '</span>';
                    tab.addEventListener('click', function () {
                        activeTab = key;
                        renderTabs();
                        syncSpecColumns();
                        applyFilters();
                        updateDelButton();
                        updatePushButton();
                    });
                    holder.appendChild(tab);
                }
                addTab(ALL, 'All', 'All categories', total);
                keys.forEach(function (key) { addTab(key, catLabel(key), key, counts.get(key)); });
            }

            function renderBadges() {
                let req = 0, nrl = 0, listed = 0, missing = 0, shown = 0;
                const selected = table && tableReady ? table.getSelectedRows().length : 0;
                rows.forEach(function (row) {
                    if (!passesTab(row) || !passesControls(row)) return;
                    shown++;
                    if (!inStock(row)) return;
                    if (row.nr_req === 'NR') { nrl++; return; }
                    req++;
                    if (row.listed === 'Created') return;
                    if (row.listed === 'Listed') listed++; else missing++;
                });
                document.getElementById('e1dBadges').innerHTML =
                    '<span class="e1d-badge req" title="In stock, required">REQ ' + req + '</span>' +
                    '<span class="e1d-badge nrl" title="In stock, not required">NRL ' + nrl + '</span>' +
                    '<span class="e1d-badge listed">Listed ' + listed + '</span>' +
                    '<span class="e1d-badge missing">Missing L ' + missing + '</span>' +
                    '<span class="e1d-badge rows">Rows ' + shown + '</span>' +
                    (selected ? '<span class="e1d-badge listed" title="Rows ticked in the first column">Selected ' + selected + '</span>' : '');
            }

            function applyFilters() {
                if (table && tableReady) {
                    table.setFilter(function (row) { return passesTab(row) && passesControls(row); });
                }
                renderBadges();
            }

            function refreshAll() {
                renderTabs();
                syncSpecColumns();
                applyFilters();
                updatePullButton();
                updateDelButton();
                updatePushButton();
            }

            // ---- Delete Column: removes one item specific from every SKU of the open tab, here and on eBay 1. ----
            // ---- Add Column: pick a Technical Specifications column, or type a new name, for the open tab. ----
            let columnOptions = null;

            function normName(value) {
                return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
            }

            async function loadColumnOptions() {
                if (columnOptions) return;
                try {
                    const res = await fetch('/ebay1-data/column-options', { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    columnOptions = res.ok && Array.isArray(json.columns) ? json.columns : [];
                } catch (e) {
                    columnOptions = [];
                }
                renderAddList();
                renderSrcList();
            }

            // Columns picked so far, in the order they were picked: normalised key => name.
            let addPicked = new Map();

            function cleanName(value) {
                return String(value || '').replace(/\s+/g, ' ').trim();
            }

            function addState() {
                const typed = cleanName(document.getElementById('e1dAddInput').value);
                const key = normName(typed);
                const inTab = new Set(specNamesForTab().map(normName));
                const known = (columnOptions || []).find(function (name) { return normName(name) === key; }) || '';
                return { typed: typed, key: key, inTab: key !== '' && inTab.has(key), known: known, tabKeys: inTab };
            }

            function togglePicked(name) {
                const key = normName(name);
                if (key === '') return;
                if (addPicked.has(key)) addPicked.delete(key); else addPicked.set(key, name);
                renderAddList();
            }

            function renderAddList() {
                const list = document.getElementById('e1dAddList');
                const chips = document.getElementById('e1dAddChips');
                const hint = document.getElementById('e1dAddHint');
                const go = document.getElementById('e1dAddGo');
                const s = addState();
                list.innerHTML = '';
                chips.innerHTML = '';
                addPicked.forEach(function (name, key) {
                    const chip = document.createElement('span');
                    chip.className = 'e1d-chip';
                    chip.appendChild(document.createTextNode(name + ' '));
                    const x = document.createElement('button');
                    x.type = 'button';
                    x.title = 'Remove';
                    x.innerHTML = '&times;';
                    x.addEventListener('click', function () { addPicked.delete(key); renderAddList(); });
                    chip.appendChild(x);
                    chips.appendChild(chip);
                });
                go.textContent = addPicked.size > 1 ? 'Add ' + addPicked.size + ' columns' : 'Add column';
                go.disabled = addPicked.size === 0 || columnOptions === null;
                if (columnOptions === null) {
                    hint.textContent = 'Loading columns…';
                    return;
                }
                columnOptions.filter(function (name) {
                    const k = normName(name);
                    return !s.tabKeys.has(k) && (s.key === '' || k.indexOf(s.key) !== -1);
                }).slice(0, 100).forEach(function (name) {
                    const row = document.createElement('label');
                    row.className = 'e1d-addopt';
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.checked = addPicked.has(normName(name));
                    cb.addEventListener('change', function () { togglePicked(name); });
                    row.appendChild(cb);
                    row.appendChild(document.createTextNode(' ' + name));
                    list.appendChild(row);
                });
                if (s.key !== '' && !s.known && !s.inTab) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'new';
                    b.textContent = '+ Create new column “' + s.typed + '”';
                    b.addEventListener('click', function () {
                        addPicked.set(s.key, s.typed);
                        document.getElementById('e1dAddInput').value = '';
                        renderAddList();
                        document.getElementById('e1dAddInput').focus();
                    });
                    list.appendChild(b);
                }
                if (s.inTab) hint.textContent = 'This column is already in this tab.';
                else if (addPicked.size) hint.textContent = addPicked.size + ' selected. Tick more, or press Add.';
                else hint.textContent = 'Tick one or more columns, or type a name that is not there to create it.';
            }

            function openAddModal() {
                if (activeTab === ALL) return;
                document.getElementById('e1dAddIntro').textContent = 'Category: ' + catLabel(activeTab);
                const input = document.getElementById('e1dAddInput');
                input.value = '';
                addPicked = new Map();
                document.getElementById('e1dAddModal').hidden = false;
                renderAddList();
                input.focus();
                loadColumnOptions();
            }

            function closeAddModal() {
                document.getElementById('e1dAddModal').hidden = true;
            }

            // Enter: put the typed name on the list of picked columns; Enter on an empty box adds them all.
            function addTypedOrSubmit() {
                const s = addState();
                if (s.key === '' || s.inTab) {
                    if (s.key === '' && addPicked.size) runAddColumn();
                    return;
                }
                addPicked.set(s.key, s.known || s.typed);
                document.getElementById('e1dAddInput').value = '';
                renderAddList();
            }

            async function runAddColumn() {
                if (!addPicked.size || activeTab === ALL) return;
                const names = Array.from(addPicked.values());
                const go = document.getElementById('e1dAddGo');
                go.disabled = true;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const skus = rows.filter(passesTab).map(function (row) { return row.sku; });
                document.getElementById('e1dAddHint').textContent = 'Adding…';
                try {
                    const res = await fetch('/ebay1-data/add-column', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ names: names, category: activeTab === NO_CATEGORY ? '' : activeTab, skus: skus }),
                    });
                    const json = await res.json();
                    if (!res.ok || !json.success) throw new Error(json.message || 'Request failed');
                    columnOptions = null;
                    closeAddModal();
                    const added = json.names || names;
                    document.getElementById('e1dPullStatus').textContent = added.length === 1
                        ? 'Column "' + added[0] + '" added.'
                        : added.length + ' columns added.';
                    await loadData();
                } catch (e) {
                    document.getElementById('e1dAddHint').innerHTML = '<span class="text-danger">' + esc(e.message || 'Failed') + '</span>';
                    go.disabled = false;
                }
            }

            // ---- REQ / NRL: saved to the same data as the Listing eBay page, then the counts are refreshed. ----
            async function saveNrReq(cell) {
                const row = cell.getRow().getData();
                const value = cell.getValue() === 'NR' ? 'NR' : 'REQ';
                const status = document.getElementById('e1dPullStatus');
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                try {
                    const res = await fetch('/ebay1-data/nr-req', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ sku: row.sku, nr_req: value }),
                    });
                    const json = await res.json();
                    if (!res.ok || !json.success) throw new Error(json.message || 'Request failed');
                    row.nr_req = json.nr_req;
                    status.textContent = row.sku + ' set to ' + (json.nr_req === 'NR' ? 'NRL' : 'REQ') + '.';
                    refreshAll();
                } catch (e) {
                    cell.restoreOldValue();
                    status.textContent = 'Could not save ' + row.sku + ': ' + (e.message || 'failed');
                }
            }

            // ---- Push: ticked rows if any, otherwise every SKU of the open tab, in one go. ----
            function pushScope() {
                const selected = table && tableReady ? table.getSelectedData().filter(passesTab) : [];
                const base = selected.length ? selected : rows.filter(passesTab);
                const targets = base.filter(function (row) {
                    if (!/^\d{9,15}$/.test(String(row.item_id || '').trim())) return false;
                    const spec = row.specifics && !Array.isArray(row.specifics) ? row.specifics : {};
                    return Object.keys(spec).some(function (name) { return String(spec[name] || '').trim() !== ''; });
                });
                return { selected: selected.length > 0, targets: targets };
            }

            function updatePushButton() {
                const btn = document.getElementById('e1dPush');
                if (!btn || btn.dataset.busy === '1') return;
                const scope = pushScope();
                btn.disabled = scope.targets.length === 0;
                document.getElementById('e1dPushLabel').textContent = 'Push to eBay 1 · ' + scope.targets.length + (scope.selected ? ' selected' : '');
                btn.title = scope.selected
                    ? 'Push the ticked rows to the live eBay 1 listings'
                    : (activeTab === ALL ? 'Push every listed SKU of all categories' : 'Push the whole open category to the live eBay 1 listings');
            }

            async function pushToEbay() {
                const btn = document.getElementById('e1dPush');
                const status = document.getElementById('e1dPullStatus');
                const scope = pushScope();
                const todo = scope.targets;
                if (!todo.length) return;
                const where = scope.selected
                    ? todo.length + ' selected SKU' + (todo.length === 1 ? '' : 's')
                    : 'all ' + todo.length + ' SKUs of ' + (activeTab === ALL ? 'every category' : '"' + catLabel(activeTab) + '"');
                if (!window.confirm('Push the item specifics of ' + where + ' to the live eBay 1 listings?\nBlank values are not sent and values already on eBay are left alone.')) return;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                btn.dataset.busy = '1';
                btn.disabled = true;
                let pushed = 0, unchanged = 0, skipped = 0, failed = 0;
                const problems = [];
                const size = 5;
                for (let i = 0; i < todo.length; i += size) {
                    const chunk = todo.slice(i, i + size).map(function (row) {
                        const specifics = {};
                        Object.keys(row.specifics).forEach(function (name) {
                            const v = String(row.specifics[name] || '').trim();
                            if (v !== '') specifics[name] = v;
                        });
                        return { sku: row.sku, item_id: String(row.item_id).trim(), specifics: specifics };
                    });
                    status.textContent = 'Pushing ' + Math.min(i + size, todo.length) + ' / ' + todo.length + '…';
                    try {
                        const res = await fetch('/ebay1-data/push', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ items: chunk }),
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error(json.message || 'Request failed');
                        (json.results || []).forEach(function (r) {
                            if (r.status === 'failed') { failed++; problems.push(r.sku + ': ' + r.message); }
                            else if (r.status === 'pushed') pushed++;
                            else if (r.status === 'unchanged') unchanged++;
                            else skipped++;
                        });
                    } catch (e) {
                        failed += chunk.length;
                        problems.push(e.message || 'Request failed');
                    }
                }
                btn.dataset.busy = '0';
                status.textContent = 'Pushed ' + pushed + ', unchanged ' + unchanged + (skipped ? ', skipped ' + skipped : '') + (failed ? ', failed ' + failed : '') + '.';
                if (problems.length) window.alert(problems.slice(0, 15).join('\n') + (problems.length > 15 ? '\n…' : ''));
                await loadData();
            }

            // ---- Source Data: a column of this tab always reads from a Technical Specifications column. ----
            function tabCategory() {
                return activeTab === NO_CATEGORY ? '' : activeTab;
            }

            function sourceFor(columnName, category) {
                const cat = String(category == null ? tabCategory() : category).trim().toLowerCase();
                const key = normName(columnName);
                const hit = columnSources.find(function (s) {
                    return String(s.category || '').trim().toLowerCase() === cat && normName(s.column) === key;
                });
                return hit ? hit.source : '';
            }

            function srcState() {
                const typed = String(document.getElementById('e1dSrcInput').value || '').replace(/\s+/g, ' ').trim();
                const key = normName(typed);
                const match = (columnOptions || []).find(function (name) { return normName(name) === key; });
                return { typed: typed, key: key, match: match || '' };
            }

            function renderSrcList() {
                const list = document.getElementById('e1dSrcList');
                const hint = document.getElementById('e1dSrcHint');
                const save = document.getElementById('e1dSrcSave');
                list.innerHTML = '';
                if (columnOptions === null) {
                    hint.textContent = 'Loading columns…';
                    save.disabled = true;
                    return;
                }
                const s = srcState();
                columnOptions.filter(function (name) {
                    return s.key === '' || normName(name).indexOf(s.key) !== -1;
                }).slice(0, 60).forEach(function (name) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = name;
                    if (normName(name) === s.key) b.className = 'active';
                    b.addEventListener('click', function () {
                        document.getElementById('e1dSrcInput').value = name;
                        renderSrcList();
                    });
                    list.appendChild(b);
                });
                const col = document.getElementById('e1dSrcCol').value;
                const current = col ? sourceFor(col) : '';
                if (!col) hint.textContent = 'Choose a column of this tab.';
                else if (s.key === '') hint.textContent = current ? 'Currently reads from “' + current + '”.' : 'No source yet. Pick the Technical Specifications column to read from.';
                else if (!s.match) hint.innerHTML = '<span class="text-danger">Pick a column from the list.</span>';
                else hint.innerHTML = 'After saving, <strong>' + esc(col) + '</strong> always shows the current value of <strong>' + esc(s.match) + '</strong> from Technical Specifications.';
                save.disabled = !col || !s.match;
                document.getElementById('e1dSrcClear').disabled = !current;
            }

            function openSrcModal() {
                const names = specNamesForTab();
                if (activeTab === ALL || !names.length) return;
                document.getElementById('e1dSrcIntro').textContent = 'Category: ' + catLabel(activeTab);
                const sel = document.getElementById('e1dSrcCol');
                sel.innerHTML = '';
                names.forEach(function (name) {
                    const opt = document.createElement('option');
                    opt.value = name;
                    const src = sourceFor(name);
                    opt.textContent = src ? name + '  ⇄  ' + src : name;
                    sel.appendChild(opt);
                });
                document.getElementById('e1dSrcInput').value = sourceFor(sel.value);
                document.getElementById('e1dSrcModal').hidden = false;
                renderSrcList();
                document.getElementById('e1dSrcInput').focus();
                loadColumnOptions();
            }

            function closeSrcModal() {
                document.getElementById('e1dSrcModal').hidden = true;
            }

            async function postSource(source) {
                const column = document.getElementById('e1dSrcCol').value;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const save = document.getElementById('e1dSrcSave');
                const hint = document.getElementById('e1dSrcHint');
                save.disabled = true;
                hint.textContent = 'Saving…';
                try {
                    const res = await fetch('/ebay1-data/save-source', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ category: tabCategory(), column: column, source: source }),
                    });
                    const json = await res.json();
                    if (!res.ok || !json.success) throw new Error(json.message || 'Request failed');
                    closeSrcModal();
                    document.getElementById('e1dPullStatus').textContent = json.removed
                        ? 'Source removed from "' + column + '".'
                        : '"' + column + '" now reads from "' + json.source + '".';
                    await loadData();
                } catch (e) {
                    hint.innerHTML = '<span class="text-danger">' + esc(e.message || 'Failed') + '</span>';
                    save.disabled = false;
                }
            }

            function updateDelButton() {
                const addBtn = document.getElementById('e1dAddCol');
                if (addBtn) addBtn.disabled = activeTab === ALL;
                const srcBtn = document.getElementById('e1dSrcBtn');
                if (srcBtn) srcBtn.disabled = activeTab === ALL || specNamesForTab().length === 0;
                const btn = document.getElementById('e1dDelCol');
                if (!btn) return;
                const has = activeTab !== ALL && specNamesForTab().length > 0;
                btn.disabled = !has;
                btn.title = has
                    ? 'Delete one item-specific column of this category, here and on the live eBay 1 listings'
                    : 'Open a category tab with pulled item specifics first';
            }

            // Columns ticked in the Delete Column window.
            let delPicked = new Set();

            function delTargets(names) {
                const list = Array.isArray(names) ? names : [names];
                return rows.filter(function (row) {
                    return passesTab(row)
                        && row.specifics && !Array.isArray(row.specifics)
                        && list.some(function (name) { return Object.prototype.hasOwnProperty.call(row.specifics, name); })
                        && /^\d{9,15}$/.test(String(row.item_id || '').trim());
                });
            }

            function refreshDelWarning() {
                const picked = Array.from(delPicked);
                const n = picked.length ? delTargets(picked).length : 0;
                const label = picked.length === 1 ? '<strong>' + esc(picked[0]) + '</strong>' : '<strong>' + picked.length + ' columns</strong>';
                document.getElementById('e1dDelWarn').innerHTML = !picked.length
                    ? 'Tick one or more columns.'
                    : (n === 0
                        ? 'No live eBay 1 listing in this category has ' + label + '. ' + (picked.length === 1 ? 'The column is' : 'The columns are') + ' only removed from this tab.'
                        : 'This removes ' + label + ' from <strong>' + n + '</strong> live eBay 1 listing' + (n === 1 ? '' : 's') + ' in this category and from the stored data. It cannot be undone here.');
                const go = document.getElementById('e1dDelGo');
                go.disabled = !picked.length;
                go.textContent = picked.length > 1 ? 'Delete ' + picked.length + ' columns' : 'Delete column';
                const all = document.getElementById('e1dDelAll');
                const total = specNamesForTab().length;
                all.checked = total > 0 && picked.length === total;
                all.indeterminate = picked.length > 0 && picked.length < total;
            }

            function renderDelList() {
                const names = specNamesForTab();
                delPicked = new Set(Array.from(delPicked).filter(function (name) { return names.indexOf(name) !== -1; }));
                const list = document.getElementById('e1dDelList');
                list.innerHTML = '';
                names.forEach(function (name) {
                    const row = document.createElement('label');
                    row.className = 'e1d-addopt';
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.checked = delPicked.has(name);
                    cb.addEventListener('change', function () {
                        if (cb.checked) delPicked.add(name); else delPicked.delete(name);
                        refreshDelWarning();
                    });
                    row.appendChild(cb);
                    row.appendChild(document.createTextNode(' ' + name + ' (' + delTargets(name).length + ')'));
                    list.appendChild(row);
                });
                refreshDelWarning();
            }

            function openDelModal(keepPicked) {
                const names = specNamesForTab();
                if (activeTab === ALL || !names.length) return;
                if (keepPicked !== true) delPicked = new Set();
                document.getElementById('e1dDelIntro').textContent = 'Category: ' + catLabel(activeTab);
                document.getElementById('e1dDelProgress').textContent = '';
                document.getElementById('e1dDelModal').hidden = false;
                renderDelList();
            }

            function closeDelModal() {
                document.getElementById('e1dDelModal').hidden = true;
            }

            async function runDelete() {
                const names = Array.from(delPicked);
                if (!names.length) return;
                const todo = delTargets(names);
                const label = names.length === 1 ? '"' + names[0] + '"' : names.length + ' columns';
                const category = activeTab === NO_CATEGORY ? '' : activeTab;
                if (!window.confirm(todo.length
                    ? 'Delete ' + label + ' from ' + todo.length + ' live eBay 1 listings?'
                    : 'Remove ' + label + ' from this tab?')) return;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const goBtn = document.getElementById('e1dDelGo');
                const progress = document.getElementById('e1dDelProgress');
                goBtn.disabled = true;
                document.getElementById('e1dDelCancel').disabled = true;
                let done = 0, failed = 0;
                const problems = [];
                const size = 5;
                // With no listing to change, one call still drops the columns of this tab.
                for (let i = 0; i < Math.max(todo.length, 1); i += size) {
                    const chunk = todo.slice(i, i + size).map(function (row) { return { sku: row.sku, item_id: String(row.item_id).trim() }; });
                    progress.textContent = 'Deleting ' + Math.min(i + size, todo.length) + ' / ' + todo.length + '…';
                    try {
                        const res = await fetch('/ebay1-data/delete-specific', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ names: names, category: category, items: chunk }),
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error(json.message || 'Request failed');
                        (json.results || []).forEach(function (r) {
                            if (r.status === 'failed') { failed++; problems.push(r.sku + ': ' + r.message); } else { done++; }
                        });
                    } catch (e) {
                        failed += chunk.length;
                        problems.push(e.message || 'Request failed');
                    }
                }
                document.getElementById('e1dDelCancel').disabled = false;
                progress.textContent = 'Deleted ' + done + (failed ? ', failed ' + failed : '') + '.';
                if (problems.length) window.alert(problems.slice(0, 15).join('\n') + (problems.length > 15 ? '\n…' : ''));
                await loadData();
                if (failed) {
                    openDelModal(true);
                    document.getElementById('e1dDelProgress').textContent = 'Deleted ' + done + ', failed ' + failed + '. Delete again to retry the failed SKUs.';
                } else {
                    closeDelModal();
                }
            }

            // SKUs with a live eBay item ID whose item specifics were not pulled yet.
            function pendingPull() {
                return rows.filter(function (row) { return !row.specs_pulled && /^\d{9,15}$/.test(String(row.item_id || '').trim()); });
            }

            function updatePullButton() {
                const btn = document.getElementById('e1dPull');
                if (!btn || btn.dataset.busy === '1') return;
                const pending = pendingPull().length;
                const done = rows.filter(function (row) { return row.specs_pulled; }).length;
                btn.disabled = pending === 0;
                document.getElementById('e1dPullLabel').textContent = pending === 0
                    ? 'Item specifics pulled'
                    : 'Pull item specifics (one time) · ' + pending;
                document.getElementById('e1dPullStatus').textContent = done ? done + ' SKUs pulled' : '';
            }

            async function pullSpecifics() {
                const btn = document.getElementById('e1dPull');
                const status = document.getElementById('e1dPullStatus');
                const todo = pendingPull();
                if (!todo.length) return;
                if (!window.confirm('Pull the category and item specifics of ' + todo.length + ' SKUs from the live eBay 1 listings?\nThis runs once. SKUs already pulled are never overwritten.')) return;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                btn.dataset.busy = '1';
                btn.disabled = true;
                let done = 0, failed = 0;
                const problems = [];
                const size = 5;
                for (let i = 0; i < todo.length; i += size) {
                    const chunk = todo.slice(i, i + size).map(function (row) { return { sku: row.sku, item_id: String(row.item_id).trim() }; });
                    status.textContent = 'Pulling ' + Math.min(i + size, todo.length) + ' / ' + todo.length + '…';
                    try {
                        const res = await fetch('/ebay1-data/pull-specifics', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ items: chunk }),
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error(json.message || 'Request failed');
                        (json.results || []).forEach(function (r) {
                            if (r.status === 'failed') { failed++; problems.push(r.sku + ': ' + r.message); } else { done++; }
                        });
                    } catch (e) {
                        failed += chunk.length;
                        problems.push(e.message || 'Request failed');
                    }
                }
                btn.dataset.busy = '0';
                status.textContent = 'Pulled ' + done + (failed ? ', failed ' + failed : '') + '.';
                if (problems.length) window.alert(problems.slice(0, 15).join('\n') + (problems.length > 15 ? '\n…' : ''));
                await loadData();
                if (failed) status.textContent = 'Pulled ' + done + ', failed ' + failed + ' (click the button to retry those).';
            }

            function numberFormatter(decimals) {
                return function (cell) {
                    const v = cell.getValue();
                    if (v === null || v === undefined || v === '') return '<span class="e1d-muted">—</span>';
                    return Number(v).toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
                };
            }

            function textFormatter(cell) {
                const v = String(cell.getValue() || '').trim();
                return v ? esc(v) : '<span class="e1d-muted">—</span>';
            }

            // Item specifics pulled from eBay become columns of the active category tab (none on the All tab).
            let specSignature = '';
            let columnSources = [];

            function specKey(name) {
                return 'sp_' + String(name).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
            }

            function flattenSpecifics(list) {
                list.forEach(function (row) {
                    const spec = row.specifics && !Array.isArray(row.specifics) ? row.specifics : {};
                    Object.keys(spec).forEach(function (name) { row[specKey(name)] = spec[name]; });
                });
            }

            function specNamesForTab() {
                if (activeTab === ALL) return [];
                const counts = new Map();
                rows.forEach(function (row) {
                    if (!passesTab(row) || !row.specifics || Array.isArray(row.specifics)) return;
                    Object.keys(row.specifics).forEach(function (name) { counts.set(name, (counts.get(name) || 0) + 1); });
                });
                return Array.from(counts.keys()).sort(function (a, b) {
                    return (counts.get(b) - counts.get(a)) || a.localeCompare(b);
                });
            }

            function syncSpecColumns(force) {
                if (!table || !tableReady) return;
                const names = specNamesForTab();
                const signature = activeTab + '|' + names.map(function (name) { return name + '=' + sourceFor(name); }).join('|');
                if (!force && signature === specSignature) return;
                specSignature = signature;
                const specColumns = names.map(function (name) {
                    const src = sourceFor(name);
                    return {
                        title: src ? esc(name) + ' <i class="fas fa-link" style="font-size:10px;opacity:.8"></i>' : esc(name),
                        field: specKey(name),
                        minWidth: 110,
                        headerTooltip: src ? 'Synced from Technical Specifications: ' + src : name,
                        formatter: textFormatter,
                    };
                });
                table.setColumns(baseColumns().concat(specColumns));
            }

            function baseColumns() {
                return [
                        {
                            formatter: 'rowSelection', titleFormatter: 'rowSelection', titleFormatterParams: { rowRange: 'active' },
                            hozAlign: 'center', headerHozAlign: 'center', headerSort: false, resizable: false,
                            width: 44, minWidth: 44, frozen: true, download: false, cssClass: 'e1d-select-col',                        },
                        { title: 'Parent', field: 'parent', minWidth: 90, formatter: textFormatter },
                        {
                            title: 'SKU', field: 'sku', minWidth: 130, frozen: true,
                            formatter: function (cell) { return '<strong>' + esc(cell.getValue()) + '</strong>'; },
                        },
                        { title: 'INV', field: 'inv', hozAlign: 'right', width: 70, formatter: numberFormatter(0) },
                        { title: 'Sold L30', field: 'sold_l30', hozAlign: 'right', width: 80, formatter: numberFormatter(0) },
                        {
                            title: 'REQ/NRL', field: 'nr_req', hozAlign: 'center', width: 96,
                            headerTooltip: 'Click a value to change it. Saved to the same data as the Listing eBay page.',
                            editor: 'list',
                            editorParams: { values: { REQ: 'REQ', NR: 'NRL' }, autocomplete: false, listOnEmpty: true, clearable: false },
                            formatter: function (cell) {
                                return cell.getValue() === 'NR'
                                    ? '<span class="e1d-pill red">NRL</span>'
                                    : '<span class="e1d-pill green">REQ</span>';
                            },
                            cellEdited: function (cell) { saveNrReq(cell); },
                        },
                        {
                            title: 'Listed', field: 'listed', hozAlign: 'center', width: 100,
                            sorter: function (a, b, aRow, bRow) {
                                const rank = function (d) { return d.listed === 'Listed' ? 2 : (d.listed === 'Created' ? 1 : 0); };
                                return rank(aRow.getData()) - rank(bRow.getData());
                            },
                            formatter: function (cell) {
                                const d = cell.getRow().getData();
                                if (d.listed === 'Listed') return '<span class="e1d-pill blue">Listed</span>';
                                if (d.listed === 'Created') return '<span class="e1d-pill amber">Created</span>';
                                if (!inStock(d) || d.nr_req === 'NR') return '<span class="e1d-pill grey">Not listed</span>';
                                return '<span class="e1d-pill red">Missing L</span>';
                            },
                        },
                        {
                            title: 'Item ID', field: 'item_id', minWidth: 120,
                            formatter: function (cell) {
                                const id = String(cell.getValue() || '').trim();
                                if (!id) return '<span class="e1d-muted">—</span>';
                                const d = cell.getRow().getData();
                                const href = /^https?:/i.test(d.buyer_link || '') ? d.buyer_link : 'https://www.ebay.com/itm/' + encodeURIComponent(id);
                                return '<a href="' + esc(href) + '" target="_blank" rel="noopener">' + esc(id) + '</a>';
                            },
                        },
                        { title: 'Status', field: 'listing_status', width: 96, formatter: textFormatter },
                        { title: 'eBay title', field: 'ebay_title', minWidth: 260, formatter: textFormatter },
                        { title: 'Price', field: 'ebay_price', hozAlign: 'right', width: 86, formatter: numberFormatter(2) },
                        { title: 'Stock', field: 'ebay_stock', hozAlign: 'right', width: 72, formatter: numberFormatter(0) },
                        { title: 'L7', field: 'ebay_l7', hozAlign: 'right', width: 60, formatter: numberFormatter(0) },
                        { title: 'L30', field: 'ebay_l30', hozAlign: 'right', width: 60, formatter: numberFormatter(0) },
                        { title: 'L60', field: 'ebay_l60', hozAlign: 'right', width: 60, formatter: numberFormatter(0) },
                        { title: 'Views', field: 'views', hozAlign: 'right', width: 76, formatter: numberFormatter(0) },
                        { title: 'L7 Views', field: 'l7_views', hozAlign: 'right', width: 84, formatter: numberFormatter(0) },
                        { title: 'L1 Views', field: 'l1_views', hozAlign: 'right', width: 84, formatter: numberFormatter(0) },
                        { title: 'Organic Clicks', field: 'organic_clicks', hozAlign: 'right', width: 110, formatter: numberFormatter(0) },
                        { title: 'Reason', field: 'inactive_reason', minWidth: 120, formatter: textFormatter },
                        { title: 'Report date', field: 'report_date', width: 110, formatter: textFormatter },
                        { title: 'Category', field: 'category', minWidth: 260, formatter: textFormatter },
                ];
            }

            function buildTable() {
                table = new Tabulator('#ebay1-data-table', {
                    data: rows,
                    layout: 'fitDataFill',
                    height: 'calc(100vh - 330px)',
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [50, 100, 200, 500, true],
                    placeholder: 'No rows',
                    initialSort: [{ column: 'sku', dir: 'asc' }],
                    selectableRows: true,
                    selectableRowsRangeMode: 'click',
                    columns: baseColumns(),
                });
                table.on('rowSelectionChanged', function () { renderBadges(); updatePushButton(); });
                table.on('tableBuilt', function () {
                    tableReady = true;
                    syncSpecColumns(true);
                    applyFilters();
                });
                table.on('dataFiltered', function () { /* badges are computed from the same rules */ });
            }

            async function loadData() {
                const placeholder = document.getElementById('e1dBadges');
                placeholder.innerHTML = '<span class="e1d-badge rows">Loading…</span>';
                try {
                    const res = await fetch('/ebay1-data/rows', { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    if (!res.ok || json.status !== 200) {
                        throw new Error(json.message || 'Failed to load Ebay1 Data');
                    }
                    rows = (json.data || []).filter(function (row) { return !isParentRow(row); });
                    columnSources = Array.isArray(json.sources) ? json.sources : [];
                    flattenSpecifics(rows);
                    if (table && tableReady) {
                        table.replaceData(rows);
                        renderTabs();
                        syncSpecColumns(true);
                    } else if (!table) {
                        buildTable();
                    }
                    refreshAll();
                } catch (e) {
                    placeholder.innerHTML = '<span class="e1d-badge nrl">' + esc(e.message || 'Failed to load') + '</span>';
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                document.getElementById('e1dSearch').addEventListener('input', function () {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(refreshAll, 200);
                });
                ['e1dListed', 'e1dNr', 'e1dStock'].forEach(function (id) {
                    document.getElementById(id).addEventListener('change', refreshAll);
                });
                document.getElementById('e1dRefresh').addEventListener('click', loadData);
                document.getElementById('e1dPull').addEventListener('click', pullSpecifics);
                document.getElementById('e1dPush').addEventListener('click', pushToEbay);
                document.getElementById('e1dAddCol').addEventListener('click', openAddModal);
                document.getElementById('e1dAddCancel').addEventListener('click', closeAddModal);
                document.getElementById('e1dAddGo').addEventListener('click', runAddColumn);
                document.getElementById('e1dAddInput').addEventListener('input', renderAddList);
                document.getElementById('e1dAddInput').addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); addTypedOrSubmit(); }
                    if (e.key === 'Escape') closeAddModal();
                });
                document.getElementById('e1dSrcBtn').addEventListener('click', openSrcModal);
                document.getElementById('e1dSrcCancel').addEventListener('click', closeSrcModal);
                document.getElementById('e1dSrcCol').addEventListener('change', function () {
                    document.getElementById('e1dSrcInput').value = sourceFor(this.value);
                    renderSrcList();
                });
                document.getElementById('e1dSrcInput').addEventListener('input', renderSrcList);
                document.getElementById('e1dSrcSave').addEventListener('click', function () { postSource(srcState().match); });
                document.getElementById('e1dSrcClear').addEventListener('click', function () { postSource(''); });
                document.getElementById('e1dDelCol').addEventListener('click', function () { openDelModal(); });
                document.getElementById('e1dDelCancel').addEventListener('click', closeDelModal);
                document.getElementById('e1dDelAll').addEventListener('change', function () {
                    delPicked = this.checked ? new Set(specNamesForTab()) : new Set();
                    renderDelList();
                });
                document.getElementById('e1dDelGo').addEventListener('click', runDelete);
                document.getElementById('e1dExport').addEventListener('click', function () {
                    if (!table) return;
                    const label = activeTab === ALL ? 'all' : catLabel(activeTab).replace(/[^a-z0-9]+/gi, '_').toLowerCase();
                    table.download('csv', 'ebay1_data_' + label + '.csv');
                });
                loadData();
            });
        })();
    </script>
@endsection
