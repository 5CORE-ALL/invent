@extends('layouts.vertical', ['title' => 'Temu 1 Missing Ads', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #temu-missing-ads-table .tabulator-header {
            background: #fd7e14;
            font-size: 0.8rem;
            color: #fff;
        }
        #temu-missing-ads-table .tabulator-header .tabulator-col {
            background: #fd7e14;
            color: #fff;
            border-right: 1px solid rgba(255,255,255,0.25);
            text-align: center;
        }
        #temu-missing-ads-table .tabulator-header .tabulator-col .tabulator-col-content,
        #temu-missing-ads-table .tabulator-header .tabulator-col .tabulator-col-title {
            text-align: center;
            justify-content: center;
        }
        #temu-missing-ads-table .tabulator-col .tabulator-col-sorter,
        #temu-missing-ads-table .tabulator-col .tabulator-col-sorter-element,
        #temu-missing-ads-table .tabulator-col .tabulator-arrow {
            display: none !important;
        }
        #temu-missing-ads-table .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 4px 3px;
        }
        #temu-missing-ads-table .tabulator-cell {
            font-size: 0.85rem;
            text-align: center !important;
            justify-content: center;
            align-items: center;
            padding: 2px 4px !important;
        }
        #temu-missing-ads-table .tabulator-cell[tabulator-field="image_path"] {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1px 2px !important;
            overflow: hidden;
        }
        #temu-missing-ads-table .temu-ads-thumb {
            width: auto;
            height: 22px;
            max-width: 100%;
            max-height: 22px;
            object-fit: contain;
            border-radius: 2px;
            vertical-align: middle;
        }
        #temu-missing-ads-table .temu-ads-row-cb,
        #temu-missing-ads-table .temu-ads-select-all {
            width: 16px;
            height: 16px;
            margin: 0;
            cursor: pointer;
            accent-color: #0d6efd;
            vertical-align: middle;
        }
        .temu-ad-create-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        .temu-ad-reject-tri {
            color: #dc3545;
            font-size: 11px;
            line-height: 1;
            cursor: help;
        }
        .create-row-ad-btn {
            padding: 1px 6px;
            font-size: 11px;
            line-height: 1.2;
        }
        .temu-missing-badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Temu 1 Missing Ads',
        'sub_title' => 'Temu 1 goods with Status No ad and Inv > 0 — same Create queue as /temu/ads.',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <input type="text" id="search-goods-id" class="form-control form-control-sm"
                                   placeholder="Search Goods ID" style="width: 170px;">
                            <input type="text" id="search-sku" class="form-control form-control-sm"
                                   placeholder="Search SKU" style="width: 150px;">
                            <a href="{{ route('temu.ads') }}" class="btn btn-sm btn-outline-secondary"
                               title="Open Temu Ads (API)">Temu Ads</a>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="button" id="create-ad-btn" class="btn btn-sm btn-warning"
                                    title="Create ads Rule — budget and target ROAS used for Create">
                                <i class="fa fa-plus"></i> Create ads Rule
                            </button>
                            <button type="button" id="export-btn" class="btn btn-sm btn-success" title="Export CSV">
                                <i class="fa fa-download"></i>
                            </button>
                        </div>
                    </div>

                    <div id="fetch-status" class="mb-2" style="display:none;"></div>

                    <div class="mt-2 p-3 bg-light rounded">
                        <div class="temu-missing-badge-row" role="group" aria-label="Missing ads summary">
                            <span class="badge fs-6 p-2" id="missing-count"
                                  style="background-color: #dc3545; color: white; font-weight: bold;"
                                  title="Unique Temu 1 goods with Status No ad and Inv > 0">
                                Missing: <span class="temu-ads-badge-val">0</span>
                            </span>
                            <span class="badge fs-6 p-2" id="create-count"
                                  style="background-color: #fd7e14; color: white; font-weight: bold; cursor: pointer;"
                                  title="Create ads for selected No ad rows (Inv > 0). If nothing is selected, uses all visible Create rows.">
                                Create: <span class="temu-ads-badge-val">0</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div id="temu-missing-ads-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createAdModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create ads Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Same as /temu/ads — calls <code>temu.searchrec.ad.create</code>.</p>
                    <div class="mb-2">
                        <label class="form-label form-label-sm" for="create-goods-id">Goods ID</label>
                        <input type="text" id="create-goods-id" class="form-control form-control-sm" placeholder="602442267775049">
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm" for="create-budget">Daily budget (USD)</label>
                        <input type="number" id="create-budget" class="form-control form-control-sm" min="1" step="0.01" value="10">
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm" for="create-roas">Target ROAS</label>
                        <input type="number" id="create-roas" class="form-control form-control-sm" min="0.1" max="12" step="0.1" value="4">
                    </div>
                    <div id="create-ad-status" class="mt-2" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-warning" id="create-ad-submit">Create Ad</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="{{ asset('js/temu-ads-color-rules.js') }}?v={{ @filemtime(public_path('js/temu-ads-color-rules.js')) ?: 16 }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dataUrl = @json(route('temu.ads.missing.data'));
            const selectedGoodsIds = new Set();
            let table = null;

            function numFmt(cell) {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '';
                return Number(v).toLocaleString();
            }

            function escapeAttr(s) {
                return String(s || '')
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            function setBadgeVal(id, text) {
                const el = document.getElementById(id);
                if (!el) return;
                const val = el.querySelector('.temu-ads-badge-val');
                if (val) val.textContent = text;
            }

            function rowGoodsId(row) {
                return String((row && row.goods_id) || '').trim();
            }

            function canCreateAdRow(row) {
                if (!row) return false;
                if (String(row.ad_status || '') !== 'No ad') return false;
                return (parseInt(row.inv, 10) || 0) > 0;
            }

            function hasRowSelection() {
                return selectedGoodsIds.size > 0;
            }

            function selectedRowData() {
                if (!table || !hasRowSelection()) return [];
                return (table.getData() || []).filter(function (row) {
                    return selectedGoodsIds.has(rowGoodsId(row));
                });
            }

            function createSourceRows() {
                if (!table) return [];
                if (hasRowSelection()) return selectedRowData();
                return table.getData(true) || [];
            }

            function queueCreateGoodsIdsFromRows(rows) {
                const seen = {};
                const ids = [];
                (rows || []).forEach(function (row) {
                    if (!canCreateAdRow(row)) return;
                    const gid = String(row.goods_id || '').trim();
                    if (!gid || seen[gid]) return;
                    seen[gid] = true;
                    ids.push(gid);
                });
                return ids;
            }

            function createBadgeCount() {
                return queueCreateGoodsIdsFromRows(createSourceRows()).length;
            }

            function paintCreateBadge() {
                const n = createBadgeCount();
                const missing = table ? (table.getData(true) || []).length : 0;
                setBadgeVal('create-count', Number(n).toLocaleString());
                setBadgeVal('missing-count', Number(missing).toLocaleString());
                const el = document.getElementById('create-count');
                if (!el) return;
                el.title = hasRowSelection()
                    ? 'Create the ' + n + ' selected No ad row(s) with Inv > 0.'
                    : 'Create all ' + n + ' visible No ad rows with Inv > 0.';
            }

            function selectCheckboxHtml(row) {
                const id = rowGoodsId(row);
                const on = !!(id && selectedGoodsIds.has(id));
                return '<input type="checkbox" class="temu-ads-row-cb" data-gid="' + id + '"' + (on ? ' checked' : '') + '>';
            }

            function refreshSelectCheckboxes() {
                if (!table) return;
                const col = table.getColumn('_select');
                if (col && typeof col.getCells === 'function') {
                    col.getCells().forEach(function (cell) {
                        const box = cell.getElement().querySelector('.temu-ads-row-cb');
                        if (!box) return;
                        const id = rowGoodsId(cell.getRow().getData());
                        box.checked = !!(id && selectedGoodsIds.has(id));
                        box.dataset.gid = id;
                    });
                }
                const allCb = document.getElementById('temu-ads-select-all');
                if (!allCb) return;
                const rows = table.getRows('active') || [];
                let selected = 0;
                rows.forEach(function (row) {
                    if (selectedGoodsIds.has(rowGoodsId(row.getData()))) selected++;
                });
                allCb.checked = rows.length > 0 && selected === rows.length;
                allCb.indeterminate = selected > 0 && selected < rows.length;
            }

            function applySearchFilters() {
                if (!table) return;
                const gid = (document.getElementById('search-goods-id').value || '').trim().toLowerCase();
                const sku = (document.getElementById('search-sku').value || '').trim().toLowerCase();
                table.setFilter(function (data) {
                    if (gid && String(data.goods_id || '').toLowerCase().indexOf(gid) === -1) return false;
                    if (sku && String(data.sku || '').toLowerCase().indexOf(sku) === -1) return false;
                    return true;
                });
                paintCreateBadge();
            }

            table = new Tabulator('#temu-missing-ads-table', {
                ajaxURL: dataUrl,
                ajaxResponse: function (url, params, response) {
                    return (response && Array.isArray(response.data)) ? response.data : [];
                },
                index: 'goods_id',
                layout: 'fitColumns',
                height: 'calc(100vh - 280px)',
                placeholder: 'No missing Temu 1 ads (Status No ad and Inv > 0)',
                initialSort: [{ column: 'sku', dir: 'asc' }],
                columns: [
                    {
                        title: '<input type="checkbox" class="temu-ads-select-all" id="temu-ads-select-all">',
                        field: '_select',
                        width: 40,
                        hozAlign: 'center',
                        headerSort: false,
                        headerHozAlign: 'center',
                        formatter: function (cell) {
                            return selectCheckboxHtml(cell.getRow().getData() || {});
                        },
                        cellClick: function (e, cell) {
                            const cb = e.target.closest('.temu-ads-row-cb');
                            if (!cb) return;
                            e.stopPropagation();
                            const id = rowGoodsId(cell.getRow().getData());
                            if (!id) return;
                            if (selectedGoodsIds.has(id)) {
                                selectedGoodsIds.delete(id);
                                cb.checked = false;
                            } else {
                                selectedGoodsIds.add(id);
                                cb.checked = true;
                            }
                            refreshSelectCheckboxes();
                            paintCreateBadge();
                        },
                    },
                    {
                        title: 'Image',
                        field: 'image_path',
                        width: 52,
                        hozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            const src = String(cell.getValue() || '').trim();
                            if (!src) return '';
                            return '<img class="temu-ads-thumb" src="' + src.replace(/"/g, '&quot;') + '" alt="">';
                        },
                    },
                    { title: 'SKU', field: 'sku', width: 140, minWidth: 90, sorter: 'string' },
                    { title: 'Goods ID', field: 'goods_id', width: 160, minWidth: 120, sorter: 'string' },
                    { title: 'Inv', field: 'inv', width: 60, hozAlign: 'center', formatter: numFmt, sorter: 'number' },
                    { title: 'Ovl30', field: 'ovl30', width: 70, hozAlign: 'center', formatter: numFmt, sorter: 'number' },
                    {
                        title: 'Dil%',
                        field: 'dil_percent',
                        width: 70,
                        hozAlign: 'center',
                        sorter: 'number',
                        formatter: function (cell) {
                            const dil = parseFloat(cell.getValue()) || 0;
                            if (window.TemuAdsColorRules && typeof TemuAdsColorRules.dilHtml === 'function') {
                                return TemuAdsColorRules.dilHtml(dil);
                            }
                            return Math.round(dil) + '%';
                        }
                    },
                    { title: 'Clicks 7', field: 'clicks_l7', width: 80, hozAlign: 'center', formatter: numFmt, sorter: 'number' },
                    {
                        title: 'Status',
                        field: 'ad_status',
                        width: 88,
                        hozAlign: 'center',
                        formatter: function (cell) {
                            const v = String(cell.getValue() || 'No ad');
                            return '<span class="badge bg-danger">' + v + '</span>';
                        }
                    },
                    {
                        title: 'Ad',
                        field: 'create_ad',
                        width: 110,
                        hozAlign: 'center',
                        headerSort: false,
                        headerTooltip: 'Create is shown only when Status is No ad and Inv > 0. Same as /temu/ads.',
                        formatter: function (cell) {
                            const data = cell.getRow().getData() || {};
                            if (!canCreateAdRow(data)) return '';
                            const reason = String(data.ad_create_reject || '').trim();
                            const selectedN = queueCreateGoodsIdsFromRows(selectedRowData()).length;
                            const gid = String(data.goods_id || '');
                            const multi = selectedN >= 2 && selectedGoodsIds.has(gid);
                            const btnTitle = multi
                                ? 'Create Temu ads for the ' + selectedN + ' selected rows'
                                : 'Create Temu ad';
                            const tri = reason
                                ? '<i class="fas fa-exclamation-triangle temu-ad-reject-tri" title="' + escapeAttr(reason) + '"></i>'
                                : '';
                            return '<span class="temu-ad-create-cell"><button type="button" class="btn btn-sm btn-outline-warning create-row-ad-btn" title="' + escapeAttr(btnTitle) + '">Create</button>' + tri + '</span>';
                        },
                        cellClick: function (e, cell) {
                            e.stopPropagation();
                            if (!e.target.closest('.create-row-ad-btn')) return;
                            const goodsId = String((cell.getRow().getData() || {}).goods_id || '').trim();
                            if (!goodsId) return;
                            const selectedIds = queueCreateGoodsIdsFromRows(selectedRowData());
                            if (selectedIds.length >= 2 && selectedIds.indexOf(goodsId) !== -1) {
                                runBulkCreateQueue();
                                return;
                            }
                            runBulkCreateQueue([goodsId]);
                        }
                    },
                ],
            });

            table.on('dataProcessed', function () {
                paintCreateBadge();
                refreshSelectCheckboxes();
            });

            document.getElementById('search-goods-id').addEventListener('input', applySearchFilters);
            document.getElementById('search-sku').addEventListener('input', applySearchFilters);

            document.getElementById('temu-missing-ads-table').addEventListener('change', function (e) {
                if (!e.target.classList.contains('temu-ads-select-all')) return;
                const rows = table.getRows('active') || [];
                if (e.target.checked) {
                    rows.forEach(function (row) {
                        const id = rowGoodsId(row.getData());
                        if (id) selectedGoodsIds.add(id);
                    });
                } else {
                    rows.forEach(function (row) {
                        selectedGoodsIds.delete(rowGoodsId(row.getData()));
                    });
                }
                refreshSelectCheckboxes();
                paintCreateBadge();
            });

            document.getElementById('export-btn').addEventListener('click', function () {
                table.download('csv', 'temu-1-missing-ads.csv');
            });

            function createAdDefaults() {
                const budget = parseFloat(document.getElementById('create-budget').value);
                const roas = parseFloat(document.getElementById('create-roas').value);
                return {
                    budget: (isFinite(budget) && budget >= 1) ? budget : 10,
                    roas: (isFinite(roas) && roas >= 0.1) ? roas : 4,
                };
            }

            function showCreateStatus(html) {
                const el = document.getElementById('create-ad-status');
                el.style.display = 'block';
                el.innerHTML = html;
            }

            function applyCreateRejectToRows(failed) {
                if (!table || !failed || !failed.length) return;
                failed.forEach(function (item) {
                    if (!item || !item.rejected) return;
                    const gid = String(item.goods_id || '');
                    if (!gid) return;
                    (table.getRows() || []).forEach(function (row) {
                        const data = row.getData() || {};
                        if (String(data.goods_id || '') !== gid) return;
                        row.update({ ad_create_reject: item.message || 'Temu rejected this listing for ads.' });
                    });
                });
            }

            async function runBulkCreateQueue(explicitIds) {
                const usingExplicit = Array.isArray(explicitIds);
                const usingSelection = !usingExplicit && hasRowSelection();
                const ids = usingExplicit ? explicitIds.filter(Boolean) : queueCreateGoodsIdsFromRows(createSourceRows());
                const status = document.getElementById('fetch-status');
                const badge = document.getElementById('create-count');
                if (!ids.length) {
                    status.style.display = 'block';
                    status.innerHTML = usingSelection
                        ? '<div class="alert alert-warning py-2 mb-0">No selected rows can be created (need Status No ad and Inv &gt; 0).</div>'
                        : '<div class="alert alert-warning py-2 mb-0">No Create queue rows (Status No ad and Inv &gt; 0).</div>';
                    return;
                }
                const defaults = createAdDefaults();
                const confirmLead = usingExplicit && ids.length === 1
                    ? 'Create a Temu ad for this goods ID?'
                    : (usingSelection
                        ? 'Create Temu ads for the ' + ids.length + ' selected goods?'
                        : 'Create Temu ads for all ' + ids.length + ' required goods?');
                if (!confirm(
                    confirmLead + '\n' +
                    'Daily budget: $' + defaults.budget + '\n' +
                    'Target ROAS: ' + defaults.roas + '\n' +
                    '(Same as Create ads Rule on /temu/ads)'
                )) {
                    return;
                }
                if (badge) badge.style.pointerEvents = 'none';
                status.style.display = 'block';
                let created = 0;
                let failed = 0;
                let rejectedN = 0;
                const failNotes = [];
                const chunkSize = 5;
                for (let i = 0; i < ids.length; i += chunkSize) {
                    const chunk = ids.slice(i, i + chunkSize);
                    status.innerHTML = '<div class="alert alert-info py-2 mb-0"><i class="fas fa-spinner fa-spin me-1"></i> Creating ads ' +
                        Math.min(i + chunk.length, ids.length) + '/' + ids.length +
                        ' (budget $' + defaults.budget + ', ROAS ' + defaults.roas + ')…</div>';
                    try {
                        const res = await fetch(@json(route('temu.ads.create-bulk')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                goods_ids: chunk,
                                budget: defaults.budget,
                                roas: defaults.roas,
                                roas_by_goods: chunk.reduce(function (map, gid) {
                                    map[gid] = defaults.roas;
                                    return map;
                                }, {}),
                            }),
                        });
                        const data = await res.json();
                        created += (data.created && data.created.length) ? data.created.length : 0;
                        if (data.failed && data.failed.length) {
                            failed += data.failed.length;
                            applyCreateRejectToRows(data.failed);
                            data.failed.forEach(function (row) {
                                if (row.rejected) rejectedN++;
                            });
                            data.failed.slice(0, 5).forEach(function (row) {
                                const note = (row.goods_id || '') + ': ' + (row.message || 'failed');
                                failNotes.push(row.task_title ? (note + ' (' + row.task_title + ')') : note);
                            });
                        }
                    } catch (err) {
                        failed += chunk.length;
                        failNotes.push('Chunk failed: ' + (err && err.message ? err.message : 'network error'));
                    }
                    if (badge) {
                        setBadgeVal('create-count', Math.max(ids.length - created - failed, 0).toLocaleString());
                    }
                }
                let cls = failed === 0 ? 'alert-success' : (created > 0 ? 'alert-warning' : 'alert-danger');
                let msg = 'Created ' + created + '/' + ids.length + ' ads (budget $' + defaults.budget + ', ROAS ' + defaults.roas + ')';
                if (failed > 0) msg += '. Failed ' + failed;
                if (rejectedN > 0) msg += '. ' + rejectedN + ' listing reject task(s) created';
                if (failNotes.length) msg += ' — ' + failNotes.slice(0, 3).join('; ');
                status.innerHTML = '<div class="alert ' + cls + ' py-2 mb-0">' + msg + '</div>';
                if (badge) badge.style.pointerEvents = '';
                table.setData(dataUrl);
            }

            document.getElementById('create-count').addEventListener('click', function () {
                runBulkCreateQueue();
            });

            function openCreateModal(goodsId) {
                document.getElementById('create-goods-id').value = goodsId || '';
                document.getElementById('create-ad-status').style.display = 'none';
                document.getElementById('create-roas').value = String(createAdDefaults().roas);
                new bootstrap.Modal(document.getElementById('createAdModal')).show();
            }

            document.getElementById('create-ad-btn').addEventListener('click', function () {
                const q = (document.getElementById('search-goods-id').value || '').trim();
                openCreateModal(/^\d+$/.test(q) ? q : '');
            });

            document.getElementById('create-ad-submit').addEventListener('click', function () {
                const goodsId = (document.getElementById('create-goods-id').value || '').trim();
                const budget = parseFloat(document.getElementById('create-budget').value);
                const roas = parseFloat(document.getElementById('create-roas').value);
                if (!goodsId || !(budget >= 1) || !(roas >= 0.1)) {
                    showCreateStatus('<div class="alert alert-warning py-2 mb-0">Enter Goods ID, budget (≥ $1), and ROAS (≥ 0.1).</div>');
                    return;
                }
                if (!confirm('Create a live Temu ad for goods ' + goodsId + '?\nDaily budget: $' + budget.toFixed(2) + '\nTarget ROAS: ' + roas)) {
                    return;
                }
                const btn = this;
                btn.disabled = true;
                showCreateStatus('<div class="alert alert-info py-2 mb-0"><i class="fas fa-spinner fa-spin me-1"></i> Creating ad…</div>');
                $.ajax({
                    url: '{{ route("temu.ads.create") }}',
                    method: 'POST',
                    data: { goods_id: goodsId, budget: budget, roas: roas, _token: '{{ csrf_token() }}' },
                    success: function (response) {
                        if (response.success) {
                            showCreateStatus('<div class="alert alert-success py-2 mb-0">' + (response.message || 'Created') + '</div>');
                            table.setData(dataUrl);
                        } else {
                            showCreateStatus('<div class="alert alert-danger py-2 mb-0">' + (response.message || 'Failed') + '</div>');
                            table.setData(dataUrl);
                        }
                    },
                    error: function (xhr) {
                        const data = xhr.responseJSON || {};
                        showCreateStatus('<div class="alert alert-danger py-2 mb-0">' + (data.message || 'Create failed') + '</div>');
                        if (data.rejected) {
                            applyCreateRejectToRows([{
                                goods_id: goodsId,
                                message: data.message || 'Temu rejected this listing for ads.',
                                rejected: true,
                            }]);
                        }
                        table.setData(dataUrl);
                    },
                    complete: function () {
                        btn.disabled = false;
                    }
                });
            });
        });
    </script>
@endsection
