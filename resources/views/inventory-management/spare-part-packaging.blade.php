@extends('layouts.vertical', ['title' => 'Spare / Parts / Packaging', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #spp-table .tabulator { border: 1px solid #dee2e6; border-radius: 0 0 8px 8px; font-size: 13px; }
        #spp-table .tabulator .tabulator-header { background: #dbeafe; }
        #spp-table .tabulator .tabulator-row .tabulator-cell { white-space: normal; vertical-align: middle; }
        .spp-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; cursor: zoom-in; }
        .spp-combo { position: relative; }
        .spp-combo-list { position: absolute; z-index: 2000; left: 0; right: 0; top: 100%; max-height: 220px; overflow-y: auto; background: #fff; border: 1px solid #ced4da; border-radius: 6px; box-shadow: 0 6px 16px rgba(0,0,0,.15); margin-top: 2px; }
        .spp-combo-item { padding: 5px 10px; font-size: 13px; cursor: pointer; display: flex; justify-content: space-between; gap: 8px; }
        .spp-combo-item small { color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .spp-combo-item.active, .spp-combo-item:hover { background: #dbeafe; }
        .spp-combo-empty { padding: 6px 10px; font-size: 13px; color: #94a3b8; }
        #spp-image-preview { max-width: 120px; max-height: 120px; border-radius: 6px; border: 1px solid #e2e8f0; display: none; margin-top: 6px; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Spare / Parts / Packaging',
        'sub_title' => 'Inventory',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <h4 class="mb-0 me-2">Spare / Parts / Packaging</h4>
                        <span class="badge bg-light text-dark" id="spp-count">0</span>
                        <button type="button" class="btn btn-primary btn-sm" id="spp-add"><i class="bi bi-plus-lg"></i> Add +</button>
                        <div class="ms-auto" style="min-width:240px;">
                            <input type="search" id="spp-search" class="form-control form-control-sm" placeholder="Search name, SKU, parent, supplier…" autocomplete="off" maxlength="100">
                        </div>
                    </div>
                    <div id="spp-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="spp-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="spp-modal-title">Add spare / part / packaging</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label small mb-1" for="spp-name">Name of part / packaging <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="spp-name" maxlength="255">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1" for="spp-image">Image</label>
                            <input type="file" class="form-control form-control-sm" id="spp-image" accept="image/*">
                            <img id="spp-image-preview" alt="Preview">
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1" for="spp-description">Description</label>
                            <textarea class="form-control form-control-sm" id="spp-description" rows="2" maxlength="2000"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1" for="spp-moq">MOQ</label>
                            <input type="number" class="form-control form-control-sm" id="spp-moq" min="0" step="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1" for="spp-msl">MSL</label>
                            <input type="number" class="form-control form-control-sm" id="spp-msl" min="0" step="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1" for="spp-qty">Qty Available</label>
                            <input type="number" class="form-control form-control-sm" id="spp-qty" step="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1" for="spp-sku">SKU</label>
                            <div class="spp-combo" data-combo="sku">
                                <input type="text" class="form-control form-control-sm" id="spp-sku" placeholder="Type to search SKU" autocomplete="off">
                                <div class="spp-combo-list d-none"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1" for="spp-parent">Parent</label>
                            <div class="spp-combo" data-combo="parent">
                                <input type="text" class="form-control form-control-sm" id="spp-parent" placeholder="Type to search parent" autocomplete="off">
                                <div class="spp-combo-list d-none"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1" for="spp-supplier">Supplier</label>
                            <div class="spp-combo" data-combo="supplier">
                                <input type="text" class="form-control form-control-sm" id="spp-supplier" placeholder="Type to search supplier" autocomplete="off">
                                <div class="spp-combo-list d-none"></div>
                            </div>
                        </div>
                    </div>
                    <div class="text-danger small mt-2 d-none" id="spp-error"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="spp-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="spp-qty-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2" id="spp-qty-meta"></div>
                    <label class="form-label small mb-1" for="spp-qty-add">Quantity to add</label>
                    <input type="number" class="form-control form-control-sm" id="spp-qty-add" min="1" step="1">
                    <div class="text-danger small mt-2 d-none" id="spp-qty-error"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success btn-sm" id="spp-qty-save">Add</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="spp-cr-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">CR — request to supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2">From: <span id="spp-cr-from" class="fw-semibold">—</span> · the draft below is editable.</div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="spp-cr-to">To (supplier email; separate several with commas)</label>
                        <input type="text" class="form-control form-control-sm" id="spp-cr-to" autocomplete="off" placeholder="supplier@example.com">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="spp-cr-subject">Subject</label>
                        <input type="text" class="form-control form-control-sm" id="spp-cr-subject" maxlength="255">
                    </div>
                    <div>
                        <label class="form-label small mb-1" for="spp-cr-body">Message</label>
                        <textarea class="form-control form-control-sm" id="spp-cr-body" rows="14" style="font-family: inherit;"></textarea>
                    </div>
                    <div class="small mt-2 d-none" id="spp-cr-msg"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="spp-cr-gmail" title="Open this draft in your own Gmail compose window"><i class="bi bi-envelope"></i> Open in Gmail</button>
                    <button type="button" class="btn btn-primary btn-sm" id="spp-cr-send"><i class="bi bi-send"></i> Send</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            'use strict';
            const CSRF = @json(csrf_token());
            const URLS = {
                data: @json(route('spare.part.packaging.data')),
                options: @json(route('spare.part.packaging.options')),
                store: @json(route('spare.part.packaging.store')),
                update: @json(route('spare.part.packaging.update', ['id' => '__ID__'])),
                addQty: @json(route('spare.part.packaging.add.qty', ['id' => '__ID__'])),
                destroy: @json(route('spare.part.packaging.destroy', ['id' => '__ID__'])),
                crDraft: @json(route('spare.part.packaging.cr.draft', ['id' => '__ID__'])),
                crSend: @json(route('spare.part.packaging.cr.send', ['id' => '__ID__'])),
            };
            let editId = null;
            let qtyId = null;
            let crId = null;
            function url(tpl, id) { return tpl.replace('__ID__', String(id)); }
            let table = null;
            let optionsLoaded = false;
            let SKUS = []; // [{sku, parent}]
            let PARENTS = [], SUPPLIERS = [];

            function el(id) { return document.getElementById(id); }
            function esc(v) { const d = document.createElement('div'); d.textContent = String(v ?? ''); return d.innerHTML; }
            const MAX_ITEMS = 100;

            // Searchable dropdown: type to filter, click / Enter to pick. getItems() -> [{value, hint}]
            function combo(name, getItems, onPick) {
                const box = document.querySelector('.spp-combo[data-combo="' + name + '"]');
                const input = box.querySelector('input');
                const list = box.querySelector('.spp-combo-list');
                let shown = [], idx = -1;

                function render() {
                    const t = input.value.trim().toLowerCase();
                    const all = getItems();
                    shown = [];
                    for (let i = 0; i < all.length && shown.length < MAX_ITEMS; i++) {
                        const it = all[i];
                        if (!t || (it.value + ' ' + (it.hint || '')).toLowerCase().indexOf(t) !== -1) shown.push(it);
                    }
                    idx = shown.length ? 0 : -1;
                    list.innerHTML = shown.length
                        ? shown.map(function (it, i) {
                            return '<div class="spp-combo-item' + (i === idx ? ' active' : '') + '" data-i="' + i + '"><span>' + esc(it.value) + '</span>' + (it.hint ? '<small>' + esc(it.hint) + '</small>' : '') + '</div>';
                        }).join('')
                        : '<div class="spp-combo-empty">No match</div>';
                    list.classList.remove('d-none');
                }
                function close() { list.classList.add('d-none'); }
                function pick(i) {
                    const it = shown[i];
                    if (!it) return;
                    input.value = it.value;
                    close();
                    if (onPick) onPick(it);
                }
                function mark() {
                    Array.prototype.forEach.call(list.children, function (c, i) {
                        c.classList.toggle('active', i === idx);
                        if (i === idx && c.scrollIntoView) c.scrollIntoView({ block: 'nearest' });
                    });
                }
                input.addEventListener('focus', render);
                input.addEventListener('input', render);
                input.addEventListener('keydown', function (e) {
                    if (list.classList.contains('d-none')) { if (e.key === 'ArrowDown') render(); return; }
                    if (e.key === 'ArrowDown') { e.preventDefault(); idx = Math.min(idx + 1, shown.length - 1); mark(); }
                    else if (e.key === 'ArrowUp') { e.preventDefault(); idx = Math.max(idx - 1, 0); mark(); }
                    else if (e.key === 'Enter') { if (idx >= 0) { e.preventDefault(); pick(idx); } }
                    else if (e.key === 'Escape' || e.key === 'Tab') close();
                });
                list.addEventListener('mousedown', function (e) {
                    const row = e.target.closest('.spp-combo-item');
                    if (row) { e.preventDefault(); pick(parseInt(row.getAttribute('data-i'), 10)); }
                });
                document.addEventListener('mousedown', function (e) { if (!box.contains(e.target)) close(); });
            }
            function inList(values, v) {
                const t = v.trim().toLowerCase();
                return values.some(function (x) { return String(x).trim().toLowerCase() === t; });
            }

            async function loadOptions() {
                if (optionsLoaded) return;
                const res = await fetch(URLS.options, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const json = await res.json();
                SKUS = json.skus || []; PARENTS = json.parents || []; SUPPLIERS = json.suppliers || [];
                optionsLoaded = true;
            }

            function resetForm() {
                ['spp-name', 'spp-description', 'spp-moq', 'spp-msl', 'spp-qty', 'spp-parent', 'spp-sku', 'spp-supplier'].forEach(function (id) { el(id).value = ''; });
                el('spp-image').value = '';
                el('spp-image-preview').style.display = 'none';
                el('spp-error').classList.add('d-none');
                editId = null;
                el('spp-modal-title').textContent = 'Add spare / part / packaging';
            }

            function showError(msg) { const e = el('spp-error'); e.textContent = msg; e.classList.remove('d-none'); }

            async function save() {
                const name = el('spp-name').value.trim();
                const sku = el('spp-sku').value.trim();
                const parent = el('spp-parent').value.trim();
                const supplier = el('spp-supplier').value.trim();
                if (!name) return showError('Enter the name of the part / packaging.');
                if (sku && !inList(SKUS.map(function (s) { return s.sku; }), sku)) return showError('Pick a SKU from the list.');
                if (parent && !inList(PARENTS, parent)) return showError('Pick a Parent from the list.');
                if (supplier && !inList(SUPPLIERS, supplier)) return showError('Pick a Supplier from the list.');

                const fd = new FormData();
                fd.append('name', name);
                fd.append('description', el('spp-description').value.trim());
                fd.append('moq', el('spp-moq').value);
                fd.append('msl', el('spp-msl').value);
                fd.append('qty_available', el('spp-qty').value);
                fd.append('parent', parent);
                fd.append('sku', sku);
                fd.append('supplier', supplier);
                const file = el('spp-image').files[0];
                if (file) fd.append('image', file);

                const btn = el('spp-save');
                btn.disabled = true;
                try {
                    const res = await fetch(editId ? url(URLS.update, editId) : URLS.store, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
                        body: fd,
                    });
                    const json = await res.json().catch(function () { return {}; });
                    if (!res.ok) {
                        const first = json.errors ? Object.values(json.errors)[0] : null;
                        throw new Error((first && first[0]) || json.message || 'Save failed (HTTP ' + res.status + ')');
                    }
                    bootstrap.Modal.getOrCreateInstance(el('spp-modal')).hide();
                    table.setData(URLS.data);
                } catch (e) {
                    showError(e.message);
                } finally {
                    btn.disabled = false;
                }
            }

            async function openEdit(r) {
                resetForm();
                editId = r.id;
                el('spp-modal-title').textContent = 'Edit spare / part / packaging';
                el('spp-name').value = r.name || '';
                el('spp-description').value = r.description || '';
                el('spp-moq').value = r.moq ?? '';
                el('spp-msl').value = r.msl ?? '';
                el('spp-qty').value = r.qty_available ?? '';
                el('spp-sku').value = r.sku || '';
                el('spp-parent').value = r.parent || '';
                el('spp-supplier').value = r.supplier || '';
                if (r.image_url) { const img = el('spp-image-preview'); img.src = r.image_url; img.style.display = 'block'; }
                bootstrap.Modal.getOrCreateInstance(el('spp-modal')).show();
                try { await loadOptions(); } catch (e) { showError('Could not load the SKU / Parent / Supplier lists.'); }
            }

            function openQty(r) {
                qtyId = r.id;
                el('spp-qty-meta').textContent = r.name + ' — available: ' + (r.qty_available ?? 0);
                el('spp-qty-add').value = '';
                el('spp-qty-error').classList.add('d-none');
                bootstrap.Modal.getOrCreateInstance(el('spp-qty-modal')).show();
                setTimeout(function () { el('spp-qty-add').focus(); }, 300);
            }

            async function saveQty() {
                const q = parseInt(el('spp-qty-add').value, 10);
                const err = el('spp-qty-error');
                if (!(q >= 1)) { err.textContent = 'Enter a quantity of 1 or more.'; err.classList.remove('d-none'); return; }
                const btn = el('spp-qty-save');
                btn.disabled = true;
                try {
                    const res = await fetch(url(URLS.addQty, qtyId), {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ qty: q }),
                    });
                    const json = await res.json().catch(function () { return {}; });
                    if (!res.ok) throw new Error(json.message || 'Could not add stock (HTTP ' + res.status + ')');
                    bootstrap.Modal.getOrCreateInstance(el('spp-qty-modal')).hide();
                    table.setData(URLS.data);
                } catch (e) {
                    err.textContent = e.message; err.classList.remove('d-none');
                } finally {
                    btn.disabled = false;
                }
            }

            function crMsg(text, ok) {
                const m = el('spp-cr-msg');
                m.textContent = text;
                m.className = 'small mt-2 ' + (ok ? 'text-success' : 'text-danger');
            }

            async function openCr(r) {
                crId = r.id;
                ['spp-cr-to', 'spp-cr-subject', 'spp-cr-body'].forEach(function (id) { el(id).value = ''; });
                el('spp-cr-from').textContent = '…';
                el('spp-cr-msg').className = 'small mt-2 d-none';
                bootstrap.Modal.getOrCreateInstance(el('spp-cr-modal')).show();
                try {
                    const res = await fetch(url(URLS.crDraft, r.id), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const j = await res.json();
                    if (!res.ok) throw new Error(j.message || 'HTTP ' + res.status);
                    el('spp-cr-to').value = j.to || '';
                    el('spp-cr-subject').value = j.subject || '';
                    el('spp-cr-body').value = j.body || '';
                    el('spp-cr-from').textContent = j.from || '—';
                    if (!j.to) crMsg(j.supplier ? 'No email saved for supplier "' + j.supplier + '". Type the address in To.' : 'This row has no supplier. Type the address in To.', false);
                } catch (e) {
                    crMsg('Could not load the draft: ' + e.message, false);
                }
            }

            async function sendCr() {
                const btn = el('spp-cr-send');
                btn.disabled = true;
                crMsg('Sending…', true);
                try {
                    const res = await fetch(url(URLS.crSend, crId), {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ to: el('spp-cr-to').value, subject: el('spp-cr-subject').value, body: el('spp-cr-body').value }),
                    });
                    const j = await res.json().catch(function () { return {}; });
                    if (!res.ok) {
                        const first = j.errors ? Object.values(j.errors)[0] : null;
                        throw new Error((first && first[0]) || j.message || 'HTTP ' + res.status);
                    }
                    crMsg(j.message || 'Email sent.', true);
                } catch (e) {
                    crMsg(e.message, false);
                } finally {
                    btn.disabled = false;
                }
            }

            function openInGmail() {
                const q = new URLSearchParams({ view: 'cm', fs: '1', to: el('spp-cr-to').value, su: el('spp-cr-subject').value, body: el('spp-cr-body').value });
                window.open('https://mail.google.com/mail/?' + q.toString(), '_blank', 'noopener');
            }

            async function trashRow(r) {
                if (!window.confirm('Delete "' + r.name + '"? This cannot be undone.')) return;
                try {
                    const res = await fetch(url(URLS.destroy, r.id), {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
                    });
                    const json = await res.json().catch(function () { return {}; });
                    if (!res.ok) throw new Error(json.message || 'Delete failed (HTTP ' + res.status + ')');
                    table.setData(URLS.data);
                } catch (e) {
                    window.alert(e.message);
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                el('spp-qty-save').addEventListener('click', saveQty);
                el('spp-cr-send').addEventListener('click', sendCr);
                el('spp-cr-gmail').addEventListener('click', openInGmail);
                table = new Tabulator('#spp-table', {
                    ajaxURL: URLS.data,
                    ajaxResponse: function (url, params, response) { return (response && response.data) || []; },
                    layout: 'fitColumns',
                    pagination: true,
                    paginationSize: 25,
                    paginationSizeSelector: [10, 25, 50, 100],
                    placeholder: 'No spare / parts / packaging added yet. Click Add +.',
                    columns: [
                        { title: 'Name of part / packaging', field: 'name', minWidth: 200, headerFilter: 'input', formatter: function (c) { return '<span class="fw-semibold">' + esc(c.getValue()) + '</span>'; } },
                        { title: 'Description', field: 'description', minWidth: 220, headerSort: false, formatter: function (c) { return esc(c.getValue() || '—'); } },
                        {
                            title: 'Image', field: 'image_url', width: 90, hozAlign: 'center', headerSort: false,
                            formatter: function (c) {
                                const u = c.getValue();
                                return u ? '<a href="' + esc(u) + '" target="_blank" rel="noopener"><img class="spp-thumb" src="' + esc(u) + '" alt=""></a>' : '<span class="text-muted">—</span>';
                            },
                        },
                        { title: 'MOQ', field: 'moq', width: 90, hozAlign: 'center', sorter: 'number', formatter: function (c) { const v = c.getValue(); return v === null || v === undefined ? '—' : esc(v); } },
                        { title: 'MSL', field: 'msl', width: 90, hozAlign: 'center', sorter: 'number', formatter: function (c) { const v = c.getValue(); return v === null || v === undefined ? '—' : esc(v); } },
                        { title: 'Qty Available', field: 'qty_available', width: 120, hozAlign: 'center', sorter: 'number', formatter: function (c) {
                            const v = c.getValue();
                            if (v === null || v === undefined) return '<span class="text-muted">—</span>';
                            const msl = c.getRow().getData().msl;
                            const low = msl !== null && msl !== undefined && Number(v) <= Number(msl);
                            return '<span class="fw-semibold' + (low ? ' text-danger' : '') + '">' + esc(v) + '</span>';
                        } },
                        { title: 'Parent', field: 'parent', width: 170, headerFilter: 'input', formatter: function (c) { return esc(c.getValue() || '—'); } },
                        { title: 'SKU', field: 'sku', width: 180, headerFilter: 'input', formatter: function (c) { return esc(c.getValue() || '—'); } },
                        { title: 'Supplier', field: 'supplier', width: 180, headerFilter: 'input', formatter: function (c) { return esc(c.getValue() || '—'); } },
                        {
                            title: 'Action', field: 'id', width: 175, hozAlign: 'center', headerSort: false, frozen: true,
                            formatter: function () {
                                return '<div class="d-inline-flex gap-1">'
                                    + '<button type="button" class="btn btn-success btn-sm spp-act" data-act="add" title="Add stock"><i class="bi bi-plus-lg"></i></button>'
                                    + '<button type="button" class="btn btn-warning btn-sm spp-act fw-bold" data-act="cr" title="CR - request from supplier">CR</button>'
                                    + '<button type="button" class="btn btn-primary btn-sm spp-act" data-act="edit" title="Edit"><i class="bi bi-pencil-square"></i></button>'
                                    + '<button type="button" class="btn btn-danger btn-sm spp-act" data-act="trash" title="Trash"><i class="bi bi-trash"></i></button>'
                                    + '</div>';
                            },
                            cellClick: function (e, cell) {
                                const b = e.target.closest('.spp-act');
                                if (!b) return;
                                const act = b.getAttribute('data-act');
                                const row = cell.getRow().getData();
                                if (act === 'add') openQty(row);
                                else if (act === 'cr') openCr(row);
                                else if (act === 'edit') openEdit(row);
                                else if (act === 'trash') trashRow(row);
                            },
                        },
                    ],
                });
                table.on('dataLoaded', function (data) { el('spp-count').textContent = data.length; });

                el('spp-search').addEventListener('input', function () {
                    const term = this.value.trim().toLowerCase();
                    if (!term) { table.clearFilter(); return; }
                    table.setFilter(function (r) {
                        return [r.name, r.description, r.parent, r.sku, r.supplier].join(' ').toLowerCase().indexOf(term) !== -1;
                    });
                });

                el('spp-add').addEventListener('click', async function () {
                    resetForm();
                    bootstrap.Modal.getOrCreateInstance(el('spp-modal')).show();
                    try { await loadOptions(); } catch (e) { showError('Could not load the SKU / Parent / Supplier lists.'); }
                });
                el('spp-save').addEventListener('click', save);

                // Picking a SKU fills its Parent and Supplier.
                combo('sku', function () {
                    return SKUS.map(function (s) { return { value: s.sku, hint: s.parent }; });
                }, function (it) {
                    const hit = SKUS.find(function (s) { return s.sku === it.value; });
                    if (!hit) return;
                    el('spp-parent').value = hit.parent || '';
                    el('spp-supplier').value = hit.supplier || '';
                });
                combo('parent', function () { return PARENTS.map(function (p) { return { value: p }; }); });
                combo('supplier', function () { return SUPPLIERS.map(function (p) { return { value: p }; }); });
                // Typing an exact SKU (without clicking) also autofills.
                el('spp-sku').addEventListener('change', function () {
                    const v = this.value.trim().toLowerCase();
                    const hit = SKUS.find(function (s) { return s.sku.toLowerCase() === v; });
                    if (!hit) return;
                    el('spp-sku').value = hit.sku;
                    el('spp-parent').value = hit.parent || '';
                    el('spp-supplier').value = hit.supplier || '';
                });
                el('spp-image').addEventListener('change', function () {
                    const f = this.files[0], img = el('spp-image-preview');
                    if (!f) { img.style.display = 'none'; return; }
                    img.src = URL.createObjectURL(f);
                    img.style.display = 'block';
                });
            });
        })();
    </script>
@endsection
