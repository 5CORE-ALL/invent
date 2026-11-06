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

    <div class="e1d-modal" id="e1dDelModal" hidden>
        <div class="e1d-modal-box" role="dialog" aria-modal="true" aria-labelledby="e1dDelTitle">
            <h5 id="e1dDelTitle" class="mb-2"><i class="fas fa-trash-alt text-danger"></i> Delete Column</h5>
            <p class="small text-muted mb-2" id="e1dDelIntro"></p>
            <label class="form-label small fw-bold" for="e1dDelSelect">Column</label>
            <select id="e1dDelSelect" class="form-select form-select-sm mb-2"></select>
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
                return /^parent\s/i.test(String(row.sku || ''));
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
            }

            // ---- Delete Column: removes one item specific from every SKU of the open tab, here and on eBay 1. ----
            function updateDelButton() {
                const btn = document.getElementById('e1dDelCol');
                if (!btn) return;
                const has = activeTab !== ALL && specNamesForTab().length > 0;
                btn.disabled = !has;
                btn.title = has
                    ? 'Delete one item-specific column of this category, here and on the live eBay 1 listings'
                    : 'Open a category tab with pulled item specifics first';
            }

            function delTargets(name) {
                return rows.filter(function (row) {
                    return passesTab(row)
                        && row.specifics && !Array.isArray(row.specifics) && Object.prototype.hasOwnProperty.call(row.specifics, name)
                        && /^\d{9,15}$/.test(String(row.item_id || '').trim());
                });
            }

            function refreshDelWarning() {
                const name = document.getElementById('e1dDelSelect').value;
                const n = name ? delTargets(name).length : 0;
                document.getElementById('e1dDelWarn').innerHTML = name
                    ? 'This removes <strong>' + esc(name) + '</strong> from <strong>' + n + '</strong> live eBay 1 listing' + (n === 1 ? '' : 's') + ' in this category and from the stored data. It cannot be undone here.'
                    : 'Choose a column.';
                document.getElementById('e1dDelGo').disabled = !name || n === 0;
            }

            function openDelModal() {
                const names = specNamesForTab();
                if (activeTab === ALL || !names.length) return;
                document.getElementById('e1dDelIntro').textContent = 'Category: ' + catLabel(activeTab);
                const sel = document.getElementById('e1dDelSelect');
                sel.innerHTML = '';
                names.forEach(function (name) {
                    const opt = document.createElement('option');
                    opt.value = name;
                    opt.textContent = name + ' (' + delTargets(name).length + ')';
                    sel.appendChild(opt);
                });
                document.getElementById('e1dDelProgress').textContent = '';
                document.getElementById('e1dDelModal').hidden = false;
                refreshDelWarning();
            }

            function closeDelModal() {
                document.getElementById('e1dDelModal').hidden = true;
            }

            async function runDelete() {
                const name = document.getElementById('e1dDelSelect').value;
                const todo = delTargets(name);
                if (!name || !todo.length) return;
                if (!window.confirm('Delete "' + name + '" from ' + todo.length + ' live eBay 1 listings?')) return;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const goBtn = document.getElementById('e1dDelGo');
                const progress = document.getElementById('e1dDelProgress');
                goBtn.disabled = true;
                document.getElementById('e1dDelCancel').disabled = true;
                let done = 0, failed = 0;
                const problems = [];
                const size = 5;
                for (let i = 0; i < todo.length; i += size) {
                    const chunk = todo.slice(i, i + size).map(function (row) { return { sku: row.sku, item_id: String(row.item_id).trim() }; });
                    progress.textContent = 'Deleting ' + Math.min(i + size, todo.length) + ' / ' + todo.length + '…';
                    try {
                        const res = await fetch('/ebay1-data/delete-specific', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ name: name, items: chunk }),
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
                    openDelModal();
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
                const signature = activeTab + '|' + names.join('|');
                if (!force && signature === specSignature) return;
                specSignature = signature;
                const specColumns = names.map(function (name) {
                    return { title: name, field: specKey(name), minWidth: 110, formatter: textFormatter };
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
                            title: 'REQ/NRL', field: 'nr_req', hozAlign: 'center', width: 88,
                            formatter: function (cell) {
                                return cell.getValue() === 'NR'
                                    ? '<span class="e1d-pill red">NRL</span>'
                                    : '<span class="e1d-pill green">REQ</span>';
                            },
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
                table.on('rowSelectionChanged', function () { renderBadges(); });
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
                document.getElementById('e1dDelCol').addEventListener('click', openDelModal);
                document.getElementById('e1dDelCancel').addEventListener('click', closeDelModal);
                document.getElementById('e1dDelSelect').addEventListener('change', refreshDelWarning);
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
