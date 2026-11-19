@extends('layouts.vertical', ['title' => 'Ebay 2 Variation Type', 'skipHighcharts' => true])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #e2vt-wrap { display: flex; flex-direction: column; min-height: 280px; overflow: hidden; }
        #e2vt-table { flex: 1 1 auto; min-height: 0; width: 100%; }
        #e2vt-wrap .tabulator { border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px; height: 100% !important; }
        #e2vt-wrap .tabulator .tabulator-header { background: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        #e2vt-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #e2vt-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            white-space: normal !important; font-size: 12.5px; font-weight: 600; text-align: center; padding: 5px 2px;
        }
        #e2vt-wrap .tabulator .tabulator-row { min-height: 34px; }
        #e2vt-wrap .tabulator .tabulator-row .tabulator-cell { padding: 4px 6px !important; white-space: normal; }
        #e2vt-wrap .tabulator .tabulator-footer { background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; }
        #e2vt-wrap .tabulator .tabulator-col.e2vt-group-values { background: #eef2ff; }
        #e2vt-wrap .tabulator .tabulator-col.e2vt-group-type { background: #f0fdf4; }

        .e2vt-stat { display: inline-flex; align-items: center; color: #fff; font-size: 13px; font-weight: 700; padding: 7px 12px; border-radius: 8px; white-space: nowrap; }
        .e2vt-stat > span { margin-left: 4px; font-size: 14px; font-weight: 800; }
        .e2vt-stat--total { background: #4c7ed8; }
        .e2vt-stat--imported { background: #8b5cf6; }
        .e2vt-stat--success { background: #16a34a; }
        .e2vt-stat--failed { background: #dc2626; }

        .e2vt-type-pill { display: inline-block; padding: 1px 8px; border-radius: 10px; background: #e0e7ff; color: #3730a3; font-weight: 600; }
        .e2vt-type-pill--mismatch { background: #fee2e2; color: #b91c1c; box-shadow: inset 0 0 0 1px #f87171; }
        .e2vt-muted { color: #94a3b8; }
        .e2vt-edit-cell { cursor: text; display: flex; align-items: center; gap: 6px; min-height: 22px; }
        .e2vt-edit-cell i { color: #94a3b8; font-size: 13px; }
        .e2vt-changed { background: #fef9c3; }

        .e2vt-dot { display: inline-block; width: 14px; height: 14px; border-radius: 50%; cursor: help; vertical-align: middle; }
        .e2vt-dot--success { background: #16a34a; box-shadow: 0 0 0 3px rgba(22, 163, 74, .18); }
        .e2vt-dot--failed { background: #dc2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, .18); }
        .e2vt-dot--none { background: #cbd5e1; width: 10px; height: 10px; }

        .e2vt-chips { display: flex; flex-wrap: wrap; gap: 3px; line-height: 1.3; }
        .e2vt-chip { display: inline-block; padding: 0 7px; border-radius: 9px; background: #f1f5f9; color: #334155; font-size: 12px; border: 1px solid #e2e8f0; }
        .e2vt-chip--edit { background: #fef9c3; border-color: #facc15; color: #713f12; font-weight: 600; }
        .e2vt-chip--more { background: transparent; border-style: dashed; color: #64748b; }
        .e2vt-chip--name { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; font-weight: 600; }
        .e2vt-chip--none { background: transparent; color: #94a3b8; }
        .e2vt-chip--missing { background: #fef2f2; border-style: dashed; border-color: #f87171; color: #b91c1c; }
        .e2vt-aspect-label { font-size: 11px; font-weight: 700; color: #4338ca; text-transform: uppercase; letter-spacing: .02em; margin-right: 4px; }
        .e2vt-aspect-block + .e2vt-aspect-block { margin-top: 4px; }

        #e2vt-modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .5); z-index: 2000; display: none; align-items: center; justify-content: center; padding: 16px; }
        #e2vt-modal-backdrop.is-open { display: flex; }
        #e2vt-modal { background: #fff; border-radius: 10px; width: 640px; max-width: 100%; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 50px rgba(0, 0, 0, .3); }
        #e2vt-modal .e2vt-m-head { padding: 14px 18px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        #e2vt-modal .e2vt-m-body { padding: 14px 18px; overflow-y: auto; }
        #e2vt-modal .e2vt-m-foot { padding: 12px 18px; border-top: 1px solid #e2e8f0; display: flex; gap: 8px; justify-content: flex-end; align-items: center; flex-wrap: wrap; }
        #e2vt-modal h6 { margin: 0; font-weight: 700; }
        .e2vt-m-aspect { font-weight: 700; color: #4338ca; margin: 10px 0 6px; }
        .e2vt-m-row { display: grid; grid-template-columns: 1fr 24px 1fr; align-items: center; gap: 6px; margin-bottom: 5px; }
        .e2vt-m-old { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 9px; font-size: 13px; overflow-wrap: anywhere; }
        .e2vt-m-arrow { text-align: center; color: #94a3b8; }
        .e2vt-m-row input.is-edited { background: #fef9c3; border-color: #facc15; }
        .e2vt-m-row input.is-dup { background: #fee2e2; border-color: #dc2626; }
        #e2vt-m-error { color: #b91c1c; font-size: 12.5px; margin-right: auto; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Ebay 2 Variation Type',
        'sub_title'  => 'eBay 2 Listings',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="e2vt-stat e2vt-stat--total" title="Live eBay 2 parents (main listings with variations). While a search or filter is on it reads shown / total.">PARENTS:<span id="e2vt-b-total">0</span></span>
                        <span class="e2vt-stat e2vt-stat--imported" title="Listings whose variation type and values were imported from eBay">IMPORTED:<span id="e2vt-b-imported">0</span></span>
                        <span class="e2vt-stat e2vt-stat--success" title="Type pushes that succeeded">TYPE OK:<span id="e2vt-b-success">0</span></span>
                        <span class="e2vt-stat e2vt-stat--failed" title="Type pushes that failed">TYPE FAILED:<span id="e2vt-b-failed">0</span></span>
                        <span class="e2vt-stat e2vt-stat--success" title="Value pushes that succeeded">VALUES OK:<span id="e2vt-b-vsuccess">0</span></span>
                        <span class="e2vt-stat e2vt-stat--failed" title="Value pushes that failed">VALUES FAILED:<span id="e2vt-b-vfailed">0</span></span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <button type="button" id="e2vt-refresh" class="btn btn-sm btn-outline-primary" title="Reload the table"><i class="ri-refresh-line"></i></button>
                        <button type="button" id="e2vt-pull" class="btn btn-sm btn-primary" title="Pull the current variation type and values from eBay 2 for the rows ticked in the Selection Box column (Trading API GetItem). Nothing is changed on eBay.">
                            <i class="ri-download-2-line me-1"></i> Pull <span id="e2vt-pull-count">(0)</span>
                        </button>
                        <button type="button" id="e2vt-import-all" class="btn btn-sm btn-warning text-dark" title="Read the current variation type and values of every listing in the filtered view from eBay 2 (Trading API GetItem)">
                            <i class="ri-download-cloud-2-line me-1"></i> Import Current Types
                        </button>
                        <button type="button" id="e2vt-push-changed" class="btn btn-sm btn-success" title="Push every listing whose New Type differs from the current type">
                            <i class="ri-upload-cloud-2-line me-1"></i> Push All Changed Types
                        </button>
                        <button type="button" id="e2vt-push-values-all" class="btn btn-sm btn-success" title="Push every listing that has value edits waiting">
                            <i class="ri-upload-cloud-2-line me-1"></i> Push All Value Edits
                        </button>
                        <button type="button" id="e2vt-stop" class="btn btn-sm btn-outline-danger d-none">Stop</button>
                        <span class="text-muted small" id="e2vt-progress"></span>
                    </div>

                    <div class="d-flex flex-wrap align-items-end gap-3 mb-2 p-2 bg-light border rounded">
                        <div style="min-width:220px">
                            <label class="form-label small fw-semibold mb-1" for="e2vt-search-parent">Search Parent</label>
                            <input type="search" id="e2vt-search-parent" class="form-control form-control-sm" placeholder="Parent or item ID..." autocomplete="off" maxlength="100">
                        </div>
                        <div style="min-width:220px">
                            <label class="form-label small fw-semibold mb-1" for="e2vt-search-sku">Search SKU</label>
                            <input type="search" id="e2vt-search-sku" class="form-control form-control-sm" placeholder="Child SKU..." autocomplete="off" maxlength="100">
                        </div>
                        <div>
                            <label class="form-label small fw-semibold mb-1" for="e2vt-type-filter">Current Type</label>
                            <select id="e2vt-type-filter" class="form-select form-select-sm" style="min-width:190px" title="Red = the type on eBay differs from the Variation Master type">
                                <option value="all">All</option>
                                <option value="red">Red only (not matching master)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small fw-semibold mb-1" for="e2vt-filter">Show</label>
                            <select id="e2vt-filter" class="form-select form-select-sm" style="min-width:200px">
                                <option value="all">All</option>
                                <option value="not_imported">Not imported yet</option>
                                <option value="changed">Type changed (New Type differs)</option>
                                <option value="values_changed">Value edits waiting</option>
                                <option value="differs_master">Differs from Variation Master</option>
                                <option value="failed">Any push failed</option>
                                <option value="success">Any push succeeded</option>
                            </select>
                        </div>
                        <div class="ms-auto small text-muted">
                            Edit <b>New Type</b> in its cell. Use <b>Edit</b> in the values column to rename individual values (for example a colour). Then press <b>Push</b>.
                            A <span class="e2vt-dot e2vt-dot--success" style="width:10px;height:10px"></span> green dot is success, a
                            <span class="e2vt-dot e2vt-dot--failed" style="width:10px;height:10px"></span> red dot is a failure: hover it for the reason.
                        </div>
                    </div>

                    <div id="e2vt-wrap">
                        <div id="e2vt-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="e2vt-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="e2vt-m-title">
        <div id="e2vt-modal">
            <div class="e2vt-m-head">
                <h6 id="e2vt-m-title">Edit values</h6>
                <button type="button" class="btn-close" id="e2vt-m-close" aria-label="Close"></button>
            </div>
            <div class="e2vt-m-body">
                <div class="small text-muted mb-1">Change the name of a value. Every variation that uses it is updated on eBay, with its price, quantity and pictures kept. Leave a box unchanged to keep that value.</div>
                <div id="e2vt-m-fields"></div>
            </div>
            <div class="e2vt-m-foot">
                <span id="e2vt-m-error"></span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="e2vt-m-cancel">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="e2vt-m-save">Save edits</button>
                <button type="button" class="btn btn-sm btn-success" id="e2vt-m-savepush"><i class="ri-upload-2-line me-1"></i>Save &amp; Push</button>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            const URLS = {
                data: @json(route('ebay2.variation.type.data')),
                import: @json(route('ebay2.variation.type.import')),
                save: @json(route('ebay2.variation.type.save')),
                push: @json(route('ebay2.variation.type.push')),
                saveValues: @json(route('ebay2.variation.type.save.values')),
                pushValues: @json(route('ebay2.variation.type.push.values')),
            };
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const IMPORT_BATCH = 10;
            const CHIPS_SHOWN = 8;
            const SKUS_SHOWN = 6;
            let table = null;
            let busy = false;
            let stopRequested = false;
            let filterMode = 'all';
            let typeFilter = 'all';
            let parentSearch = '';
            let skuSearch = '';
            let modalItemId = null;

            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const norm = (v) => String(v ?? '').trim().toLowerCase();
            const valuesOf = (d) => (d.current_values && typeof d.current_values === 'object') ? d.current_values : {};
            const editsOf = (d) => Array.isArray(d.value_edits) ? d.value_edits : [];
            const hasValues = (d) => Object.keys(valuesOf(d)).length > 0;

            async function post(url, body) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(body),
                });
                let json = null;
                try { json = await res.json(); } catch (_) { /* not JSON */ }
                if (!res.ok) {
                    const msg = json && (json.message || (json.errors && Object.values(json.errors).flat().join(' ')))
                        || ('Request failed (HTTP ' + res.status + ')');
                    throw new Error(msg);
                }
                return json;
            }

            const isChanged = (d) => norm(d.new_type) !== '' && norm(d.new_type) !== norm(d.current_type);

            // The red Current Type pill: eBay's type differs from the Variation Master type (both known).
            const isTypeMismatch = (d) => norm(d.current_type) !== '' && norm(d.master_type) !== '' && norm(d.master_type) !== norm(d.current_type);

            // Green / red dot, the reason (or confirmation) shows on hover.
            function dot(status, message, when, fallbackNone) {
                if (status === 'success' || status === 'failed') {
                    const label = status === 'success' ? 'Success' : 'Failed';
                    const tip = label + (when ? ' (' + when + ')' : '') + (message ? '\n' + message : '');
                    return '<span class="e2vt-dot e2vt-dot--' + status + '" title="' + esc(tip).replace(/\n/g, '&#10;') + '"></span>';
                }
                return '<span class="e2vt-dot e2vt-dot--none" title="' + esc(fallbackNone) + '"></span>';
            }

            function setBusy(state, message) {
                busy = state;
                document.querySelectorAll('#e2vt-pull, #e2vt-import-all, #e2vt-push-changed, #e2vt-push-values-all, #e2vt-refresh').forEach((b) => { b.disabled = state; });
                document.getElementById('e2vt-stop').classList.toggle('d-none', !state);
                document.getElementById('e2vt-progress').textContent = message || '';
            }

            let parentTotal = 0;
            let parentShown = null; // rows passing the search / filters, set by the table's dataFiltered event
            // PARENTS badge: the total, or "shown / total" while a search or filter hides some parents.
            function showParentCount() {
                const shown = parentShown === null ? parentTotal : parentShown;
                document.getElementById('e2vt-b-total').textContent = shown === parentTotal ? parentTotal : shown + ' / ' + parentTotal;
            }

            function updateBadges(meta) {
                if (!meta) return;
                parentTotal = meta.total ?? 0;
                showParentCount();
                document.getElementById('e2vt-b-imported').textContent = meta.imported ?? 0;
                document.getElementById('e2vt-b-success').textContent = meta.success ?? 0;
                document.getElementById('e2vt-b-failed').textContent = meta.failed ?? 0;
                document.getElementById('e2vt-b-vsuccess').textContent = meta.values_success ?? 0;
                document.getElementById('e2vt-b-vfailed').textContent = meta.values_failed ?? 0;
            }

            function recountBadges() {
                const rows = table.getData();
                updateBadges({
                    total: rows.length,
                    imported: rows.filter((r) => r.current_type || hasValues(r)).length,
                    success: rows.filter((r) => r.push_status === 'success').length,
                    failed: rows.filter((r) => r.push_status === 'failed').length,
                    values_success: rows.filter((r) => r.values_status === 'success').length,
                    values_failed: rows.filter((r) => r.values_status === 'failed').length,
                });
            }

            // Cells that depend on several fields are re-rendered after any update.
            function applyRowUpdates(updates) {
                const list = (updates || []).filter(Boolean);
                return Promise.resolve(table.updateData(list)).then(() => {
                    list.forEach((u) => { const r = table.getRow(u.item_id); if (r) r.reformat(); });
                });
            }

            function applyFilters() {
                const filters = [];
                if (parentSearch) {
                    filters.push((d) => norm(d.main_listing).includes(parentSearch) || norm(d.item_id).includes(parentSearch));
                }
                if (skuSearch) {
                    // sku_search is the lower-cased child SKUs of the eBay listing and of Variation Master.
                    filters.push((d) => String(d.sku_search || '').includes(skuSearch));
                }
                if (filterMode === 'not_imported') filters.push((d) => !d.current_type && !hasValues(d));
                if (filterMode === 'changed') filters.push((d) => isChanged(d));
                if (filterMode === 'values_changed') filters.push((d) => editsOf(d).length > 0);
                if (filterMode === 'differs_master' || typeFilter === 'red') filters.push(isTypeMismatch);
                if (filterMode === 'failed') filters.push((d) => d.push_status === 'failed' || d.values_status === 'failed');
                if (filterMode === 'success') filters.push((d) => d.push_status === 'success' || d.values_status === 'success');
                table.clearFilter();
                filters.forEach((fn) => table.addFilter(fn));
            }

            async function importRows(rows) {
                const ids = rows.map((r) => r.item_id);
                let done = 0;
                for (let i = 0; i < ids.length; i += IMPORT_BATCH) {
                    if (stopRequested) break;
                    const batch = ids.slice(i, i + IMPORT_BATCH);
                    document.getElementById('e2vt-progress').textContent = 'Importing ' + Math.min(i + batch.length, ids.length) + ' / ' + ids.length + '...';
                    try {
                        const res = await post(URLS.import, { item_ids: batch });
                        await applyRowUpdates(res.results || []);
                    } catch (e) {
                        await applyRowUpdates(batch.map((id) => ({ item_id: id, import_error: e.message })));
                    }
                    done += batch.length;
                }
                recountBadges();
                return done;
            }

            async function pushType(d) {
                const newType = String(d.new_type || '').trim();
                if (!newType) {
                    await applyRowUpdates([{ item_id: d.item_id, push_status: 'failed', push_message: 'Enter the new variation type first.' }]);
                    return;
                }
                try {
                    const res = await post(URLS.push, { item_id: d.item_id, new_type: newType });
                    await applyRowUpdates([res.row]);
                } catch (e) {
                    await applyRowUpdates([{ item_id: d.item_id, push_status: 'failed', push_message: e.message }]);
                }
                recountBadges();
            }

            async function pushValues(d) {
                try {
                    const res = await post(URLS.pushValues, { item_id: d.item_id });
                    await applyRowUpdates([res.row]);
                } catch (e) {
                    await applyRowUpdates([{ item_id: d.item_id, values_status: 'failed', values_message: e.message }]);
                }
                recountBadges();
            }

            function visibleRows() {
                return table.getRows('active').map((r) => r.getData());
            }

            // Selection Box column: keep the header tick box (select all in the current view) and the Pull count in step.
            function syncSelection() {
                if (!table) return;
                const selected = table.getSelectedRows();
                document.getElementById('e2vt-pull-count').textContent = '(' + selected.length + ')';
                const box = document.querySelector('.e2vt-sel-all');
                if (!box) return;
                const active = table.getRows('active');
                const picked = active.filter((r) => r.isSelected()).length;
                box.checked = active.length > 0 && picked === active.length;
                box.indeterminate = picked > 0 && picked < active.length;
            }

            function fitHeight() {
                const wrap = document.getElementById('e2vt-wrap');
                const top = wrap.getBoundingClientRect().top;
                wrap.style.height = Math.max(320, Math.floor(window.innerHeight - top - 12)) + 'px';
                if (table) table.setHeight('100%');
            }

            // ---------- values editor modal ----------
            function openModal(d) {
                modalItemId = d.item_id;
                const values = valuesOf(d);
                const edits = editsOf(d);
                document.getElementById('e2vt-m-title').textContent = 'Edit values: ' + d.main_listing;
                document.getElementById('e2vt-m-error').textContent = '';
                const box = document.getElementById('e2vt-m-fields');
                box.innerHTML = '';
                Object.keys(values).forEach((aspect) => {
                    const head = document.createElement('div');
                    head.className = 'e2vt-m-aspect';
                    head.textContent = aspect + ' (' + values[aspect].length + ')';
                    box.appendChild(head);
                    values[aspect].forEach((val) => {
                        const edit = edits.find((e) => e.aspect === aspect && e.old === val);
                        const row = document.createElement('div');
                        row.className = 'e2vt-m-row';
                        row.innerHTML = '<div class="e2vt-m-old"></div><div class="e2vt-m-arrow">&rarr;</div>';
                        row.firstChild.textContent = val;
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.className = 'form-control form-control-sm';
                        input.maxLength = 65;
                        input.value = edit ? edit.new : val;
                        input.dataset.aspect = aspect;
                        input.dataset.old = val;
                        input.addEventListener('input', () => { refreshModalMarks(); });
                        row.appendChild(input);
                        box.appendChild(row);
                    });
                });
                refreshModalMarks();
                document.getElementById('e2vt-modal-backdrop').classList.add('is-open');
            }

            function closeModal() {
                document.getElementById('e2vt-modal-backdrop').classList.remove('is-open');
                modalItemId = null;
            }

            // Marks edited boxes and flags two values that would end up with the same name.
            function refreshModalMarks() {
                const inputs = Array.from(document.querySelectorAll('#e2vt-m-fields input'));
                const seen = {};
                let dup = false;
                inputs.forEach((i) => { i.classList.remove('is-dup'); });
                inputs.forEach((i) => {
                    const v = i.value.trim() === '' ? i.dataset.old : i.value.trim();
                    i.classList.toggle('is-edited', v !== i.dataset.old);
                    const key = i.dataset.aspect + '\u0000' + v.toLowerCase();
                    if (seen[key]) { seen[key].classList.add('is-dup'); i.classList.add('is-dup'); dup = true; }
                    else seen[key] = i;
                });
                document.getElementById('e2vt-m-error').textContent = dup ? 'Two values would get the same name. Give each its own name.' : '';
                return !dup;
            }

            function collectEdits() {
                return Array.from(document.querySelectorAll('#e2vt-m-fields input'))
                    .map((i) => ({ aspect: i.dataset.aspect, old: i.dataset.old, new: i.value.trim() }))
                    .filter((e) => e.new !== '' && e.new !== e.old);
            }

            async function saveModal(thenPush) {
                if (!modalItemId || !refreshModalMarks()) return;
                const itemId = modalItemId;
                const edits = collectEdits();
                const saveBtn = document.getElementById('e2vt-m-save');
                const pushBtn = document.getElementById('e2vt-m-savepush');
                saveBtn.disabled = pushBtn.disabled = true;
                try {
                    const res = await post(URLS.saveValues, { item_id: itemId, edits: edits });
                    await applyRowUpdates([{ item_id: itemId, value_edits: res.value_edits || [] }]);
                    closeModal();
                    if (thenPush && (res.value_edits || []).length) {
                        setBusy(true, 'Pushing values...');
                        try { await pushValues(table.getRow(itemId).getData()); } finally { setBusy(false, ''); }
                    }
                } catch (e) {
                    document.getElementById('e2vt-m-error').textContent = 'Could not save: ' + e.message;
                } finally {
                    saveBtn.disabled = pushBtn.disabled = false;
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                fitHeight();
                window.addEventListener('resize', () => { clearTimeout(window._e2vtR); window._e2vtR = setTimeout(fitHeight, 100); });

                document.getElementById('e2vt-m-close').addEventListener('click', closeModal);
                document.getElementById('e2vt-m-cancel').addEventListener('click', closeModal);
                document.getElementById('e2vt-m-save').addEventListener('click', () => saveModal(false));
                document.getElementById('e2vt-m-savepush').addEventListener('click', () => saveModal(true));
                document.getElementById('e2vt-modal-backdrop').addEventListener('click', (e) => { if (e.target.id === 'e2vt-modal-backdrop') closeModal(); });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

                table = new Tabulator('#e2vt-table', {
                    ajaxURL: URLS.data,
                    ajaxResponse: function (url, params, response) {
                        updateBadges(response.meta);
                        setTimeout(fitHeight, 0);
                        return response.data || [];
                    },
                    ajaxError: function () {
                        document.getElementById('e2vt-progress').textContent = 'Could not load the table. If this is new, run php artisan migrate on the server.';
                    },
                    index: 'item_id',
                    height: '100%',
                    layout: 'fitDataFill',
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [25, 50, 100, 250, 500],
                    paginationCounter: 'rows',
                    placeholder: 'No live eBay 2 listings with variations found. Pull listings on the Variation Verify page first.',
                    columns: [
                        {
                            // Selection Box: tick a row, or tick the header box to select every row in the current view.
                            title: 'Selection Box', width: 92, frozen: true, hozAlign: 'center', headerHozAlign: 'center', headerSort: false, resizable: false,
                            formatter: 'rowSelection',
                            titleFormatter: function () {
                                const wrapEl = document.createElement('div');
                                wrapEl.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:2px;line-height:1.1;white-space:normal';
                                const box = document.createElement('input');
                                box.type = 'checkbox';
                                box.className = 'e2vt-sel-all';
                                box.title = 'Select / clear every row in the current view';
                                box.addEventListener('click', (e) => e.stopPropagation());
                                box.addEventListener('change', function () {
                                    const rows = table.getRows('active');
                                    if (box.checked) table.selectRow(rows); else table.deselectRow(rows);
                                });
                                const label = document.createElement('span');
                                label.textContent = 'Selection Box';
                                wrapEl.appendChild(box);
                                wrapEl.appendChild(label);
                                return wrapEl;
                            },
                        },
                        {
                            title: 'Parent', field: 'main_listing', width: 200, frozen: true, headerHozAlign: 'center',
                            formatter: (c) => '<b>' + esc(c.getValue()) + '</b>',
                        },
                        {
                            // Every child SKU of the parent: eBay listing SKUs plus Variation Master children.
                            // A dashed red chip is in Variation Master but not on the eBay listing.
                            title: 'SKU', field: 'skus', width: 280, headerHozAlign: 'center', headerSort: false,
                            formatter: function (c) {
                                const d = c.getData();
                                const skus = Array.isArray(d.skus) ? d.skus : [];
                                if (!skus.length) return '<span class="e2vt-muted">—</span>';
                                const missing = new Set(Array.isArray(d.skus_not_on_ebay) ? d.skus_not_on_ebay : []);
                                const chip = (s) => missing.has(s)
                                    ? '<span class="e2vt-chip e2vt-chip--missing" title="In Variation Master, not on the eBay listing">' + esc(s) + '</span>'
                                    : '<span class="e2vt-chip">' + esc(s) + '</span>';
                                const shown = skus.slice(0, SKUS_SHOWN).map(chip);
                                if (skus.length > SKUS_SHOWN) {
                                    shown.push('<span class="e2vt-chip e2vt-chip--more" title="' + esc(skus.slice(SKUS_SHOWN).join(', ')) + '">+' + (skus.length - SKUS_SHOWN) + ' more</span>');
                                }
                                return '<div><span class="e2vt-aspect-label">' + skus.length + ' SKU' + (skus.length === 1 ? '' : 's')
                                    + (missing.size ? ' · ' + missing.size + ' not on eBay' : '') + '</span>'
                                    + '<span class="e2vt-chips" style="display:flex;margin-top:2px">' + shown.join('') + '</span></div>';
                            },
                        },
                        {
                            title: 'Item ID', field: 'item_id', width: 130, hozAlign: 'center', headerHozAlign: 'center',
                            formatter: (c) => '<a href="https://www.ebay.com/itm/' + encodeURIComponent(c.getValue()) + '" target="_blank" rel="noopener">' + esc(c.getValue()) + '</a>',
                        },
                        { title: 'Children', field: 'children', width: 78, hozAlign: 'center', headerHozAlign: 'center' },
                        {
                            title: 'Import', width: 68, hozAlign: 'center', headerHozAlign: 'center', headerSort: false,
                            formatter: () => '<button type="button" class="btn btn-sm btn-outline-secondary e2vt-import-one" title="Import this listing\'s current type and values from eBay"><i class="ri-download-line"></i></button>',
                            cellClick: async function (e, cell) {
                                if (busy || !e.target.closest('.e2vt-import-one')) return;
                                const d = cell.getData();
                                setBusy(true, 'Importing ' + d.main_listing + '...');
                                try { await importRows([d]); } finally { setBusy(false, ''); }
                            },
                        },
                        {
                            title: 'Variation Type',
                            cssClass: '',
                            columns: [
                                {
                                    title: 'Variation Master Type', field: 'master_type', width: 140, hozAlign: 'center', headerHozAlign: 'center',
                                    formatter: (c) => c.getValue() ? '<span class="e2vt-type-pill">' + esc(c.getValue()) + '</span>' : '<span class="e2vt-muted">—</span>',
                                },
                                {
                                    title: 'Current Type (eBay)', field: 'current_type', width: 150, hozAlign: 'center', headerHozAlign: 'center',
                                    formatter: function (c) {
                                        const d = c.getData();
                                        if (d.current_type) {
                                            // Red when Variation Master has a type for this parent and eBay's differs from it.
                                            const mismatch = isTypeMismatch(d);
                                            const tip = 'Imported ' + (d.imported_at || '')
                                                + (mismatch ? '\nDoes not match Variation Master: ' + d.master_type : '');
                                            return '<span class="e2vt-type-pill' + (mismatch ? ' e2vt-type-pill--mismatch' : '') + '" title="' + esc(tip).replace(/\n/g, '&#10;') + '">' + esc(d.current_type) + '</span>';
                                        }
                                        if (d.import_error) {
                                            return '<span class="text-danger small" title="' + esc(d.import_error) + '">Import failed</span>';
                                        }
                                        return '<span class="e2vt-muted">Not imported</span>';
                                    },
                                },
                                {
                                    title: 'New Type (edit)', field: 'new_type', width: 170, headerHozAlign: 'center',
                                    editor: 'input', editorParams: { elementAttributes: { maxlength: 65 } },
                                    formatter: function (c) {
                                        const v = c.getValue();
                                        if (isChanged(c.getData())) c.getElement().classList.add('e2vt-changed'); else c.getElement().classList.remove('e2vt-changed');
                                        return '<div class="e2vt-edit-cell"><i class="ri-edit-line"></i>' + (v ? esc(v) : '<span class="e2vt-muted">click to edit</span>') + '</div>';
                                    },
                                    cellEdited: async function (cell) {
                                        const d = cell.getData();
                                        cell.getRow().reformat();
                                        try {
                                            await post(URLS.save, { item_id: d.item_id, new_type: String(d.new_type || '').trim() });
                                        } catch (e) {
                                            document.getElementById('e2vt-progress').textContent = 'Could not save edit: ' + e.message;
                                        }
                                    },
                                },
                                {
                                    title: 'Push', width: 90, hozAlign: 'center', headerHozAlign: 'center', headerSort: false,
                                    formatter: function (c) {
                                        const canPush = isChanged(c.getData());
                                        return '<button type="button" class="btn btn-sm ' + (canPush ? 'btn-success' : 'btn-outline-secondary') + ' e2vt-push-type" title="' + (canPush ? 'Push the new type to eBay 2' : 'New Type is empty or equals the current type') + '"><i class="ri-upload-2-line me-1"></i>Push</button>';
                                    },
                                    cellClick: async function (e, cell) {
                                        if (busy || !e.target.closest('.e2vt-push-type')) return;
                                        const d = cell.getData();
                                        if (!isChanged(d) && !confirm('New Type is empty or already equals the current type. Push anyway?')) return;
                                        setBusy(true, 'Pushing type for ' + d.main_listing + '...');
                                        try { await pushType(d); } finally { setBusy(false, ''); cell.getRow().reformat(); }
                                    },
                                },
                                {
                                    title: 'Reason', field: 'push_status', width: 70, hozAlign: 'center', headerHozAlign: 'center', headerSort: false,
                                    formatter: (c) => { const d = c.getData(); return dot(d.push_status, d.push_message, d.pushed_at, 'Type not pushed yet'); },
                                },
                            ],
                        },
                        {
                            title: 'Variation Values (sub-fields)',
                            columns: [
                                {
                                    title: 'Existing Values (eBay)', field: 'current_values', width: 340, headerHozAlign: 'center', headerSort: false,
                                    formatter: function (c) {
                                        const d = c.getData();
                                        const values = valuesOf(d);
                                        const aspects = Object.keys(values);
                                        if (!aspects.length) {
                                            return d.current_type
                                                ? '<span class="e2vt-muted">No values on this listing</span>'
                                                : '<span class="e2vt-muted">Import to see the values</span>';
                                        }
                                        const edits = editsOf(d);
                                        return aspects.map(function (aspect) {
                                            const list = values[aspect];
                                            const chips = list.slice(0, CHIPS_SHOWN).map(function (v) {
                                                const e = edits.find((x) => x.aspect === aspect && x.old === v);
                                                return e
                                                    ? '<span class="e2vt-chip e2vt-chip--edit" title="Will be renamed on push">' + esc(v) + ' &rarr; ' + esc(e.new) + '</span>'
                                                    : '<span class="e2vt-chip">' + esc(v) + '</span>';
                                            });
                                            // A renamed value hidden behind "+N more" still has to be visible.
                                            const hiddenEdits = list.slice(CHIPS_SHOWN).filter((v) => edits.some((x) => x.aspect === aspect && x.old === v)).length;
                                            if (list.length > CHIPS_SHOWN) {
                                                chips.push('<span class="e2vt-chip e2vt-chip--more" title="' + esc(list.slice(CHIPS_SHOWN).join(', ')) + '">+' + (list.length - CHIPS_SHOWN) + ' more' + (hiddenEdits ? ' (' + hiddenEdits + ' edited)' : '') + '</span>');
                                            }
                                            return '<div class="e2vt-aspect-block"><span class="e2vt-aspect-label">' + esc(aspect) + ' (' + list.length + ')</span><span class="e2vt-chips" style="display:inline-flex">' + chips.join('') + '</span></div>';
                                        }).join('');
                                    },
                                },
                                {
                                    // Variation Master "Variation Name" per SKU (/variation-master). One entry per SKU of the
                                    // SKU column, same order; hover an entry for its SKU. A dash means no name saved for it.
                                    title: 'Variation Name (SKU wise)', field: 'sku_names', width: 260, headerHozAlign: 'center', headerSort: false,
                                    formatter: function (c) {
                                        const d = c.getData();
                                        const skus = Array.isArray(d.skus) ? d.skus : [];
                                        const names = Array.isArray(d.sku_names) ? d.sku_names : [];
                                        if (!skus.length) return '<span class="e2vt-muted">—</span>';
                                        const filled = names.filter((n) => n).length;
                                        const chip = (i) => names[i]
                                            ? '<span class="e2vt-chip e2vt-chip--name" title="' + esc(skus[i]) + '">' + esc(names[i]) + '</span>'
                                            : '<span class="e2vt-chip e2vt-chip--none" title="' + esc(skus[i]) + ': no Variation Name in Variation Master">—</span>';
                                        const shown = skus.slice(0, SKUS_SHOWN).map((s, i) => chip(i));
                                        if (skus.length > SKUS_SHOWN) {
                                            const rest = skus.slice(SKUS_SHOWN).map((s, i) => s + ': ' + (names[SKUS_SHOWN + i] || '—')).join('\n');
                                            shown.push('<span class="e2vt-chip e2vt-chip--more" title="' + esc(rest).replace(/\n/g, '&#10;') + '">+' + (skus.length - SKUS_SHOWN) + ' more</span>');
                                        }
                                        return '<div><span class="e2vt-aspect-label">' + filled + ' / ' + skus.length + ' named</span>'
                                            + '<span class="e2vt-chips" style="display:flex;margin-top:2px">' + shown.join('') + '</span></div>';
                                    },
                                },
                                {
                                    title: 'Edit', width: 74, hozAlign: 'center', headerHozAlign: 'center', headerSort: false,
                                    formatter: function (c) {
                                        const d = c.getData();
                                        const ok = hasValues(d);
                                        const n = editsOf(d).length;
                                        return '<button type="button" class="btn btn-sm ' + (n ? 'btn-warning' : 'btn-outline-primary') + ' e2vt-edit-values"' + (ok ? '' : ' disabled') + ' title="' + (ok ? 'Rename values' : 'Import this listing first') + '"><i class="ri-edit-line"></i>' + (n ? ' ' + n : '') + '</button>';
                                    },
                                    cellClick: function (e, cell) {
                                        const btn = e.target.closest('.e2vt-edit-values');
                                        if (busy || !btn || btn.disabled) return;
                                        openModal(cell.getData());
                                    },
                                },
                                {
                                    title: 'Push', width: 90, hozAlign: 'center', headerHozAlign: 'center', headerSort: false,
                                    formatter: function (c) {
                                        const can = editsOf(c.getData()).length > 0;
                                        return '<button type="button" class="btn btn-sm ' + (can ? 'btn-success' : 'btn-outline-secondary') + ' e2vt-push-values"' + (can ? '' : ' disabled') + ' title="' + (can ? 'Push the renamed values to eBay 2' : 'No value edits waiting. Use Edit first.') + '"><i class="ri-upload-2-line me-1"></i>Push</button>';
                                    },
                                    cellClick: async function (e, cell) {
                                        const btn = e.target.closest('.e2vt-push-values');
                                        if (busy || !btn || btn.disabled) return;
                                        const d = cell.getData();
                                        setBusy(true, 'Pushing values for ' + d.main_listing + '...');
                                        try { await pushValues(d); } finally { setBusy(false, ''); cell.getRow().reformat(); }
                                    },
                                },
                                {
                                    title: 'Reason', field: 'values_status', width: 70, hozAlign: 'center', headerHozAlign: 'center', headerSort: false,
                                    formatter: (c) => { const d = c.getData(); return dot(d.values_status, d.values_message, d.values_pushed_at, 'Values not pushed yet'); },
                                },
                            ],
                        },
                    ],
                });

                table.on('dataFiltered', function (filters, rows) {
                    parentShown = rows.length;
                    showParentCount();
                    syncSelection();
                });
                table.on('rowSelectionChanged', syncSelection);
                table.on('dataLoaded', syncSelection);

                document.getElementById('e2vt-pull').addEventListener('click', async function () {
                    if (busy) return;
                    const rows = table.getSelectedRows().map((r) => r.getData());
                    if (!rows.length) { alert('Tick at least one row in the Selection Box column first.'); return; }
                    if (!confirm('Pull the current variation type and values of ' + rows.length + ' selected listing(s) from eBay 2? This calls GetItem once per listing and does not change eBay.')) return;
                    stopRequested = false;
                    setBusy(true, 'Pulling...');
                    const done = await importRows(rows);
                    setBusy(false, (stopRequested ? 'Stopped after ' : 'Pulled ') + done + ' selected listing(s).');
                    table.redraw(true);
                    syncSelection();
                });

                document.getElementById('e2vt-refresh').addEventListener('click', () => table.setData(URLS.data));
                document.getElementById('e2vt-stop').addEventListener('click', () => { stopRequested = true; });
                document.getElementById('e2vt-filter').addEventListener('change', (e) => { filterMode = e.target.value; applyFilters(); });
                document.getElementById('e2vt-type-filter').addEventListener('change', (e) => { typeFilter = e.target.value; applyFilters(); });
                let searchTimer = null;
                const debounceFilter = () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyFilters, 200); };
                document.getElementById('e2vt-search-parent').addEventListener('input', (e) => { parentSearch = norm(e.target.value); debounceFilter(); });
                document.getElementById('e2vt-search-sku').addEventListener('input', (e) => { skuSearch = norm(e.target.value); debounceFilter(); });

                document.getElementById('e2vt-import-all').addEventListener('click', async function () {
                    if (busy) return;
                    const rows = visibleRows();
                    if (!rows.length) return;
                    if (!confirm('Import the current variation type and values of ' + rows.length + ' listing(s) from eBay 2? This calls GetItem once per listing.')) return;
                    stopRequested = false;
                    setBusy(true, 'Importing...');
                    const done = await importRows(rows);
                    setBusy(false, (stopRequested ? 'Stopped after ' : 'Imported ') + done + ' listing(s).');
                    table.redraw(true);
                });

                async function pushMany(rows, fn, label) {
                    stopRequested = false;
                    setBusy(true, 'Pushing...');
                    let n = 0;
                    for (const d of rows) {
                        if (stopRequested) break;
                        n++;
                        document.getElementById('e2vt-progress').textContent = 'Pushing ' + label + ' ' + n + ' / ' + rows.length + ' (' + d.main_listing + ')...';
                        await fn(table.getRow(d.item_id).getData());
                    }
                    setBusy(false, (stopRequested ? 'Stopped after ' : 'Pushed ') + n + ' listing(s). Hover the dots for the result.');
                    table.redraw(true);
                }

                document.getElementById('e2vt-push-changed').addEventListener('click', async function () {
                    if (busy) return;
                    const rows = visibleRows().filter(isChanged);
                    if (!rows.length) { alert('Nothing to push: no listing in this view has a New Type that differs from its current type.'); return; }
                    if (!confirm('Push the new variation type to eBay 2 for ' + rows.length + ' listing(s)? This changes live listings.')) return;
                    await pushMany(rows, pushType, 'type');
                });

                document.getElementById('e2vt-push-values-all').addEventListener('click', async function () {
                    if (busy) return;
                    const rows = visibleRows().filter((d) => editsOf(d).length > 0);
                    if (!rows.length) { alert('Nothing to push: no listing in this view has value edits waiting.'); return; }
                    if (!confirm('Push renamed values to eBay 2 for ' + rows.length + ' listing(s)? This changes live listings.')) return;
                    await pushMany(rows, pushValues, 'values');
                });
            });
        })();
    </script>
@endsection
