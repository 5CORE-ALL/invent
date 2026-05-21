@php
    /**
     * This blade powers three pages — All / Video / Carousal — distinguished
     * by the controller-supplied `$pageType` ('all' | 'video' | 'carousal').
     * The Video and Carousal pages restrict the Ad Type dropdown and pass
     * `?type=…` to the data endpoint so the server filters server-side.
     */
    $pageType        = $pageType        ?? 'all';
    $pageTitle       = $pageTitle       ?? 'Facebook All Ads Sheet';
    $pageSubtitle    = $pageSubtitle    ?? 'Generic CSV / Excel / TSV importer — upload any sheet and view it as a table';
    $allowedAdTypes  = $allowedAdTypes  ?? ['GROUP VIDEO', 'GROUP CAROUSAL', 'PARENT VIDEO', 'PARENT CAROUSAL'];
@endphp

@extends('layouts.vertical', ['title' => $pageTitle, 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .faas-meta { font-size: 0.8rem; color: #6b7280; }
        .faas-meta strong { color: #374151; }
        .faas-batch-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 0.75rem;
            font-weight: 600;
        }
        #facebook-all-ads-table .tabulator-header { background: #f9fafb; }
        #facebook-all-ads-table .tabulator-col-title { font-weight: 600; color: #1f2937; }
        .tabulator-paginator label { margin-right: 5px; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $pageTitle,
        'sub_title'  => $pageSubtitle,
    ])

    <div class="toast-container"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="mb-0">
                        <i class="fab fa-facebook-f me-1 text-primary"></i>
                        Imported Rows
                        <span id="faasBatchPill" class="faas-batch-pill ms-2 d-none">
                            <i class="fas fa-database"></i>
                            <span id="faasBatchName">—</span>
                        </span>
                    </h5>

                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span id="faasRowCount" class="faas-meta">—</span>

                        <select id="faasBatchSelect" class="form-select form-select-sm" style="max-width:380px;">
                            <option value="__merged__">🔗 Merged · Campaign + Spend + Sales</option>
                        </select>

                        <button type="button"
                                id="faasReloadBtn"
                                class="btn btn-sm btn-outline-secondary"
                                title="Refresh table">
                            <i class="fas fa-sync-alt"></i>
                        </button>

                        <button type="button"
                                class="btn btn-sm btn-success"
                                data-bs-toggle="modal"
                                data-bs-target="#faasUploadModal">
                            <i class="fas fa-cloud-upload-alt me-1"></i>
                            Upload Sheet
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div id="faas-table-wrapper" style="height: calc(100vh - 220px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text"
                               id="faas-search"
                               class="form-control form-control-sm"
                               placeholder="Search across all columns…">
                    </div>
                    <div id="facebook-all-ads-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

{{-- ── Upload Sheet modal ─────────────────────────────────────────── --}}
<div class="modal fade"
     id="faasUploadModal"
     tabindex="-1"
     aria-labelledby="faasUploadModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#1877f2,#0d5cb6);color:#fff;">
                <h5 class="modal-title" id="faasUploadModalLabel">
                    <i class="fas fa-cloud-upload-alt me-2"></i>Upload Sheet
                </h5>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="faasUploadForm" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-2">
                        <div class="col-12 col-md-5">
                            <label for="faasUploadType" class="form-label small fw-semibold">Upload type</label>
                            <select id="faasUploadType" name="upload_type" class="form-select" required>
                                <option value="">— select —</option>
                                <option value="campaign">📣 Campaign</option>
                                <option value="spend">💰 Spend</option>
                                <option value="sales">🛒 Sales</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-7">
                            <label for="faasFile" class="form-label small fw-semibold">Choose a file</label>
                            <input type="file"
                                   name="file"
                                   id="faasFile"
                                   class="form-control"
                                   required>
                        </div>
                    </div>
                    <div class="faas-meta mt-2">
                        Accepts <strong>CSV, TSV, TXT, XLSX, XLS, ODS</strong> — or anything tab / comma /
                        semicolon / pipe separated. First non-empty row = header.
                    </div>
                    <div id="faasUploadStatus" class="mt-3"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button"
                        id="faasUploadBtn"
                        class="btn btn-primary">
                    <i class="fas fa-upload me-1"></i>
                    Upload
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                        || document.querySelector('input[name="_token"]')?.value
                        || '';

        // Page-specific config (set by the controller).
        const PAGE_TYPE = @json($pageType);
        // Dropdown choices on this page. On Video/Carousal pages this is a
        // restricted subset so users can't pick a value that would make the
        // row disappear from the page they're currently looking at.
        const AD_TYPES  = @json($allowedAdTypes);
        const AD_TYPE_COLORS = {
            'GROUP VIDEO':     { bg: '#dbeafe', fg: '#1e40af' },
            'GROUP CAROUSAL':  { bg: '#dcfce7', fg: '#166534' },
            'PARENT VIDEO':    { bg: '#fef3c7', fg: '#92400e' },
            'PARENT CAROUSAL': { bg: '#fce7f3', fg: '#9d174d' },
        };

        let tabulator = null;

        // Render the Ad Type cell as a coloured pill so values are scannable.
        function formatAdTypeCell(cell) {
            const v = cell.getValue();
            if (!v) {
                return '<span class="text-muted small">— Select —</span>';
            }
            const c = AD_TYPE_COLORS[v] || { bg: '#e5e7eb', fg: '#374151' };
            return `<span style="display:inline-block;padding:2px 10px;border-radius:999px;`
                 + `background:${c.bg};color:${c.fg};font-size:0.75rem;font-weight:600;">${v}</span>`;
        }

        // Persist a row's chosen Ad Type to the backend on edit.
        function onAdTypeEdited(cell) {
            const row     = cell.getRow();
            const id      = row.getData()._id;
            const value   = cell.getValue() || '';
            const oldVal  = cell.getOldValue() || '';
            if (!id) return;

            const fd = new FormData();
            fd.append('ad_type', value);
            fd.append('_token',  csrfToken);

            fetch(`/facebook-all-ads-sheet/${id}/ad-type`, {
                method:      'POST',
                credentials: 'same-origin',
                headers:     { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body:        fd,
            })
            .then(async r => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok || !data.success) throw new Error(data.message || `HTTP ${r.status}`);
                return data;
            })
            .catch(err => {
                // Revert the cell on save failure.
                row.update({ ad_type: oldVal });
                alert('Failed to save Ad Type: ' + err.message);
            });
        }

        function showStatus(html, type = 'info') {
            const cls = {
                info:    'alert-info',
                success: 'alert-success',
                error:   'alert-danger',
                warn:    'alert-warning',
            }[type] || 'alert-info';
            document.getElementById('faasUploadStatus').innerHTML =
                `<div class="alert ${cls} mb-0 py-2 small">${html}</div>`;
        }

        function clearStatus() {
            document.getElementById('faasUploadStatus').innerHTML = '';
        }

        function uploadTypeLabel(t) {
            if (t === 'campaign') return '📣 Campaign';
            if (t === 'spend')    return '💰 Spend';
            if (t === 'sales')    return '🛒 Sales';
            if (t === 'merged')   return '🔗 Merged';
            return '';
        }

        function updateBatchPill(batch) {
            const pill = document.getElementById('faasBatchPill');
            const name = document.getElementById('faasBatchName');
            const counter = document.getElementById('faasRowCount');
            if (!batch || !batch.id) {
                pill.classList.add('d-none');
                counter.textContent = '—';
                return;
            }
            pill.classList.remove('d-none');
            const fname = batch.source_filename || '(unnamed)';
            const dt    = batch.uploaded_at ? new Date(batch.uploaded_at).toLocaleString() : '';
            const tlbl  = uploadTypeLabel(batch.upload_type);
            name.textContent = `${tlbl ? tlbl + ' · ' : ''}${fname}${dt ? ' · ' + dt : ''}`;
            const rowsLabel = `${batch.row_count} row${batch.row_count === 1 ? '' : 's'}`;
            const unmatched = (batch.unmatched && batch.unmatched > 0)
                ? ` · ${batch.unmatched} unmatched`
                : '';
            counter.textContent = rowsLabel + unmatched;
        }

        function loadBatches(selectedId = '__merged__') {
            return fetch('/facebook-all-ads-sheet/batches', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(resp => {
                    const sel = document.getElementById('faasBatchSelect');
                    // Always-on Merged option at the top.
                    sel.innerHTML = '<option value="__merged__">🔗 Merged · Campaign + Spend + Sales</option>';
                    (resp.data || []).forEach(b => {
                        const dt   = b.uploaded_at ? new Date(b.uploaded_at).toLocaleString() : '';
                        const tlbl = uploadTypeLabel(b.upload_type);
                        const opt  = document.createElement('option');
                        opt.value  = b.import_batch_id;
                        opt.textContent =
                            `${tlbl ? tlbl + ' · ' : ''}${b.source_filename || b.import_batch_id.slice(0,8)} · ${b.row_count} rows${dt ? ' · ' + dt : ''}`;
                        if (b.import_batch_id === selectedId) opt.selected = true;
                        sel.appendChild(opt);
                    });
                    // Default selection: Merged view, unless caller asked otherwise.
                    if (selectedId === '__merged__' || !selectedId) {
                        sel.value = '__merged__';
                    }
                });
        }

        function loadTable(batchId = '__merged__') {
            const params = new URLSearchParams();
            // '__merged__' means "join latest Campaign + latest Spend" — send
            // `view=merged` instead of a real batch id.
            if (batchId === '__merged__' || !batchId) {
                params.set('view', 'merged');
            } else {
                params.set('batch_id', batchId);
            }
            if (PAGE_TYPE !== 'all') params.set('type', PAGE_TYPE);
            const url = '/facebook-all-ads-sheet/data?' + params.toString();

            return fetch(url, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(resp => {
                    if (!resp.success) {
                        showStatus('Failed to load data.', 'error');
                        return;
                    }
                    updateBatchPill(resp.batch);

                    const cols = (resp.columns || []).map(c => ({
                        title:        c.title,
                        field:        c.field,
                        headerFilter: 'input',
                        headerSort:   true,
                        widthGrow:    1,
                        minWidth:     120,
                        formatter:    'plaintext',
                    }));
                    // Prepend row index + Ad Type dropdown columns.
                    cols.unshift(
                        {
                            title: '#',
                            field: '_row_index',
                            width: 60,
                            headerSort: true,
                            hozAlign: 'center',
                        },
                        {
                            title:        'Ad Type',
                            field:        'ad_type',
                            width:        180,
                            headerFilter: 'list',
                            headerFilterParams: {
                                values:      { '': '— all —', ...Object.fromEntries(AD_TYPES.map(v => [v, v])) },
                                clearable:   true,
                            },
                            editor:       'list',
                            editorParams: {
                                values:        ['', ...AD_TYPES],
                                clearable:     true,
                                autocomplete:  true,
                                listOnEmpty:   true,
                                placeholderEmpty: '— Select —',
                            },
                            cellEdited:   onAdTypeEdited,
                            formatter:    formatAdTypeCell,
                        }
                    );

                    if (tabulator) {
                        tabulator.destroy();
                    }
                    tabulator = new Tabulator('#facebook-all-ads-table', {
                        data:                   resp.data || [],
                        columns:                cols,
                        layout:                 'fitDataStretch',
                        // Let the table fill #faas-table-wrapper which itself
                        // is sized to the viewport. Same pattern as
                        // /topdawg/sales-dashboard.
                        height:                 '100%',
                        pagination:             true,
                        paginationSize:         100,
                        paginationSizeSelector: [25, 50, 100, 250, 500],
                        paginationCounter:      'rows',
                        movableColumns:         true,
                        resizableColumns:       true,
                        clipboard:              true,
                        placeholder:            resp.batch
                            ? 'No rows in this upload.'
                            : 'No uploads yet — click Upload Sheet to get started.',
                    });

                    // Re-bind the search box every time the table is rebuilt.
                    bindSearch();
                });
        }

        function bindSearch() {
            const input = document.getElementById('faas-search');
            if (!input || !tabulator) return;
            input.oninput = function () {
                const v = (this.value || '').toLowerCase().trim();
                if (!v) { tabulator.clearFilter(); return; }
                tabulator.setFilter(function (row) {
                    for (const k in row) {
                        if (k.startsWith('_')) continue;
                        const cell = row[k];
                        if (cell != null && String(cell).toLowerCase().includes(v)) return true;
                    }
                    return false;
                });
            };
        }

        // ── Wiring ──────────────────────────────────────────────
        function submitUpload() {
            const fileInput = document.getElementById('faasFile');
            const typeSel   = document.getElementById('faasUploadType');
            const uploadType = typeSel.value;
            if (!uploadType) {
                showStatus('Please pick an upload type (Campaign / Spend / Sales).', 'warn');
                return;
            }
            if (!fileInput.files.length) {
                showStatus('Please choose a file first.', 'warn');
                return;
            }

            const fd = new FormData();
            fd.append('file',        fileInput.files[0]);
            fd.append('upload_type', uploadType);
            fd.append('_token',      csrfToken);

            const btn = document.getElementById('faasUploadBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading…';
            showStatus(`Parsing and importing as <strong>${uploadType}</strong> — this may take a moment for large files…`, 'info');

            fetch('/facebook-all-ads-sheet/upload', {
                method:      'POST',
                credentials: 'same-origin',
                headers:     { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body:        fd,
            })
            .then(async r => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok || !data.success) {
                    throw new Error(data.message || `HTTP ${r.status}`);
                }
                return data;
            })
            .then(resp => {
                fileInput.value = '';
                // Close the modal once the import succeeded.
                const modalEl = document.getElementById('faasUploadModal');
                if (modalEl && window.bootstrap?.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                clearStatus();
                // After any upload, return to the Merged view — that's where
                // the user expects to see Campaign + Spend together. The
                // newly-uploaded batch is automatically included because
                // Merged pulls the latest batch of each type.
                return loadBatches('__merged__').then(() => loadTable('__merged__'));
            })
            .catch(err => {
                showStatus('Upload failed: ' + err.message, 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload';
            });
        }

        document.getElementById('faasUploadBtn').addEventListener('click', submitUpload);
        document.getElementById('faasUploadForm').addEventListener('submit', function (e) {
            // Pressing Enter inside the form should also trigger upload.
            e.preventDefault();
            submitUpload();
        });

        // Reset modal state when it's closed (so reopening shows a clean slate).
        document.getElementById('faasUploadModal')?.addEventListener('hidden.bs.modal', function () {
            clearStatus();
            document.getElementById('faasFile').value = '';
            document.getElementById('faasUploadType').value = '';
        });

        document.getElementById('faasBatchSelect').addEventListener('change', function () {
            loadTable(this.value);
        });

        document.getElementById('faasReloadBtn').addEventListener('click', function () {
            const sel = document.getElementById('faasBatchSelect');
            loadBatches(sel.value).then(() => loadTable(sel.value));
        });

        // Initial load
        loadBatches().then(() => loadTable());
    })();
</script>
@endsection
