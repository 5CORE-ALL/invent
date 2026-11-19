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
        #e2vt-wrap .tabulator .tabulator-row .tabulator-cell { padding: 4px 6px !important; }
        #e2vt-wrap .tabulator .tabulator-footer { background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; }

        .e2vt-stat { display: inline-flex; align-items: center; color: #fff; font-size: 14px; font-weight: 700; padding: 8px 14px; border-radius: 8px; white-space: nowrap; }
        .e2vt-stat > span { margin-left: 4px; font-size: 15px; font-weight: 800; }
        .e2vt-stat--total { background: #4c7ed8; }
        .e2vt-stat--imported { background: #8b5cf6; }
        .e2vt-stat--success { background: #16a34a; }
        .e2vt-stat--failed { background: #dc2626; }

        .e2vt-type-pill { display: inline-block; padding: 1px 8px; border-radius: 10px; background: #e0e7ff; color: #3730a3; font-weight: 600; }
        .e2vt-muted { color: #94a3b8; }
        .e2vt-edit-cell { cursor: text; display: flex; align-items: center; gap: 6px; min-height: 22px; }
        .e2vt-edit-cell i { color: #94a3b8; font-size: 13px; }
        .e2vt-changed { background: #fef9c3; }
        .e2vt-status { display: inline-block; padding: 2px 10px; border-radius: 10px; font-weight: 700; font-size: 12px; color: #fff; }
        .e2vt-status--success { background: #16a34a; }
        .e2vt-status--failed { background: #dc2626; }
        .e2vt-status--none { background: #cbd5e1; color: #475569; }
        .e2vt-reason { white-space: normal; line-height: 1.35; font-size: 12.5px; }
        .e2vt-reason--failed { color: #b91c1c; }
        .e2vt-reason--success { color: #15803d; }
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
                        <span class="e2vt-stat e2vt-stat--total" title="Live eBay 2 main listings with variations">LISTINGS:<span id="e2vt-b-total">0</span></span>
                        <span class="e2vt-stat e2vt-stat--imported" title="Listings whose current variation type was imported from eBay">IMPORTED:<span id="e2vt-b-imported">0</span></span>
                        <span class="e2vt-stat e2vt-stat--success" title="Last push succeeded">SUCCESS:<span id="e2vt-b-success">0</span></span>
                        <span class="e2vt-stat e2vt-stat--failed" title="Last push failed">FAILED:<span id="e2vt-b-failed">0</span></span>

                        <button type="button" id="e2vt-refresh" class="btn btn-sm btn-outline-primary" title="Reload the table"><i class="ri-refresh-line"></i></button>
                        <button type="button" id="e2vt-import-all" class="btn btn-sm btn-warning text-dark" title="Read the current variation type of every listing in the filtered view from eBay 2 (Trading API GetItem)">
                            <i class="ri-download-cloud-2-line me-1"></i> Import Current Types
                        </button>
                        <button type="button" id="e2vt-push-changed" class="btn btn-sm btn-success" title="Push every listing whose New Type differs from the current type">
                            <i class="ri-upload-cloud-2-line me-1"></i> Push All Changed
                        </button>
                        <button type="button" id="e2vt-stop" class="btn btn-sm btn-outline-danger d-none">Stop</button>
                        <span class="text-muted small" id="e2vt-progress"></span>
                    </div>

                    <div class="d-flex flex-wrap align-items-end gap-3 mb-2 p-2 bg-light border rounded">
                        <div>
                            <label class="form-label small fw-semibold mb-1" for="e2vt-filter">Show</label>
                            <select id="e2vt-filter" class="form-select form-select-sm" style="min-width:180px">
                                <option value="all">All</option>
                                <option value="not_imported">Not imported yet</option>
                                <option value="changed">Changed (New Type differs)</option>
                                <option value="differs_master">Differs from Variation Master</option>
                                <option value="failed">Failed</option>
                                <option value="success">Success</option>
                            </select>
                        </div>
                        <div class="flex-grow-1" style="max-width:360px">
                            <label class="form-label small fw-semibold mb-1" for="e2vt-search">Search</label>
                            <input type="search" id="e2vt-search" class="form-control form-control-sm" placeholder="Main listing or item ID..." autocomplete="off" maxlength="100">
                        </div>
                        <div class="ms-auto small text-muted">
                            Click a <b>New Type</b> cell to edit it, then press <b>Push</b>. eBay needs one variation aspect per listing, the new name is applied to every variation.
                        </div>
                    </div>

                    <div id="e2vt-wrap">
                        <div id="e2vt-table"></div>
                    </div>
                </div>
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
            };
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const IMPORT_BATCH = 10;
            let table = null;
            let busy = false;
            let stopRequested = false;
            let filterMode = 'all';
            let searchText = '';

            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const norm = (v) => String(v ?? '').trim().toLowerCase();

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

            function setBusy(state, message) {
                busy = state;
                document.querySelectorAll('#e2vt-import-all, #e2vt-push-changed, #e2vt-refresh').forEach((b) => { b.disabled = state; });
                document.getElementById('e2vt-stop').classList.toggle('d-none', !state);
                document.getElementById('e2vt-progress').textContent = message || '';
            }

            function updateBadges(meta) {
                if (!meta) return;
                document.getElementById('e2vt-b-total').textContent = meta.total ?? 0;
                document.getElementById('e2vt-b-imported').textContent = meta.imported ?? 0;
                document.getElementById('e2vt-b-success').textContent = meta.success ?? 0;
                document.getElementById('e2vt-b-failed').textContent = meta.failed ?? 0;
            }

            // Actions / Reason / New Type cells depend on several fields, so re-render the touched rows.
            function applyRowUpdates(updates) {
                const list = (updates || []).filter(Boolean);
                return Promise.resolve(table.updateData(list)).then(() => {
                    list.forEach((u) => { const r = table.getRow(u.item_id); if (r) r.reformat(); });
                });
            }

            function recountBadges() {
                const rows = table.getData();
                updateBadges({
                    total: rows.length,
                    imported: rows.filter((r) => r.current_type).length,
                    success: rows.filter((r) => r.push_status === 'success').length,
                    failed: rows.filter((r) => r.push_status === 'failed').length,
                });
            }

            function applyFilters() {
                const filters = [];
                if (searchText) {
                    filters.push((d) => norm(d.main_listing).includes(searchText) || norm(d.item_id).includes(searchText));
                }
                if (filterMode === 'not_imported') filters.push((d) => !d.current_type);
                if (filterMode === 'changed') filters.push((d) => isChanged(d));
                if (filterMode === 'differs_master') filters.push((d) => d.master_type && d.current_type && norm(d.master_type) !== norm(d.current_type));
                if (filterMode === 'failed') filters.push((d) => d.push_status === 'failed');
                if (filterMode === 'success') filters.push((d) => d.push_status === 'success');
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

            async function pushRow(d) {
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

            function visibleRows() {
                return table.getRows('active').map((r) => r.getData());
            }

            function fitHeight() {
                const wrap = document.getElementById('e2vt-wrap');
                const top = wrap.getBoundingClientRect().top;
                wrap.style.height = Math.max(320, Math.floor(window.innerHeight - top - 12)) + 'px';
                if (table) table.setHeight('100%');
            }

            document.addEventListener('DOMContentLoaded', function () {
                fitHeight();
                window.addEventListener('resize', () => { clearTimeout(window._e2vtR); window._e2vtR = setTimeout(fitHeight, 100); });

                table = new Tabulator('#e2vt-table', {
                    ajaxURL: URLS.data,
                    ajaxResponse: function (url, params, response) {
                        updateBadges(response.meta);
                        setTimeout(fitHeight, 0);
                        return response.data || [];
                    },
                    ajaxError: function (error) {
                        document.getElementById('e2vt-progress').textContent = 'Could not load the table.';
                    },
                    index: 'item_id',
                    height: '100%',
                    layout: 'fitColumns',
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [25, 50, 100, 250, 500],
                    paginationCounter: 'rows',
                    placeholder: 'No live eBay 2 listings with variations found. Pull listings on the Variation Verify page first.',
                    columns: [
                        {
                            title: 'Main Listing', field: 'main_listing', minWidth: 190, widthGrow: 2, headerHozAlign: 'center',
                            formatter: (c) => '<b>' + esc(c.getValue()) + '</b>',
                        },
                        {
                            title: 'Item ID', field: 'item_id', width: 135, hozAlign: 'center', headerHozAlign: 'center',
                            formatter: (c) => '<a href="https://www.ebay.com/itm/' + encodeURIComponent(c.getValue()) + '" target="_blank" rel="noopener">' + esc(c.getValue()) + '</a>',
                        },
                        { title: 'Children', field: 'children', width: 80, hozAlign: 'center', headerHozAlign: 'center' },
                        {
                            title: 'Variation Master Type', field: 'master_type', width: 150, hozAlign: 'center', headerHozAlign: 'center',
                            formatter: (c) => c.getValue() ? '<span class="e2vt-type-pill">' + esc(c.getValue()) + '</span>' : '<span class="e2vt-muted">—</span>',
                        },
                        {
                            title: 'Current Type (eBay)', field: 'current_type', width: 160, hozAlign: 'center', headerHozAlign: 'center',
                            formatter: function (c) {
                                const d = c.getData();
                                if (d.current_type) {
                                    return '<span class="e2vt-type-pill" title="Imported ' + esc(d.imported_at || '') + '">' + esc(d.current_type) + '</span>';
                                }
                                if (d.import_error) {
                                    return '<span class="text-danger small" title="' + esc(d.import_error) + '">Import failed</span>';
                                }
                                return '<span class="e2vt-muted">Not imported</span>';
                            },
                        },
                        {
                            title: 'New Type (edit)', field: 'new_type', width: 190, headerHozAlign: 'center',
                            editor: 'input', editorParams: { elementAttributes: { maxlength: 65 } },
                            formatter: function (c) {
                                const v = c.getValue();
                                const changed = isChanged(c.getData());
                                if (changed) c.getElement().classList.add('e2vt-changed'); else c.getElement().classList.remove('e2vt-changed');
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
                            title: 'Actions', width: 150, hozAlign: 'center', headerHozAlign: 'center', headerSort: false,
                            formatter: function (c) {
                                const d = c.getData();
                                const canPush = isChanged(d);
                                return '<button type="button" class="btn btn-sm btn-outline-secondary e2vt-import-one me-1" title="Import current type from eBay"><i class="ri-download-line"></i></button>'
                                    + '<button type="button" class="btn btn-sm ' + (canPush ? 'btn-success' : 'btn-outline-secondary') + ' e2vt-push-one" title="' + (canPush ? 'Push to eBay 2' : 'Push (New Type is empty or equals the current type)') + '"><i class="ri-upload-2-line me-1"></i>Push</button>';
                            },
                            cellClick: async function (e, cell) {
                                if (busy) return;
                                const importBtn = e.target.closest('.e2vt-import-one');
                                const pushBtn = e.target.closest('.e2vt-push-one');
                                if (!importBtn && !pushBtn) return;
                                const d = cell.getData();
                                const btn = importBtn || pushBtn;
                                if (pushBtn && !isChanged(d) && !confirm('New Type is empty or already equals the current type. Push anyway?')) return;
                                btn.disabled = true;
                                setBusy(true, (importBtn ? 'Importing ' : 'Pushing ') + d.main_listing + '...');
                                try {
                                    if (importBtn) await importRows([d]); else await pushRow(d);
                                } finally {
                                    setBusy(false, '');
                                    cell.getRow().reformat();
                                }
                            },
                        },
                        {
                            title: 'Status', field: 'push_status', width: 95, hozAlign: 'center', headerHozAlign: 'center',
                            formatter: function (c) {
                                const v = c.getValue();
                                const d = c.getData();
                                const tip = d.pushed_at ? ' title="' + esc(d.pushed_at) + '"' : '';
                                if (v === 'success') return '<span class="e2vt-status e2vt-status--success"' + tip + '>Success</span>';
                                if (v === 'failed') return '<span class="e2vt-status e2vt-status--failed"' + tip + '>Failed</span>';
                                return '<span class="e2vt-status e2vt-status--none">—</span>';
                            },
                        },
                        {
                            title: 'Reason', field: 'push_message', minWidth: 260, widthGrow: 3, headerHozAlign: 'center',
                            formatter: function (c) {
                                const d = c.getData();
                                if (d.push_status === 'failed' && d.push_message) return '<div class="e2vt-reason e2vt-reason--failed">' + esc(d.push_message) + '</div>';
                                if (d.push_status === 'success' && d.push_message) return '<div class="e2vt-reason e2vt-reason--success">' + esc(d.push_message) + '</div>';
                                if (d.import_error) return '<div class="e2vt-reason e2vt-reason--failed">Import: ' + esc(d.import_error) + '</div>';
                                return '';
                            },
                            variableHeight: true,
                        },
                    ],
                });

                document.getElementById('e2vt-refresh').addEventListener('click', () => table.setData(URLS.data));
                document.getElementById('e2vt-stop').addEventListener('click', () => { stopRequested = true; });
                document.getElementById('e2vt-filter').addEventListener('change', (e) => { filterMode = e.target.value; applyFilters(); });
                document.getElementById('e2vt-search').addEventListener('input', (e) => { searchText = norm(e.target.value); applyFilters(); });

                document.getElementById('e2vt-import-all').addEventListener('click', async function () {
                    if (busy) return;
                    const rows = visibleRows();
                    if (!rows.length) return;
                    if (!confirm('Import the current variation type of ' + rows.length + ' listing(s) from eBay 2? This calls GetItem once per listing.')) return;
                    stopRequested = false;
                    setBusy(true, 'Importing...');
                    const done = await importRows(rows);
                    setBusy(false, (stopRequested ? 'Stopped after ' : 'Imported ') + done + ' listing(s).');
                    table.redraw(true);
                });

                document.getElementById('e2vt-push-changed').addEventListener('click', async function () {
                    if (busy) return;
                    const rows = visibleRows().filter(isChanged);
                    if (!rows.length) {
                        alert('Nothing to push: no listing in this view has a New Type that differs from its current type.');
                        return;
                    }
                    if (!confirm('Push the new variation type to eBay 2 for ' + rows.length + ' listing(s)? This changes live listings.')) return;
                    stopRequested = false;
                    setBusy(true, 'Pushing...');
                    let n = 0;
                    for (const d of rows) {
                        if (stopRequested) break;
                        n++;
                        document.getElementById('e2vt-progress').textContent = 'Pushing ' + n + ' / ' + rows.length + ' (' + d.main_listing + ')...';
                        await pushRow(table.getRow(d.item_id).getData());
                    }
                    setBusy(false, (stopRequested ? 'Stopped after ' : 'Pushed ') + n + ' listing(s). See the Status and Reason columns.');
                    table.redraw(true);
                });
            });
        })();
    </script>
@endsection
