@extends('layouts.vertical', ['title' => 'Description For HTML', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .card.dh-master-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .card.dh-master-card .card-body { padding: 1.25rem 1.5rem; }
        .dh-master-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; }
        .dh-master-toolbar .btn { padding:.3rem .6rem; font-size:.8rem; }
        #description-html-table { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        #description-html-table .tabulator-header { background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color:#fff; }
        #description-html-table .tabulator-col { background:transparent !important; border-right:1px solid rgba(255,255,255,.15); }
        #description-html-table .tabulator-col-title { color:#fff; font-size:11px; font-weight:600; text-transform:uppercase; }
        #description-html-table .tabulator-header-filter input {
            background:rgba(255,255,255,.95); border:none; border-radius:4px; color:#333;
            padding:4px 6px; width:100%; font-size:10px;
        }
        #description-html-table .tabulator-row:nth-child(even) { background:#f8fafc; }
        #description-html-table .tabulator-row:hover { background:#e8f0fe !important; }
        #description-html-table .tabulator-cell { font-size:11px; color:#475569; }
        .preview-cell { max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; cursor:help; }
        .toast-container { position:fixed; top:20px; right:20px; z-index:9999; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        #dhCharCount { font-variant-numeric: tabular-nums; }
        #modalDescHtml { font-family: ui-monospace, Consolas, monospace; font-size: 12px; }
        #viewHtmlPreview {
            border:1px solid #e2e8f0; border-radius:8px; padding:.75rem; background:#fff;
            max-height:min(50vh,360px); overflow:auto; font-size:13px; line-height:1.45;
        }
        #viewHtmlSource {
            white-space:pre-wrap; word-break:break-word; background:#f8fafc; border:1px solid #e2e8f0;
            border-radius:8px; padding:.75rem; min-height:2rem; max-height:min(40vh,280px); overflow:auto;
            font-family: ui-monospace, Consolas, monospace; font-size:11px;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Description For HTML',
        'sub_title' => 'HTML description by SKU (same product set as Description Master)',
    ])

    <div class="toast-container" id="toastContainer"></div>

    <div class="row">
        <div class="col-12">
            <div class="card dh-master-card">
                <div class="card-body">
                    <div class="mb-3 dh-master-toolbar">
                        <button type="button" id="exportBtn" class="btn btn-primary btn-sm">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button type="button" id="importBtn" class="btn btn-info btn-sm">
                            <i class="fas fa-upload"></i> Import
                        </button>
                        <select id="statusFilter" class="form-select form-select-sm" style="width:auto; min-width:200px;">
                            <option value="all">All rows</option>
                            <option value="has">Has HTML Description</option>
                            <option value="missing">Missing HTML Description</option>
                        </select>
                        <span class="text-muted small" id="rowCountBadge">0 products</span>
                        <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
                    </div>

                    <div id="description-html-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editHtmlModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Description For HTML</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalSku">
                    <div class="mb-2"><strong>SKU:</strong> <span id="modalSkuLabel"></span></div>
                    <div class="mb-3"><strong>Product:</strong> <span id="modalProductLabel"></span></div>
                    <label for="modalDescHtml" class="form-label">HTML Description</label>
                    <textarea id="modalDescHtml" class="form-control" rows="14" placeholder="<div>…</div>"></textarea>
                    <div class="d-flex justify-content-between mt-1">
                        <span class="small text-muted">Synced with Description Master (<code>description_1500</code> + <code>description_html</code>)</span>
                        <span class="small text-muted" id="dhCharCount">0 chars</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="modalSaveBtn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewHtmlModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Description For HTML</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewHtmlSubtitle" class="small text-muted mb-2"></div>
                    <div class="fw-semibold small mb-1">Rendered preview</div>
                    <div id="viewHtmlPreview" class="mb-3"></div>
                    <div class="fw-semibold small mb-1">HTML source</div>
                    <div id="viewHtmlSource"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="viewCopyBtn"><i class="fas fa-copy me-1"></i> Copy HTML</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        (function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            let table = null;
            let editModal = null;
            let viewModal = null;

            function toast(message, type) {
                const wrap = document.getElementById('toastContainer');
                if (!wrap) return;
                const el = document.createElement('div');
                el.className = 'alert alert-' + (type === 'error' ? 'danger' : 'success') + ' alert-dismissible fade show shadow-sm py-2 px-3 mb-2';
                el.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                wrap.appendChild(el);
                setTimeout(() => el.remove(), 3200);
            }

            function esc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            /** Same primary content as Description Master: HTML col, else DESC 1500 / product_description. */
            function htmlText(row) {
                const html = String(row.description_html || '').trim();
                if (html) return html;
                const forHtml = String(row.description_for_html || '').trim();
                if (forHtml) return forHtml;
                return String(row.description_1500 || row.product_description || '').trim();
            }

            /** Same preview source as /product-description (description_1500 || product_description). */
            function pmPreviewText(row) {
                return String(row.description_1500 || row.product_description || htmlText(row) || '').trim();
            }

            function stripPreview(html) {
                const tmp = document.createElement('div');
                tmp.innerHTML = html || '';
                const t = (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
                if (!t) return html ? '(HTML markup)' : '—';
                return t.length > 100 ? t.slice(0, 100) + '…' : t;
            }

            function preview100(row) {
                const base = pmPreviewText(row);
                if (!base) return '—';
                const plain = stripPreview(base);
                return plain === '(HTML markup)' ? '—' : plain;
            }

            function updateRowCount(n) {
                const badge = document.getElementById('rowCountBadge');
                if (badge) badge.textContent = n + ' product' + (n === 1 ? '' : 's');
            }

            function applyStatusFilter() {
                if (!table) return;
                const mode = document.getElementById('statusFilter')?.value || 'all';
                if (mode === 'has') {
                    table.setFilter(row => pmPreviewText(row.getData()) !== '' || htmlText(row.getData()) !== '');
                } else if (mode === 'missing') {
                    table.setFilter(row => pmPreviewText(row.getData()) === '' && htmlText(row.getData()) === '');
                } else {
                    table.clearFilter(true);
                }
                updateRowCount(table.getDataCount('active'));
            }

            async function saveHtml(sku, html) {
                const res = await fetch('/description-for-html/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        sku: sku,
                        description_html: html,
                        description_for_html: html,
                    }),
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    throw new Error(json.message || 'Save failed');
                }
                return json;
            }

            function openEdit(rowData) {
                document.getElementById('modalSku').value = rowData.SKU || '';
                document.getElementById('modalSkuLabel').textContent = rowData.SKU || '';
                document.getElementById('modalProductLabel').textContent = rowData.Parent || rowData.title150 || rowData.SKU || '';
                document.getElementById('modalDescHtml').value = htmlText(rowData);
                updateCharCount();
                editModal.show();
            }

            function openView(rowData) {
                const html = htmlText(rowData);
                document.getElementById('viewHtmlSubtitle').textContent =
                    (rowData.SKU || '') + ' — ' + (rowData.Parent || rowData.title150 || '');
                document.getElementById('viewHtmlPreview').innerHTML = html || '<span class="text-muted">(empty)</span>';
                document.getElementById('viewHtmlSource').textContent = html || '(empty)';
                viewModal.show();
            }

            function updateCharCount() {
                const n = (document.getElementById('modalDescHtml').value || '').length;
                document.getElementById('dhCharCount').textContent = n + ' chars';
            }

            function initTable(rows) {
                if (table) {
                    table.replaceData(rows);
                    applyStatusFilter();
                    return;
                }

                table = new Tabulator('#description-html-table', {
                    data: rows,
                    layout: 'fitColumns',
                    height: '640px',
                    pagination: true,
                    paginationSize: 50,
                    paginationSizeSelector: [25, 50, 100, 200],
                    placeholder: 'No products found',
                    columns: [
                        {
                            title: 'SKU',
                            field: 'SKU',
                            width: 180,
                            headerFilter: 'input',
                            headerFilterPlaceholder: 'Filter SKU',
                        },
                        {
                            title: 'Product Name',
                            field: 'Parent',
                            width: 200,
                            headerFilter: 'input',
                            headerFilterPlaceholder: 'Filter parent',
                            formatter: function (cell) {
                                const d = cell.getRow().getData();
                                return esc(d.Parent || d.title150 || d.SKU || '');
                            },
                        },
                        {
                            title: 'Preview (PM)',
                            field: 'description_1500',
                            minWidth: 220,
                            headerFilter: 'input',
                            headerFilterPlaceholder: 'Filter preview',
                            formatter: function (cell) {
                                const d = cell.getRow().getData();
                                const full = pmPreviewText(d);
                                const prev = preview100(d);
                                return '<span class="preview-cell" title="' + esc(full) + '">' + esc(prev) + '</span>';
                            },
                        },
                        {
                            title: 'Description For HTML',
                            field: 'description_for_html',
                            minWidth: 320,
                            editor: 'textarea',
                            formatter: function (cell) {
                                const d = cell.getRow().getData();
                                const v = htmlText(d);
                                if (!v) return '<span class="text-muted">—</span>';
                                return esc(v.length > 160 ? v.slice(0, 160) + '…' : v);
                            },
                            cellEdited: async function (cell) {
                                const d = cell.getRow().getData();
                                const sku = d.SKU;
                                const value = String(cell.getValue() || '');
                                try {
                                    await saveHtml(sku, value);
                                    cell.getRow().update({
                                        description_for_html: value,
                                        description_html: value,
                                        description_1500: value,
                                        product_description: value,
                                    });
                                    toast('Saved HTML description for ' + sku, 'success');
                                    applyStatusFilter();
                                } catch (e) {
                                    toast(e.message || 'Save failed', 'error');
                                    cell.restoreOldValue();
                                }
                            },
                        },
                        {
                            title: 'Action',
                            field: '_action',
                            width: 150,
                            hozAlign: 'center',
                            headerSort: false,
                            formatter: function () {
                                return '<div class="d-flex gap-1 justify-content-center">' +
                                    '<button type="button" class="btn btn-sm btn-info text-white dh-view-btn"><i class="fas fa-eye"></i></button>' +
                                    '<button type="button" class="btn btn-sm btn-primary dh-edit-btn"><i class="fas fa-edit"></i></button>' +
                                    '</div>';
                            },
                            cellClick: function (e, cell) {
                                const d = cell.getRow().getData();
                                if (e.target.closest('.dh-view-btn')) openView(d);
                                if (e.target.closest('.dh-edit-btn')) openEdit(d);
                            },
                        },
                    ],
                });

                table.on('dataFiltered', function () {
                    updateRowCount(table.getDataCount('active'));
                });
                updateRowCount(rows.length);
            }

            async function loadData() {
                updateRowCount(0);
                try {
                    const res = await fetch('/description-for-html-data', { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    if (!res.ok || json.status === 500) {
                        throw new Error(json.message || 'Failed to load data');
                    }
                    initTable(json.data || []);
                    applyStatusFilter();
                } catch (e) {
                    toast(e.message || 'Failed to load Description For HTML data', 'error');
                    initTable([]);
                }
            }

            function exportExcel() {
                if (!table) return;
                const rows = table.getData('active').map(r => ({
                    SKU: r.SKU || '',
                    Parent: r.Parent || '',
                    title150: r.title150 || '',
                    description_1500: r.description_1500 || r.product_description || '',
                    description_1000: r.description_1000 || '',
                    description_800: r.description_800 || '',
                    description_600: r.description_600 || '',
                    description_html: htmlText(r),
                    description_for_html: htmlText(r),
                }));
                const ws = XLSX.utils.json_to_sheet(rows);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Description HTML');
                XLSX.writeFile(wb, 'description_for_html_export.xlsx');
            }

            async function importExcel(file) {
                const buf = await file.arrayBuffer();
                const wb = XLSX.read(buf, { type: 'array' });
                const sheet = wb.Sheets[wb.SheetNames[0]];
                const rows = XLSX.utils.sheet_to_json(sheet, { defval: '' });
                let ok = 0, fail = 0;
                for (const row of rows) {
                    const sku = String(row.SKU || row.sku || '').trim();
                    if (!sku) continue;
                    const html = String(
                        row.description_html
                        || row.description_for_html
                        || row['Description For HTML']
                        || row['Description HTML']
                        || row.description_1500
                        || row.product_description
                        || ''
                    );
                    try {
                        await saveHtml(sku, html);
                        ok++;
                        const tRow = table?.getRows().find(r => r.getData().SKU === sku);
                        if (tRow) {
                            tRow.update({
                                description_for_html: html,
                                description_html: html,
                                description_1500: html,
                                product_description: html,
                            });
                        }
                    } catch (_) {
                        fail++;
                    }
                }
                toast('Import done: ' + ok + ' saved' + (fail ? ', ' + fail + ' failed' : ''), fail ? 'error' : 'success');
                applyStatusFilter();
            }

            document.addEventListener('DOMContentLoaded', function () {
                editModal = new bootstrap.Modal(document.getElementById('editHtmlModal'));
                viewModal = new bootstrap.Modal(document.getElementById('viewHtmlModal'));

                document.getElementById('exportBtn').addEventListener('click', exportExcel);
                document.getElementById('importBtn').addEventListener('click', () => document.getElementById('importFile').click());
                document.getElementById('importFile').addEventListener('change', function (e) {
                    const file = e.target.files?.[0];
                    if (file) importExcel(file);
                    e.target.value = '';
                });
                document.getElementById('statusFilter').addEventListener('change', applyStatusFilter);
                document.getElementById('modalDescHtml').addEventListener('input', updateCharCount);

                document.getElementById('modalSaveBtn').addEventListener('click', async function () {
                    const sku = document.getElementById('modalSku').value;
                    const html = document.getElementById('modalDescHtml').value || '';
                    const btn = this;
                    btn.disabled = true;
                    try {
                        await saveHtml(sku, html);
                        const tRow = table?.getRows().find(r => r.getData().SKU === sku);
                        if (tRow) {
                            tRow.update({
                                description_for_html: html,
                                description_html: html,
                                description_1500: html,
                                product_description: html,
                            });
                        }
                        toast('Saved HTML description for ' + sku, 'success');
                        editModal.hide();
                        applyStatusFilter();
                    } catch (e) {
                        toast(e.message || 'Save failed', 'error');
                    } finally {
                        btn.disabled = false;
                    }
                });

                document.getElementById('viewCopyBtn').addEventListener('click', async function () {
                    const text = document.getElementById('viewHtmlSource').textContent || '';
                    try {
                        await navigator.clipboard.writeText(text === '(empty)' ? '' : text);
                        toast('Copied to clipboard', 'success');
                    } catch (_) {
                        toast('Copy failed', 'error');
                    }
                });

                loadData();
            });
        })();
    </script>
@endsection
