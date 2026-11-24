@extends('layouts.vertical', ['title' => 'Purchase Specific'])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .ps-summary { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .ps-badge {
            flex: 1 1 0; min-width: 150px; display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 14px; border-radius: 10px; color: #fff; font-size: 0.95rem; box-shadow: 0 1px 3px rgba(0,0,0,.12);
        }
        .ps-badge .label { font-weight: 500; opacity: .95; }
        .ps-badge .value { font-weight: 700; font-size: 1.15rem; }
        .ps-badge--specs { background: #2563eb; }
        .ps-badge--selected { background: #7c3aed; }
        .ps-badge--uses { background: #059669; }
        .ps-badge--normalize { background: #d97706; }

        .ps-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 10px; }
        .ps-toolbar .ps-search { max-width: 280px; }

        #ps-table .tabulator-header { background: #D8F3F3; border-bottom: 1px solid #403f3f; }
        #ps-table .tabulator-header .tabulator-col { background: #3bc0c3; color: #fff; font-weight: 700; border-right: 1px solid #fff; }
        #ps-table .tabulator-header .tabulator-col .tabulator-col-content { padding: 10px 8px; }
        #ps-table .tabulator-row:nth-child(even) { background-color: #f8fafc; }
        #ps-table .tabulator-row:hover { background-color: #dbeafe; }
        #ps-table .tabulator-row.tabulator-selected { background-color: #ede9fe !important; }
        #ps-table .tabulator-cell { padding: 7px 8px; font-size: 0.9rem; }

        .ps-chip {
            display: inline-block; padding: 1px 8px; margin: 1px 2px; border-radius: 999px; font-size: .75rem;
            background: #fef3c7; color: #92400e; border: 1px solid #fde68a;
        }
        .ps-chip--pick { cursor: pointer; background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; font-size: .82rem; padding: 3px 10px; }
        .ps-chip--pick:hover { background: #ddd6fe; }
        .ps-ok { color: #94a3b8; }
        .ps-edit-btn { border: 0; background: #e0f2fe; color: #0369a1; border-radius: 6px; padding: 3px 9px; }
        .ps-edit-btn:hover { background: #bae6fd; }
        .ps-preview { font-size: .85rem; color: #475569; margin-top: 6px; }
        .ps-preview strong { color: #0f172a; }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Purchase Specific', 'sub_title' => 'Purchase'])

    <div class="row">
        <div class="col-12">
            <div class="ps-summary" aria-label="Summary">
                <span class="ps-badge ps-badge--specs" title="Unique specifications in the current view">
                    <span class="label">📋 Specifics</span><span class="value" id="ps-total-specs">0</span>
                </span>
                <span class="ps-badge ps-badge--selected" title="Selected rows">
                    <span class="label">☑️ Selected</span><span class="value" id="ps-total-selected">0</span>
                </span>
                <span class="ps-badge ps-badge--uses" title="Sum of forms using these specifications">
                    <span class="label">🧾 Total uses</span><span class="value" id="ps-total-uses">0</span>
                </span>
                <span class="ps-badge ps-badge--normalize" title="Specifications stored with a different spelling than shown">
                    <span class="label">✨ To normalize</span><span class="value" id="ps-total-normalize">0</span>
                </span>
            </div>
        </div>

        <div class="col-12">
            <div id="ps-flash" class="alert d-none py-2" role="alert"></div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="ps-toolbar">
                        <input type="text" id="ps-search" class="form-control form-control-sm border-primary ps-search" placeholder="Search specifics…" autocomplete="off">
                        <button type="button" id="ps-merge-btn" class="btn btn-sm btn-primary" disabled>
                            <i class="fas fa-object-group"></i> Merge <span id="ps-merge-count"></span>
                        </button>
                        <button type="button" id="ps-normalize-btn" class="btn btn-sm btn-warning" title="Save the normalized spelling into every Purchase Comparison form">
                            <i class="fas fa-wand-magic-sparkles"></i> Normalize all
                        </button>
                        <button type="button" id="ps-refresh-btn" class="btn btn-sm btn-outline-secondary" title="Re-scan all forms">
                            <i class="fas fa-rotate"></i> Refresh
                        </button>
                        <span id="ps-loading" class="text-muted small d-none"><i class="fas fa-spinner fa-spin"></i> Scanning forms…</span>
                    </div>
                    <div id="ps-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit modal --}}
    <div class="modal fade" id="psEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit specification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="ps-edit-input">Specification name</label>
                    <input type="text" id="ps-edit-input" class="form-control" maxlength="120" autocomplete="off">
                    <div class="ps-preview">Will be saved as: <strong id="ps-edit-preview">—</strong></div>
                    <div class="ps-preview" id="ps-edit-info"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="ps-edit-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Merge modal --}}
    <div class="modal fade" id="psMergeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Merge into a new specification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2 small text-muted">Selected specifications (click one to use its name):</div>
                    <div id="ps-merge-list" class="mb-3"></div>
                    <label class="form-label" for="ps-merge-input">New specification name</label>
                    <input type="text" id="ps-merge-input" class="form-control" maxlength="120" autocomplete="off">
                    <div class="ps-preview">Will be saved as: <strong id="ps-merge-preview">—</strong></div>
                    <div class="ps-preview">Every Purchase Comparison form using any of the selected names will be updated.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="ps-merge-save">Merge</button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const CSRF = '{{ csrf_token() }}';
            const URLS = {
                data: @json(route('purchase.specific.data')),
                rename: @json(route('purchase.specific.rename')),
                merge: @json(route('purchase.specific.merge')),
                normalizeAll: @json(route('purchase.specific.normalize-all')),
            };

            let ACRONYMS = new Set();
            let table = null;
            let editKey = null;

            const $ = (id) => document.getElementById(id);
            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

            // Mirror of PurchaseSpecificController::normalizeLabel() (sentence case, e.g. "apple" -> "Apple").
            function normalizeLabel(label) {
                let s = String(label || '').replace(/\s+/g, ' ').trim().replace(/\s*\/\s*/g, '/').replace(/[\s:;,.]+$/, '');
                if (!s) return '';
                s = s.replace(/[\p{L}\p{N}']+/gu, (w) => {
                    const lower = w.toLowerCase();
                    return ACRONYMS.has(lower) ? lower.toUpperCase() : lower;
                });
                return s.charAt(0).toUpperCase() + s.slice(1);
            }

            function flash(type, message) {
                const el = $('ps-flash');
                el.className = 'alert alert-' + type + ' py-2';
                el.textContent = message;
                clearTimeout(flash._t);
                flash._t = setTimeout(() => el.classList.add('d-none'), 7000);
            }

            async function post(url, body) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
                    body: JSON.stringify(body || {}),
                });
                let json = {};
                try { json = await res.json(); } catch (e) {}
                if (!res.ok || json.success === false) {
                    throw new Error(json.message || (json.errors ? Object.values(json.errors).flat().join(' ') : 'Request failed (' + res.status + ').'));
                }
                return json;
            }

            function updateBadges() {
                if (!table) return;
                const rows = table.getData('active');
                $('ps-total-specs').textContent = rows.length.toLocaleString();
                $('ps-total-uses').textContent = rows.reduce((sum, r) => sum + (Number(r.count) || 0), 0).toLocaleString();
                $('ps-total-normalize').textContent = rows.filter((r) => r.needs_normalize).length.toLocaleString();
                const selected = table.getSelectedData().length;
                $('ps-total-selected').textContent = selected.toLocaleString();
                $('ps-merge-btn').disabled = selected < 2;
                $('ps-merge-count').textContent = selected >= 2 ? '(' + selected + ')' : '';
            }

            function buildTable() {
                table = new Tabulator('#ps-table', {
                    data: [],
                    height: '68vh',
                    layout: 'fitColumns',
                    placeholder: 'No specifications found',
                    index: 'key',
                    selectableRows: true,
                    rowHeader: {
                        formatter: 'rowSelection', titleFormatter: 'rowSelection', headerSort: false, resizable: false,
                        frozen: true, headerHozAlign: 'center', hozAlign: 'center', width: 44,
                    },
                    initialSort: [{column: 'name', dir: 'asc'}],
                    columns: [
                        {title: 'PC specifics', field: 'name', sorter: 'string', widthGrow: 3, minWidth: 220,
                            sorterParams: {locale: true},
                            formatter: (cell) => '<strong>' + esc(cell.getValue()) + '</strong>'},
                        {title: 'Used in forms', field: 'count', sorter: 'number', hozAlign: 'center', width: 140},
                        {title: 'Other spellings found', field: 'variants', headerSort: false, widthGrow: 3, minWidth: 220,
                            formatter: (cell) => {
                                const row = cell.getRow().getData();
                                const others = (row.variants || []).filter((v) => v !== row.name);
                                if (!others.length) return '<span class="ps-ok">—</span>';
                                return others.map((v) => '<span class="ps-chip">' + esc(v) + '</span>').join('');
                            }},
                        {title: 'Edit', field: 'key', headerSort: false, hozAlign: 'center', width: 90,
                            formatter: () => '<button type="button" class="ps-edit-btn" title="Edit"><i class="fas fa-pen"></i></button>',
                            cellClick: (e, cell) => { e.stopPropagation(); openEdit(cell.getRow().getData()); }},
                    ],
                });
                table.on('rowSelectionChanged', updateBadges);
                table.on('dataFiltered', updateBadges);
                table.on('dataLoaded', updateBadges);
            }

            async function load(fresh) {
                $('ps-loading').classList.remove('d-none');
                try {
                    const res = await fetch(URLS.data + (fresh ? '?fresh=1' : ''), {headers: {'Accept': 'application/json'}});
                    const json = await res.json();
                    if (!res.ok || !json.success) throw new Error(json.message || 'Could not load specifications.');
                    ACRONYMS = new Set(json.acronyms || []);
                    await table.setData(json.rows || []);
                    table.setSort([{column: 'name', dir: 'asc'}]);
                    applySearch();
                    updateBadges();
                } catch (err) {
                    flash('danger', err.message);
                } finally {
                    $('ps-loading').classList.add('d-none');
                }
            }

            function applySearch() {
                const q = $('ps-search').value.trim().toLowerCase();
                if (!q) { table.clearFilter(); return; }
                table.setFilter((row) => String(row.name).toLowerCase().includes(q)
                    || (row.variants || []).some((v) => String(v).toLowerCase().includes(q)));
            }

            // ---------------------------------------------------------------- edit
            function openEdit(row) {
                editKey = row.key;
                $('ps-edit-input').value = row.name;
                $('ps-edit-preview').textContent = row.name;
                $('ps-edit-info').textContent = 'Used in ' + row.count + ' form(s). Renaming updates all of them.';
                bootstrap.Modal.getOrCreateInstance($('psEditModal')).show();
                setTimeout(() => $('ps-edit-input').select(), 250);
            }

            $('ps-edit-input').addEventListener('input', (e) => {
                $('ps-edit-preview').textContent = normalizeLabel(e.target.value) || '—';
            });
            $('ps-edit-input').addEventListener('keydown', (e) => { if (e.key === 'Enter') $('ps-edit-save').click(); });

            $('ps-edit-save').addEventListener('click', async () => {
                const name = normalizeLabel($('ps-edit-input').value);
                if (!name) { flash('warning', 'Name cannot be blank.'); return; }
                const btn = $('ps-edit-save');
                btn.disabled = true;
                try {
                    const json = await post(URLS.rename, {key: editKey, new_name: name});
                    bootstrap.Modal.getOrCreateInstance($('psEditModal')).hide();
                    flash('success', 'Saved "' + json.name + '" — ' + (json.updated_cells || 0) + ' cell(s) in ' + (json.updated_sheets || 0) + ' form(s) updated.');
                    await load(true);
                } catch (err) {
                    flash('danger', err.message);
                } finally {
                    btn.disabled = false;
                }
            });

            // ---------------------------------------------------------------- merge
            function openMerge() {
                const rows = table.getSelectedData();
                if (rows.length < 2) return;
                const best = rows.slice().sort((a, b) => (b.count - a.count) || a.name.localeCompare(b.name))[0];
                $('ps-merge-list').innerHTML = rows
                    .slice().sort((a, b) => a.name.localeCompare(b.name))
                    .map((r) => '<span class="ps-chip ps-chip--pick" data-name="' + esc(r.name) + '">' + esc(r.name) + ' · ' + r.count + '</span>').join('');
                $('ps-merge-input').value = best.name;
                $('ps-merge-preview').textContent = best.name;
                bootstrap.Modal.getOrCreateInstance($('psMergeModal')).show();
                setTimeout(() => $('ps-merge-input').select(), 250);
            }

            $('ps-merge-btn').addEventListener('click', openMerge);
            $('ps-merge-list').addEventListener('click', (e) => {
                const chip = e.target.closest('.ps-chip--pick');
                if (!chip) return;
                $('ps-merge-input').value = chip.dataset.name;
                $('ps-merge-preview').textContent = chip.dataset.name;
            });
            $('ps-merge-input').addEventListener('input', (e) => {
                $('ps-merge-preview').textContent = normalizeLabel(e.target.value) || '—';
            });
            $('ps-merge-input').addEventListener('keydown', (e) => { if (e.key === 'Enter') $('ps-merge-save').click(); });

            $('ps-merge-save').addEventListener('click', async () => {
                const name = normalizeLabel($('ps-merge-input').value);
                if (!name) { flash('warning', 'Enter the new specification name.'); return; }
                const keys = table.getSelectedData().map((r) => r.key);
                const btn = $('ps-merge-save');
                btn.disabled = true;
                try {
                    const json = await post(URLS.merge, {keys, new_name: name});
                    bootstrap.Modal.getOrCreateInstance($('psMergeModal')).hide();
                    flash('success', 'Merged ' + keys.length + ' specifications into "' + json.name + '" — ' + (json.updated_cells || 0) + ' cell(s) in ' + (json.updated_sheets || 0) + ' form(s) updated.');
                    await load(true);
                } catch (err) {
                    flash('danger', err.message);
                } finally {
                    btn.disabled = false;
                }
            });

            // ---------------------------------------------------------------- normalize all / refresh / search
            $('ps-normalize-btn').addEventListener('click', async () => {
                const n = table.getData().filter((r) => r.needs_normalize).length;
                if (!n) { flash('info', 'Everything is already normalized.'); return; }
                if (!confirm('Rewrite ' + n + ' specification(s) with their normalized spelling in every Purchase Comparison form?')) return;
                const btn = $('ps-normalize-btn');
                btn.disabled = true;
                $('ps-loading').classList.remove('d-none');
                try {
                    const json = await post(URLS.normalizeAll, {});
                    flash('success', 'Normalized ' + (json.updated_cells || 0) + ' cell(s) in ' + (json.updated_sheets || 0) + ' form(s).');
                    await load(true);
                } catch (err) {
                    flash('danger', err.message);
                } finally {
                    btn.disabled = false;
                    $('ps-loading').classList.add('d-none');
                }
            });

            $('ps-refresh-btn').addEventListener('click', () => load(true));
            $('ps-search').addEventListener('input', applySearch);

            buildTable();
            table.on('tableBuilt', () => load(false));
        });
    </script>
@endsection
