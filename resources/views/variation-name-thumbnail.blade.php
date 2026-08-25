@extends('layouts.vertical', ['title' => 'PM variation name / thumbnail', 'mode' => $mode ?? '', 'demo' => $demo ?? '', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    @include('partials.marketplace-master-button-colors')
    <style>
        #vnt-table-wrapper {
            height: calc(100vh - 128px);
            min-height: 280px;
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        #vnt-tabulator {
            flex: 1;
            min-height: 0;
            width: 100%;
            border-top: 1px solid #dee2e6;
        }

        #vnt-tabulator .tabulator {
            font-size: 11px;
            width: 100% !important;
            border: 1px solid #d1d5db;
        }

        #vnt-tabulator .tabulator-header {
            background: #e8ecf1;
            color: #1e293b;
            font-weight: 600;
            border-bottom: 1px solid #cbd5e1;
        }

        #vnt-tabulator .tabulator-header .tabulator-col {
            border-right: 1px solid #cbd5e1;
        }

        #vnt-tabulator .tabulator-header .tabulator-col-content,
        #vnt-tabulator .tabulator-header .tabulator-col-title,
        #vnt-tabulator .tabulator-header-filter {
            text-align: center;
        }

        #vnt-tabulator .tabulator-header .tabulator-col-title {
            font-size: 11px;
            font-weight: 600;
            color: #334155;
        }

        #vnt-tabulator .tabulator-header-filter input {
            text-align: center;
        }

        #vnt-tabulator .tabulator-row .tabulator-cell {
            padding: 4px 6px;
            vertical-align: middle;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
            border-right: 1px solid #f1f5f9;
            font-size: 11px;
        }

        #vnt-tabulator .tabulator-row.tabulator-row-even .tabulator-cell {
            background-color: #fafafa;
        }

        #vnt-tabulator .tabulator-row:hover .tabulator-cell {
            background-color: #f1f5f9;
        }

        #vnt-tabulator .tabulator-row.vnt-parent-row .tabulator-cell {
            background-color: #fffbeb;
        }

        #vnt-tabulator .vnt-thumb {
            width: 36px;
            height: 36px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            cursor: zoom-in;
        }

        #vnt-tabulator .vnt-thumb-empty {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 4px;
            border: 1px dashed #cbd5e1;
            color: #94a3b8;
            background: #f8fafc;
        }

        #vnt-img-hover-preview {
            position: fixed;
            z-index: 10050;
            display: none;
            pointer-events: none;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.22);
            border-radius: 8px;
            background: #fff;
            padding: 6px;
            line-height: 0;
        }

        #vnt-img-hover-preview img {
            max-width: min(92vw, 380px);
            max-height: min(85vh, 380px);
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            border-radius: 4px;
        }

        .vnt-toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .vnt-toolbar .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
        }

        #vnt-tabulator .vnt-edit-btn {
            padding: 2px 6px;
            line-height: 1;
            border-radius: 4px;
        }

        #vnt-tabulator .vnt-edit-btn i {
            font-size: 13px;
        }

        #vntEditModal .vnt-sku-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            max-height: 88px;
            overflow: auto;
        }

        #vntEditModal .vnt-sku-chip {
            background: #e8ecf1;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 11px;
        }

        #vnt-tabulator .vnt-push-btn {
            padding: 2px 6px;
            line-height: 1;
            border-radius: 4px;
        }

        #vntPushModal .vnt-channel-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 6px;
            max-height: 360px;
            overflow: auto;
        }

        #vntPushModal .vnt-channel-item {
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px 8px;
            margin: 0;
            cursor: pointer;
            background: #fff;
        }

        #vntPushModal .vnt-channel-item.is-off {
            opacity: 0.55;
        }

        #vntPushModal .vnt-channel-tile {
            width: 28px;
            height: 28px;
            border-radius: 4px;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        #vntPushModal .vnt-channel-logo {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 4px;
            background: #f8fafc;
        }

        #vntPushModal .vnt-push-results {
            max-height: 160px;
            overflow: auto;
            font-size: 11px;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared.page-title', [
        'page_title' => 'PM variation name / thumbnail',
        'sub_title' => 'Product Masters',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2">
                    <div class="vnt-toolbar">
                        <button type="button" class="btn btn-sm btn-primary" id="vnt-upload-btn" title="Upload Excel (SKU + last 3 columns)" aria-label="Upload Excel">
                            <i class="bi bi-upload"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-success" id="vnt-download-btn" title="Download template (SKU, Variation Name, Thumbnail, INV)" aria-label="Download template">
                            <i class="bi bi-download"></i>
                        </button>
                        <input type="file" id="vnt-upload-file" class="d-none" accept=".xlsx,.xls,.csv">
                        <span class="small text-muted">Selected: <strong id="vnt-selected-count">0</strong></span>
                        <span class="small ms-auto" id="vnt-import-status"></span>
                    </div>
                </div>
                <div id="vnt-table-wrapper">
                    <div id="vnt-tabulator"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vntEditModal" tabindex="-1" aria-labelledby="vntEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); color: #fff;">
                    <h5 class="modal-title" id="vntEditModalLabel">Edit row</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="vntEditForm">
                    <div class="modal-body">
                        <div id="vnt-edit-single-meta" class="mb-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small mb-1">Parent</label>
                                    <input type="text" class="form-control form-control-sm" id="vnt-edit-parent" readonly>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">SKU</label>
                                    <input type="text" class="form-control form-control-sm" id="vnt-edit-sku" readonly>
                                </div>
                            </div>
                        </div>
                        <div id="vnt-edit-bulk-meta" class="mb-3 d-none">
                            <div class="small fw-semibold mb-1" id="vnt-edit-bulk-count">0 SKUs selected</div>
                            <div class="vnt-sku-chips" id="vnt-edit-sku-chips"></div>
                            <div class="form-text">Check a field to apply it to every selected row. Unchecked fields stay unchanged.</div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <input type="checkbox" class="form-check-input vnt-apply-flag" id="vnt-apply-name" checked>
                                <label class="form-label small mb-0" for="vnt-edit-name">Variation Name</label>
                            </div>
                            <input type="text" class="form-control form-control-sm" id="vnt-edit-name" maxlength="500">
                        </div>
                        <div class="mb-3">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <input type="checkbox" class="form-check-input vnt-apply-flag" id="vnt-apply-thumb" checked>
                                <label class="form-label small mb-0" for="vnt-edit-thumb">Thumbnail</label>
                            </div>
                            <input type="text" class="form-control form-control-sm" id="vnt-edit-thumb" maxlength="2000">
                        </div>
                        <div class="mb-0">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <input type="checkbox" class="form-check-input vnt-apply-flag" id="vnt-apply-inv" checked>
                                <label class="form-label small mb-0" for="vnt-edit-inv">INV</label>
                            </div>
                            <input type="number" step="any" class="form-control form-control-sm" id="vnt-edit-inv">
                        </div>
                        <div class="text-danger small mt-2 d-none" id="vnt-edit-error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-primary" id="vnt-edit-save">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vntPushModal" tabindex="-1" aria-labelledby="vntPushModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3a5f 0%, #2c6ed5 100%); color: #fff;">
                    <h5 class="modal-title" id="vntPushModalLabel">Push variation name</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2" id="vnt-push-sku-hint"></div>
                    <div class="small text-muted mb-2">Active channels (same list as Active Channels). Tick the ones with a variation-name API. Your selection is saved automatically.</div>
                    <div class="d-flex gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="vnt-push-select-all">Select all with API</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="vnt-push-select-none">Clear</button>
                    </div>
                    <div class="vnt-channel-list" id="vnt-push-channel-list"></div>
                    <div class="text-danger small mt-2 d-none" id="vnt-push-error"></div>
                    <div class="vnt-push-results mt-2 d-none" id="vnt-push-results"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="vnt-push-run">
                        <i class="bi bi-send me-1"></i>Push
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dataUrl = @json(route('variation.name.thumbnail.data'));
            const saveUrl = @json(route('variation.name.thumbnail.save'));
            const templateUrl = @json(route('variation.name.thumbnail.template'));
            const importUrl = @json(route('variation.name.thumbnail.import'));
            const saveBulkUrl = @json(route('variation.name.thumbnail.save.bulk'));
            const pushChannelsUrl = @json(route('variation.name.thumbnail.push.channels'));
            const pushChannelsSaveUrl = @json(route('variation.name.thumbnail.push.channels.save'));
            const pushUrl = @json(route('variation.name.thumbnail.push'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            let table = null;
            let editIds = [];
            let pushIds = [];
            let pushChannelsCache = [];

            function escapeHtml(text) {
                if (text == null) return '';
                const div = document.createElement('div');
                div.textContent = String(text);
                return div.innerHTML;
            }

            function updateSelectedCount() {
                const el = document.getElementById('vnt-selected-count');
                if (el && table) el.textContent = String(table.getSelectedData().length);
            }

            function setImportStatus(text, ok) {
                const el = document.getElementById('vnt-import-status');
                if (!el) return;
                el.textContent = text || '';
                el.classList.remove('text-success', 'text-danger', 'text-muted');
                if (!text) return;
                el.classList.add(ok === true ? 'text-success' : (ok === false ? 'text-danger' : 'text-muted'));
            }

            document.getElementById('vnt-download-btn')?.addEventListener('click', function() {
                window.location.href = templateUrl;
            });

            document.getElementById('vnt-upload-btn')?.addEventListener('click', function() {
                document.getElementById('vnt-upload-file')?.click();
            });

            document.getElementById('vnt-upload-file')?.addEventListener('change', function() {
                const input = this;
                const file = input.files && input.files[0];
                input.value = '';
                if (!file) return;
                const fd = new FormData();
                fd.append('file', file);
                setImportStatus('Uploading…', null);
                fetch(importUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: fd
                }).then(function(res) {
                    return res.json().then(function(json) {
                        if (!res.ok) throw new Error((json && json.message) || 'Upload failed');
                        return json;
                    });
                }).then(function(json) {
                    setImportStatus(json.message || 'Imported.', true);
                    if (table) table.replaceData();
                }).catch(function(err) {
                    setImportStatus(err.message || 'Upload failed', false);
                });
            });

            function setupImageHover() {
                const host = document.getElementById('vnt-tabulator');
                if (!host) return;
                let previewEl = null;

                function getPreview() {
                    if (!previewEl) {
                        previewEl = document.createElement('div');
                        previewEl.id = 'vnt-img-hover-preview';
                        previewEl.appendChild(document.createElement('img'));
                        document.body.appendChild(previewEl);
                    }
                    return previewEl;
                }

                function hidePreview() {
                    if (previewEl) previewEl.style.display = 'none';
                }

                function showPreview(img, clientX, clientY) {
                    const src = img.getAttribute('src');
                    if (!src) return;
                    const el = getPreview();
                    el.querySelector('img').src = src;
                    el.style.display = 'block';
                    const margin = 14;
                    const pad = 8;
                    requestAnimationFrame(function() {
                        const w = el.offsetWidth || 200;
                        const h = el.offsetHeight || 200;
                        let left = clientX + margin;
                        let top = clientY + margin;
                        if (left + w > window.innerWidth - pad) left = Math.max(pad, window.innerWidth - w - pad);
                        if (top + h > window.innerHeight - pad) top = Math.max(pad, window.innerHeight - h - pad);
                        el.style.left = left + 'px';
                        el.style.top = top + 'px';
                    });
                }

                host.addEventListener('mouseover', function(e) {
                    const img = e.target.closest('.vnt-thumb');
                    if (img && host.contains(img)) showPreview(img, e.clientX, e.clientY);
                });
                host.addEventListener('mousemove', function(e) {
                    const img = e.target.closest('.vnt-thumb');
                    if (img && host.contains(img) && previewEl && previewEl.style.display === 'block') {
                        showPreview(img, e.clientX, e.clientY);
                    }
                });
                host.addEventListener('mouseout', function(e) {
                    if (e.target.closest('.vnt-thumb')) hidePreview();
                });
                window.addEventListener('scroll', hidePreview, true);
            }

            table = new Tabulator('#vnt-tabulator', {
                ajaxURL: dataUrl,
                ajaxResponse: function(_url, _params, response) {
                    return (response && Array.isArray(response.data)) ? response.data : [];
                },
                index: 'id',
                layout: 'fitColumns',
                height: '100%',
                placeholder: 'No products found',
                selectableRows: true,
                columnDefaults: {
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    vertAlign: 'middle'
                },
                columns: [
                    {
                        formatter: 'rowSelection',
                        titleFormatter: 'rowSelection',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 44,
                        frozen: true
                    },
                    {
                        title: 'Image',
                        field: 'image',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 64,
                        formatter: function(cell) {
                            const src = String(cell.getValue() || '').trim();
                            if (!src) {
                                return '<span class="vnt-thumb-empty" title="No image">—</span>';
                            }
                            return '<img class="vnt-thumb" src="' + escapeHtml(src) + '" alt="">';
                        }
                    },
                    {
                        title: 'Parent',
                        field: 'parent',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Parent',
                        minWidth: 90,
                        formatter: function(cell) {
                            const v = String(cell.getValue() || '').trim();
                            return v ? escapeHtml(v) : '—';
                        }
                    },
                    {
                        title: 'SKU',
                        field: 'sku',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'SKU',
                        minWidth: 120,
                        formatter: function(cell) {
                            const v = String(cell.getValue() || '').trim();
                            return v ? escapeHtml(v) : '—';
                        }
                    },
                    {
                        title: 'Variation Name',
                        field: 'variation_name',
                        editor: 'input',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Variation name',
                        minWidth: 160,
                        formatter: function(cell) {
                            const v = String(cell.getValue() || '').trim();
                            return v ? escapeHtml(v) : '';
                        }
                    },
                    {
                        title: 'Thumbnail',
                        field: 'thumbnail',
                        editor: 'input',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Thumbnail',
                        minWidth: 160,
                        formatter: function(cell) {
                            const v = String(cell.getValue() || '').trim();
                            return v ? escapeHtml(v) : '';
                        }
                    },
                    {
                        title: 'INV',
                        field: 'inv',
                        hozAlign: 'center',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'INV',
                        width: 72,
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === 0 || v === '0') return '0';
                            if (v === null || v === undefined || v === '') return '—';
                            return escapeHtml(String(v));
                        }
                    },
                    {
                        title: 'Edit',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 52,
                        formatter: function() {
                            return '<button type="button" class="btn btn-sm btn-outline-primary vnt-edit-btn" title="Edit"><i class="bi bi-pencil"></i></button>';
                        },
                        cellClick: function(e, cell) {
                            e.preventDefault();
                            e.stopPropagation();
                            openEditModal(cell.getRow().getData());
                        }
                    },
                    {
                        title: 'Push',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 52,
                        formatter: function() {
                            return '<button type="button" class="btn btn-sm btn-outline-success vnt-push-btn" title="Push variation name"><i class="bi bi-send"></i></button>';
                        },
                        cellClick: function(e, cell) {
                            e.preventDefault();
                            e.stopPropagation();
                            openPushModal(cell.getRow().getData());
                        }
                    }
                ],
                rowFormatter: function(row) {
                    const el = row.getElement();
                    if (row.getData().is_parent) {
                        el.classList.add('vnt-parent-row');
                    } else {
                        el.classList.remove('vnt-parent-row');
                    }
                }
            });

            table.on('rowSelectionChanged', updateSelectedCount);
            table.on('cellEdited', function(cell) {
                const field = cell.getField();
                if (field !== 'variation_name' && field !== 'thumbnail') return;
                const row = cell.getRow().getData();
                const payload = { id: row.id };
                payload[field] = cell.getValue();
                fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify(payload)
                }).then(function(res) {
                    if (!res.ok) throw new Error('Save failed');
                    return res.json();
                }).then(function(json) {
                    const saved = json && json[field] != null ? String(json[field]) : String(cell.getValue() || '');
                    if (String(cell.getValue() || '') !== saved) {
                        cell.setValue(saved, false);
                    }
                }).catch(function() {
                    cell.restoreOldValue();
                });
            });

            function rowsForEdit(clicked) {
                const selected = table ? table.getSelectedData() : [];
                const ids = selected.map(function(r) { return r.id; });
                if (selected.length > 1 && ids.indexOf(clicked.id) !== -1) {
                    return selected;
                }
                return [clicked];
            }

            function openEditModal(clicked) {
                const rows = rowsForEdit(clicked);
                editIds = rows.map(function(r) { return r.id; });
                const bulk = rows.length > 1;
                const err = document.getElementById('vnt-edit-error');
                if (err) {
                    err.classList.add('d-none');
                    err.textContent = '';
                }
                document.getElementById('vntEditModalLabel').textContent = bulk
                    ? ('Edit ' + rows.length + ' rows')
                    : 'Edit row';
                document.getElementById('vnt-edit-single-meta').classList.toggle('d-none', bulk);
                document.getElementById('vnt-edit-bulk-meta').classList.toggle('d-none', !bulk);
                document.querySelectorAll('.vnt-apply-flag').forEach(function(cb) {
                    cb.checked = !bulk;
                    cb.classList.toggle('d-none', !bulk);
                });

                if (bulk) {
                    document.getElementById('vnt-edit-bulk-count').textContent = rows.length + ' SKUs selected';
                    const chips = document.getElementById('vnt-edit-sku-chips');
                    const shown = rows.slice(0, 12);
                    chips.innerHTML = shown.map(function(r) {
                        return '<span class="vnt-sku-chip">' + escapeHtml(r.sku || '') + '</span>';
                    }).join('') + (rows.length > 12 ? '<span class="vnt-sku-chip">+' + (rows.length - 12) + ' more</span>' : '');
                    document.getElementById('vnt-edit-name').value = '';
                    document.getElementById('vnt-edit-thumb').value = '';
                    document.getElementById('vnt-edit-inv').value = '';
                    document.getElementById('vnt-edit-name').placeholder = 'Apply to all selected';
                    document.getElementById('vnt-edit-thumb').placeholder = 'Apply to all selected';
                    document.getElementById('vnt-edit-inv').placeholder = 'Apply to all selected';
                } else {
                    const row = rows[0];
                    document.getElementById('vnt-edit-parent').value = row.parent || '';
                    document.getElementById('vnt-edit-sku').value = row.sku || '';
                    document.getElementById('vnt-edit-name').value = row.variation_name || '';
                    document.getElementById('vnt-edit-thumb').value = row.thumbnail || '';
                    document.getElementById('vnt-edit-inv').value = (row.inv === 0 || row.inv) ? String(row.inv) : '';
                    document.getElementById('vnt-edit-name').placeholder = '';
                    document.getElementById('vnt-edit-thumb').placeholder = '';
                    document.getElementById('vnt-edit-inv').placeholder = '';
                }

                bootstrap.Modal.getOrCreateInstance(document.getElementById('vntEditModal')).show();
            }

            document.getElementById('vntEditForm')?.addEventListener('submit', function(e) {
                e.preventDefault();
                const err = document.getElementById('vnt-edit-error');
                const bulk = editIds.length > 1;
                const applyName = document.getElementById('vnt-apply-name').checked;
                const applyThumb = document.getElementById('vnt-apply-thumb').checked;
                const applyInv = document.getElementById('vnt-apply-inv').checked;
                if (bulk && !applyName && !applyThumb && !applyInv) {
                    err.textContent = 'Check at least one field to update.';
                    err.classList.remove('d-none');
                    return;
                }

                const saveBtn = document.getElementById('vnt-edit-save');
                saveBtn.disabled = true;
                const name = document.getElementById('vnt-edit-name').value;
                const thumb = document.getElementById('vnt-edit-thumb').value;
                const inv = document.getElementById('vnt-edit-inv').value;
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                };

                const req = bulk
                    ? fetch(saveBulkUrl, {
                        method: 'POST',
                        headers: headers,
                        body: JSON.stringify({
                            ids: editIds,
                            variation_name: name,
                            thumbnail: thumb,
                            inv: inv === '' ? null : inv,
                            update_variation_name: applyName,
                            update_thumbnail: applyThumb,
                            update_inv: applyInv
                        })
                    })
                    : fetch(saveUrl, {
                        method: 'POST',
                        headers: headers,
                        body: JSON.stringify({
                            id: editIds[0],
                            variation_name: name,
                            thumbnail: thumb,
                            inv: inv === '' ? null : inv
                        })
                    });

                req.then(function(res) {
                    return res.json().then(function(json) {
                        if (!res.ok) throw new Error((json && json.message) || 'Save failed');
                        return json;
                    });
                }).then(function(json) {
                    const patch = {};
                    if (!bulk || applyName) patch.variation_name = name;
                    if (!bulk || applyThumb) patch.thumbnail = thumb;
                    if (!bulk || applyInv) {
                        if (inv !== '') patch.inv = Number(inv);
                        if (json && json.inv != null) patch.inv = json.inv;
                    }
                    editIds.forEach(function(id) {
                        const row = table && (table.getRow(Number(id)) || table.getRow(id));
                        if (row) row.update(patch);
                    });
                    bootstrap.Modal.getInstance(document.getElementById('vntEditModal'))?.hide();
                    setImportStatus(json.message || (bulk ? ('Updated ' + editIds.length + ' rows.') : 'Saved.'), true);
                }).catch(function(ex) {
                    err.textContent = ex.message || 'Save failed';
                    err.classList.remove('d-none');
                }).finally(function() {
                    saveBtn.disabled = false;
                });
            });

            setupImageHover();

            function selectedPushChannels() {
                return Array.from(document.querySelectorAll('#vnt-push-channel-list input[type="checkbox"]:checked:not(:disabled)')).map(function(el) {
                    return el.value;
                });
            }

            let autosaveTimer = null;
            function autosavePushChannels() {
                const channels = selectedPushChannels();
                clearTimeout(autosaveTimer);
                autosaveTimer = setTimeout(function() {
                    fetch(pushChannelsSaveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({ channels: channels })
                    }).catch(function() {});
                }, 250);
            }

            function renderPushChannels(channels, selected) {
                const host = document.getElementById('vnt-push-channel-list');
                const selectedSet = {};
                (selected || []).forEach(function(k) { selectedSet[String(k)] = true; });
                host.innerHTML = (channels || []).map(function(ch) {
                    const key = escapeHtml(ch.key || '');
                    const checked = (selectedSet[ch.key] && ch.configured) ? ' checked' : '';
                    const off = ch.configured ? '' : ' is-off';
                    const disabled = ch.configured ? '' : ' disabled';
                    const badge = ch.logo
                        ? '<img class="vnt-channel-logo" src="' + escapeHtml(ch.logo) + '" alt="">'
                        : '<span class="vnt-channel-tile ' + escapeHtml(ch.cls || 'btn-secondary') + '">' + escapeHtml(ch.short || '') + '</span>';
                    const apiNote = ch.configured ? '' : ' <span class="text-muted">(no variation API)</span>';
                    return '<label class="vnt-channel-item' + off + '">' +
                        '<input type="checkbox" class="form-check-input m-0" value="' + key + '"' + checked + disabled + '>' +
                        badge +
                        '<span class="small">' + escapeHtml(ch.label || ch.channel || '') + apiNote + '</span>' +
                        '</label>';
                }).join('') || '<div class="text-muted small">No active channels found.</div>';
                host.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                    cb.addEventListener('change', autosavePushChannels);
                });
            }

            function openPushModal(clicked) {
                const rows = rowsForEdit(clicked);
                pushIds = rows.map(function(r) { return r.id; });
                document.getElementById('vntPushModalLabel').textContent = rows.length > 1
                    ? ('Push variation name · ' + rows.length + ' SKUs')
                    : 'Push variation name';
                document.getElementById('vnt-push-sku-hint').textContent = rows.length === 1
                    ? ('SKU: ' + (rows[0].sku || '') + (rows[0].variation_name ? ' · ' + rows[0].variation_name : ''))
                    : (rows.length + ' selected SKUs');
                document.getElementById('vnt-push-error').classList.add('d-none');
                document.getElementById('vnt-push-results').classList.add('d-none');
                document.getElementById('vnt-push-results').innerHTML = '';
                const list = document.getElementById('vnt-push-channel-list');
                list.innerHTML = '<div class="text-muted small">Loading channels…</div>';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('vntPushModal')).show();
                fetch(pushChannelsUrl, { headers: { 'Accept': 'application/json' } })
                    .then(function(res) { return res.json(); })
                    .then(function(json) {
                        pushChannelsCache = json.channels || [];
                        renderPushChannels(pushChannelsCache, json.selected || []);
                    })
                    .catch(function() {
                        list.innerHTML = '<div class="text-danger small">Could not load active channels.</div>';
                    });
            }

            document.getElementById('vnt-push-select-all')?.addEventListener('click', function() {
                document.querySelectorAll('#vnt-push-channel-list input[type="checkbox"]:not(:disabled)').forEach(function(cb) {
                    cb.checked = true;
                });
                autosavePushChannels();
            });
            document.getElementById('vnt-push-select-none')?.addEventListener('click', function() {
                document.querySelectorAll('#vnt-push-channel-list input[type="checkbox"]').forEach(function(cb) {
                    cb.checked = false;
                });
                autosavePushChannels();
            });

            document.getElementById('vnt-push-run')?.addEventListener('click', function() {
                const channels = selectedPushChannels();
                const err = document.getElementById('vnt-push-error');
                const resultsEl = document.getElementById('vnt-push-results');
                if (!channels.length) {
                    err.textContent = 'Select at least one channel.';
                    err.classList.remove('d-none');
                    return;
                }
                if (!pushIds.length) {
                    err.textContent = 'No SKUs to push.';
                    err.classList.remove('d-none');
                    return;
                }
                err.classList.add('d-none');
                const btn = document.getElementById('vnt-push-run');
                btn.disabled = true;
                resultsEl.classList.remove('d-none');
                resultsEl.innerHTML = '<div class="text-muted">Pushing…</div>';
                fetch(pushUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({ ids: pushIds, channels: channels })
                }).then(function(res) {
                    return res.json().then(function(json) {
                        if (!res.ok) throw new Error((json && json.message) || 'Push failed');
                        return json;
                    });
                }).then(function(json) {
                    const rows = json.results || [];
                    resultsEl.innerHTML = rows.map(function(r) {
                        const cls = r.success ? 'text-success' : 'text-danger';
                        return '<div class="' + cls + '">' + escapeHtml(r.sku) + ' · ' + escapeHtml(r.channel) + ' — ' + escapeHtml(r.message || '') + '</div>';
                    }).join('') || escapeHtml(json.message || 'Done.');
                    setImportStatus(json.message || 'Push finished.', json.fail === 0);
                }).catch(function(ex) {
                    err.textContent = ex.message || 'Push failed';
                    err.classList.remove('d-none');
                    resultsEl.classList.add('d-none');
                }).finally(function() {
                    btn.disabled = false;
                });
            });
        });
    </script>
@endsection
