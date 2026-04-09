@extends('layouts.vertical', ['title' => $title])

@section('css')
<style>
    .rm-card { border-radius: 12px; border: 1px solid rgba(0,0,0,.08); overflow: hidden; transition: .15s; }
    .rm-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,.08); }
    .rm-thumb { height: 140px; background: #f4f6f8; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .rm-thumb img { max-height:100%; max-width:100%; object-fit:contain; }
    .rm-dropzone { border: 2px dashed var(--bs-primary); border-radius: 12px; padding: 2rem; text-align: center; background: rgba(var(--bs-primary-rgb),.04); cursor: pointer; }
    .rm-dropzone.dragover { background: rgba(var(--bs-primary-rgb),.12); }
    #rmProgress { display: none; }
</style>
@endsection

@section('content')
    @include('layouts.shared.page-title', ['page_title' => $title, 'sub_title' => 'Resources Master'])

    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <a href="{{ route('resources-master.dashboard') }}" class="btn btn-soft-secondary btn-sm"><i class="ri-arrow-left-line"></i> Dashboard</a>
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-sm btn-outline-primary active" id="rmViewCards"><i class="ri-layout-grid-line"></i></button>
            <button type="button" class="btn btn-sm btn-outline-primary" id="rmViewTable"><i class="ri-list-unordered"></i></button>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted">Search</label>
                    <input type="search" class="form-control form-control-sm" id="rmSearch" placeholder="Title or description">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Department</label>
                    <select class="form-select form-select-sm" id="rmFilterDept">
                        <option value="">All</option>
                        @foreach($departments as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Tag</label>
                    <select class="form-select form-select-sm" id="rmFilterTag">
                        <option value="">All</option>
                        @foreach($tags as $t)
                            <option value="{{ $t->id }}">{{ $t->tag_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Type</label>
                    <select class="form-select form-select-sm" id="rmFilterType">
                        <option value="">All</option>
                        <option value="pdf">PDF</option>
                        <option value="doc">Document</option>
                        <option value="spreadsheet">Spreadsheet</option>
                        <option value="presentation">Presentation</option>
                        <option value="image">Image</option>
                        <option value="video">Video</option>
                        <option value="link">Link</option>
                        <option value="checklist">Checklist</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small text-muted">From</label>
                    <input type="date" class="form-control form-control-sm" id="rmDateFrom">
                </div>
                <div class="col-md-1">
                    <label class="form-label small text-muted">To</label>
                    <input type="date" class="form-control form-control-sm" id="rmDateTo">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-primary btn-sm w-100" id="rmApplyFilters">Apply</button>
                </div>
            </div>
        </div>
    </div>

    @if($canManage)
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-2">
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#rmUploadModal"><i class="ri-upload-2-line"></i> Add resource</button>
                <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#rmBulkModal"><i class="ri-stack-line"></i> Bulk upload</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#rmCsvModal">CSV import</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#rmZipModal">ZIP import</button>
            </div>
        </div>
    </div>
    @endif

    <div class="progress mb-3" id="rmProgress">
        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
    </div>

    <div id="rmCardView" class="row g-3"></div>
    <div id="rmTableView" class="d-none">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Departments</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="rmTableBody"></tbody>
            </table>
        </div>
    </div>
    <nav id="rmPagination" class="mt-3 d-none"></nav>

    {{-- Upload single --}}
    <div class="modal fade" id="rmUploadModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="rmUploadForm">
                        <div class="mb-2">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required maxlength="500">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">File</label>
                            <input type="file" name="file" id="rmFileInput" class="form-control">
                            <div class="mt-2" id="rmFilePreview"></div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Or external link (YouTube / Google Drive)</label>
                            <input type="url" name="external_link" class="form-control" placeholder="https://">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Departments</label>
                                <select name="department_ids[]" class="form-select" multiple size="4">
                                    @foreach($departments as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Tags</label>
                                <select name="tag_ids[]" class="form-select" multiple size="4">
                                    @foreach($tags as $t)
                                        <option value="{{ $t->id }}">{{ $t->tag_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Duration (seconds, videos)</label>
                            <input type="number" name="duration_seconds" class="form-control" min="0">
                        </div>
                        <input type="hidden" name="category" value="{{ $section }}">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="rmSubmitUpload">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Bulk --}}
    <div class="modal fade" id="rmBulkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Bulk upload</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="rm-dropzone" id="rmBulkDrop">
                        <p class="mb-1"><i class="ri-drag-drop-line fs-3"></i></p>
                        <p class="mb-0 small text-muted">Drop files here or click to select (max 50)</p>
                        <input type="file" id="rmBulkFiles" class="d-none" multiple>
                    </div>
                    <p class="small text-muted mt-2" id="rmBulkList"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary btn-sm" id="rmBulkSubmit">Upload all</button>
                </div>
            </div>
        </div>
    </div>

    {{-- CSV --}}
    <div class="modal fade" id="rmCsvModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">CSV metadata import</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="small text-muted">Headers: title, description, external_link or link, status</p>
                    <input type="file" id="rmCsvFile" accept=".csv,.txt" class="form-control">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary btn-sm" id="rmCsvSubmit">Import</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ZIP --}}
    <div class="modal fade" id="rmZipModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">ZIP bulk extract</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="file" id="rmZipFile" accept=".zip,application/zip" class="form-control">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary btn-sm" id="rmZipSubmit">Import</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rmLightbox" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-transparent border-0">
                <button type="button" class="btn-close btn-close-white ms-auto mb-2" data-bs-dismiss="modal"></button>
                <img src="" alt="" id="rmLightboxImg" class="img-fluid rounded shadow">
            </div>
        </div>
    </div>

    <div class="modal fade" id="rmVideoModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="rmVideoTitle">Video</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-0">
                    <video id="rmVideoEl" controls class="w-100" style="max-height:70vh;"></video>
                    <div id="rmYoutubeEmbed" class="ratio ratio-16x9 d-none"></div>
                </div>
            </div>
        </div>
    </div>

<script>
(function () {
    const section = @json($section);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const dataUrl = @json(route('resources-master.data'));
    const storeUrl = @json(route('resources-master.store'));
    const bulkUrl = @json(route('resources-master.bulk-upload'));
    const csvUrl = @json(route('resources-master.import.csv'));
    const zipUrl = @json(route('resources-master.import.zip'));
    const canManage = @json($canManage);
    const canForceDelete = @json($canForceDelete);
    const itemBase = @json(url('/resources-master/item'));

    let page = 1;
    let viewMode = 'cards';

    function headers() {
        return {
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    function buildQuery() {
        const p = new URLSearchParams({ section, page: String(page), per_page: '12' });
        const s = document.getElementById('rmSearch')?.value?.trim();
        if (s) p.set('search', s);
        const d = document.getElementById('rmFilterDept')?.value;
        if (d) p.set('department_id', d);
        const t = document.getElementById('rmFilterTag')?.value;
        if (t) p.set('tag_id', t);
        const ft = document.getElementById('rmFilterType')?.value;
        if (ft) p.set('file_type', ft);
        const df = document.getElementById('rmDateFrom')?.value;
        if (df) p.set('date_from', df);
        const dt = document.getElementById('rmDateTo')?.value;
        if (dt) p.set('date_to', dt);
        return p.toString();
    }

    async function load() {
        const res = await fetch(dataUrl + '?' + buildQuery(), { headers: headers(), credentials: 'same-origin' });
        const json = await res.json();
        render(json.data || []);
        renderPagination(json);
    }

    function deptNames(r) {
        return (r.departments || []).map(d => d.name).join(', ') || '—';
    }

    function cardHtml(r) {
        const thumb = r.thumbnail_path
            ? `${itemBase}/${r.id}/thumbnail`
            : null;
        const type = r.file_type || '—';
        return `
        <div class="col-md-6 col-xl-4">
            <div class="card rm-card h-100">
                <div class="rm-thumb">
                    ${thumb ? `<img src="${thumb}" alt="">` : `<i class="ri-file-3-line fs-1 text-muted"></i>`}
                </div>
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-truncate" title="${escapeHtml(r.title)}">${escapeHtml(r.title)}</h5>
                    <p class="small text-muted mb-2">${escapeHtml(type)} · ${deptNames(r)}</p>
                    <div class="mt-auto d-flex flex-wrap gap-1">
                        ${actionButtons(r)}
                    </div>
                </div>
            </div>
        </div>`;
    }

    function escapeHtml(s) {
        if (!s) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function actionButtons(r) {
        let html = '';
        const dl = `${itemBase}/${r.id}/download`;
        if (r.file_type === 'link' && r.external_link) {
            html += `<a href="${dl}" class="btn btn-sm btn-primary" target="_blank" rel="noopener">Open</a>`;
        } else if (r.file_path) {
            html += `<a href="${dl}" class="btn btn-sm btn-primary">Download</a>`;
        }
        if (r.file_type === 'image' && r.file_path) {
            html += `<button type="button" class="btn btn-sm btn-outline-secondary rm-preview-img" data-src="${dl}">Preview</button>`;
        }
        if (r.file_type === 'video') {
            html += `<button type="button" class="btn btn-sm btn-outline-secondary rm-preview-vid" data-id="${r.id}" data-link="${r.external_link || ''}">Play</button>`;
        }
        if (r.checklist_schema && r.checklist_schema.length) {
            html += `<button type="button" class="btn btn-sm btn-outline-info rm-checklist" data-id="${r.id}">Checklist</button>`;
        }
        if (canManage) {
            html += `<button type="button" class="btn btn-sm btn-outline-danger rm-del" data-id="${r.id}">Archive</button>`;
        }
        if (canForceDelete) {
            html += `<button type="button" class="btn btn-sm btn-danger rm-force" data-id="${r.id}">Delete</button>`;
        }
        return html;
    }

    function render(rows) {
        const cv = document.getElementById('rmCardView');
        const tb = document.getElementById('rmTableBody');
        if (viewMode === 'cards') {
            cv.innerHTML = rows.map(cardHtml).join('');
            cv.classList.remove('d-none');
            document.getElementById('rmTableView').classList.add('d-none');
        } else {
            tb.innerHTML = rows.map(r => `
                <tr>
                    <td>${escapeHtml(r.title)}</td>
                    <td>${escapeHtml(r.file_type)}</td>
                    <td>${escapeHtml(deptNames(r))}</td>
                    <td>${r.updated_at ? r.updated_at.slice(0, 10) : ''}</td>
                    <td class="text-end">${actionButtons(r)}</td>
                </tr>`).join('');
            document.getElementById('rmTableView').classList.remove('d-none');
            cv.classList.add('d-none');
        }
        bindDynamic();
    }

    function renderPagination(json) {
        const nav = document.getElementById('rmPagination');
        if (!json.last_page || json.last_page <= 1) {
            nav.classList.add('d-none');
            return;
        }
        nav.classList.remove('d-none');
        let h = '<ul class="pagination pagination-sm mb-0">';
        for (let i = 1; i <= json.last_page; i++) {
            h += `<li class="page-item ${i === json.current_page ? 'active' : ''}"><a class="page-link rm-page" href="#" data-p="${i}">${i}</a></li>`;
        }
        h += '</ul>';
        nav.innerHTML = h;
        nav.querySelectorAll('.rm-page').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                page = parseInt(a.dataset.p, 10);
                load();
            });
        });
    }

    function bindDynamic() {
        document.querySelectorAll('.rm-preview-img').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('rmLightboxImg').src = btn.dataset.src;
                new bootstrap.Modal(document.getElementById('rmLightbox')).show();
            });
        });
        document.querySelectorAll('.rm-preview-vid').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                const link = btn.dataset.link || '';
                const modal = new bootstrap.Modal(document.getElementById('rmVideoModal'));
                const v = document.getElementById('rmVideoEl');
                const y = document.getElementById('rmYoutubeEmbed');
                v.classList.remove('d-none');
                y.classList.add('d-none');
                if (link && (link.includes('youtu') || link.includes('youtube'))) {
                    v.classList.add('d-none');
                    y.classList.remove('d-none');
                    let embed = link;
                    const m = link.match(/(?:v=|youtu\.be\/)([\w-]+)/);
                    if (m) embed = 'https://www.youtube.com/embed/' + m[1];
                    y.innerHTML = '<iframe src="' + embed + '" class="w-100" style="min-height:360px" allowfullscreen></iframe>';
                } else {
                    v.src = `${itemBase}/${id}/download`;
                    fetch(`${itemBase}/${id}/watch`, {
                        method: 'POST', headers: { ...headers(), 'Content-Type': 'application/json' }, credentials: 'same-origin', body: '{}'
                    }).catch(() => {});
                }
                modal.show();
            });
        });
        document.querySelectorAll('.rm-del').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Archive this resource?')) return;
                await fetch(`${itemBase}/` + btn.dataset.id, {
                    method: 'DELETE',
                    headers: { ...headers(), 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                load();
            });
        });
        document.querySelectorAll('.rm-force').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Permanently delete?')) return;
                await fetch(@json(url('/resources-master/force')) + '/' + btn.dataset.id, {
                    method: 'DELETE',
                    headers: headers(),
                    credentials: 'same-origin'
                });
                load();
            });
        });
        document.querySelectorAll('.rm-checklist').forEach(btn => {
            btn.addEventListener('click', () => {
                alert('This record has a checklist template (JSON). Extend the module to add an online fill/report UI.');
            });
        });
    }

    document.getElementById('rmApplyFilters')?.addEventListener('click', () => { page = 1; load(); });
    document.getElementById('rmViewCards')?.addEventListener('click', () => { viewMode = 'cards'; document.getElementById('rmViewTable').classList.remove('active'); document.getElementById('rmViewCards').classList.add('active'); load(); });
    document.getElementById('rmViewTable')?.addEventListener('click', () => { viewMode = 'table'; document.getElementById('rmViewCards').classList.remove('active'); document.getElementById('rmViewTable').classList.add('active'); load(); });

    document.getElementById('rmFileInput')?.addEventListener('change', e => {
        const f = e.target.files[0];
        const prev = document.getElementById('rmFilePreview');
        prev.innerHTML = '';
        if (!f) return;
        if (f.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.style.maxHeight = '120px';
            img.file = f;
            const r = new FileReader();
            r.onload = e2 => { img.src = e2.target.result; };
            r.readAsDataURL(f);
            prev.appendChild(img);
        } else {
            prev.textContent = f.name + ' (' + Math.round(f.size / 1024) + ' KB)';
        }
    });

    document.getElementById('rmSubmitUpload')?.addEventListener('click', async () => {
        const form = document.getElementById('rmUploadForm');
        const fd = new FormData(form);
        const bar = document.querySelector('#rmProgress .progress-bar');
        document.getElementById('rmProgress').style.display = 'block';
        bar.style.width = '30%';
        const res = await fetch(storeUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd, credentials: 'same-origin' });
        bar.style.width = '100%';
        setTimeout(() => { document.getElementById('rmProgress').style.display = 'none'; bar.style.width = '0%'; }, 400);
        if (res.ok) {
            bootstrap.Modal.getInstance(document.getElementById('rmUploadModal'))?.hide();
            form.reset();
            document.getElementById('rmFilePreview').innerHTML = '';
            load();
        } else {
            const err = await res.json().catch(() => ({}));
            alert(err.message || 'Upload failed');
        }
    });

    let bulkFiles = [];
    const bulkDrop = document.getElementById('rmBulkDrop');
    const bulkInput = document.getElementById('rmBulkFiles');
    bulkDrop?.addEventListener('click', () => bulkInput.click());
    bulkDrop?.addEventListener('dragover', e => { e.preventDefault(); bulkDrop.classList.add('dragover'); });
    bulkDrop?.addEventListener('dragleave', () => bulkDrop.classList.remove('dragover'));
    bulkDrop?.addEventListener('drop', e => {
        e.preventDefault();
        bulkDrop.classList.remove('dragover');
        bulkFiles = Array.from(e.dataTransfer.files || []);
        document.getElementById('rmBulkList').textContent = bulkFiles.map(f => f.name).join(', ');
    });
    bulkInput?.addEventListener('change', e => {
        bulkFiles = Array.from(e.target.files || []);
        document.getElementById('rmBulkList').textContent = bulkFiles.map(f => f.name).join(', ');
    });
    document.getElementById('rmBulkSubmit')?.addEventListener('click', async () => {
        if (!bulkFiles.length) return alert('Select files');
        const fd = new FormData();
        bulkFiles.forEach(f => fd.append('files[]', f));
        fd.append('category', section);
        fd.append('_token', csrf);
        const res = await fetch(bulkUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd, credentials: 'same-origin' });
        if (res.ok) {
            bootstrap.Modal.getInstance(document.getElementById('rmBulkModal'))?.hide();
            bulkFiles = [];
            load();
        } else alert('Bulk upload failed');
    });

    document.getElementById('rmCsvSubmit')?.addEventListener('click', async () => {
        const f = document.getElementById('rmCsvFile').files[0];
        if (!f) return;
        const fd = new FormData();
        fd.append('file', f);
        fd.append('category', section);
        fd.append('_token', csrf);
        const res = await fetch(csvUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: fd, credentials: 'same-origin' });
        if (res.ok) { bootstrap.Modal.getInstance(document.getElementById('rmCsvModal'))?.hide(); load(); }
        else alert('CSV import failed');
    });

    document.getElementById('rmZipSubmit')?.addEventListener('click', async () => {
        const f = document.getElementById('rmZipFile').files[0];
        if (!f) return;
        const fd = new FormData();
        fd.append('file', f);
        fd.append('category', section);
        fd.append('_token', csrf);
        const res = await fetch(zipUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: fd, credentials: 'same-origin' });
        if (res.ok) { bootstrap.Modal.getInstance(document.getElementById('rmZipModal'))?.hide(); load(); }
        else alert('ZIP import failed');
    });

    load();
})();
</script>
@endsection
