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
    .rm-tag-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        background: #eef2f6;
        border: 1px solid rgba(0,0,0,.08);
        border-radius: 999px;
        padding: .2rem .55rem .2rem .7rem;
        font-size: .8125rem;
    }
    .rm-tag-chip .btn-close { font-size: .55rem; padding: .15rem; }
    .rm-tag-suggest { cursor: pointer; }
    .rm-search-select { position: relative; }
    .rm-search-select-list {
        position: absolute;
        z-index: 1080;
        left: 0; right: 0;
        top: calc(100% + 2px);
        max-height: 220px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid rgba(0,0,0,.16);
        border-radius: .375rem;
        box-shadow: 0 8px 24px rgba(0,0,0,.12);
    }
    .rm-search-select-item {
        display: block;
        width: 100%;
        text-align: left;
        background: none;
        border: 0;
        padding: .4rem .75rem;
        font-size: .9rem;
        color: #333;
    }
    .rm-search-select-item:hover,
    .rm-search-select-item.active { background: #fff3cd; color: #111; }
    .rm-search-select-empty { padding: .45rem .75rem; color: #888; font-size: .85rem; }
    .btn.rm-icon {
        width: 32px;
        padding-left: 0;
        padding-right: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn.rm-icon i { font-size: 1rem; line-height: 1; }
    .rm-clid {
        display: inline-flex;
        align-items: center;
        min-width: 14px;
        min-height: 22px;
        cursor: default;
    }
    .rm-clid-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #198754;
        flex-shrink: 0;
    }
    .rm-clid-detail {
        display: none;
        align-items: center;
        gap: .3rem;
        margin-left: .4rem;
        font-weight: 600;
        color: #212529;
        white-space: nowrap;
    }
    .rm-clid:hover .rm-clid-detail { display: inline-flex; }
    .rm-clid-copy {
        border: 0;
        background: transparent;
        padding: 0;
        line-height: 1;
        color: #6c757d;
    }
    .rm-clid-copy:hover { color: #0d6efd; }
    .rm-clid-copy.copied { color: #198754; }
    .rm-csv-table th { white-space: nowrap; }
    .rm-csv-thumb {
        width: 36px;
        height: 36px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid rgba(0,0,0,.08);
    }
    .rm-csv-img-empty {
        width: 36px;
        height: 36px;
        border-radius: 6px;
        background: #f1f3f5;
        color: #adb5bd;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .rm-csv-link { word-break: break-all; }
    .rm-readonly-field {
        min-height: 38px;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .35rem;
    }
    .rm-readonly-record + .rm-readonly-record {
        margin-top: 1.25rem;
        padding-top: 1.25rem;
        border-top: 1px solid rgba(0,0,0,.08);
    }
</style>
@endsection

@section('content')
    @include('layouts.shared.page-title', ['page_title' => $title, 'sub_title' => 'Resources Master'])

    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ route('resources-master.dashboard') }}" class="btn btn-soft-secondary btn-sm"><i class="ri-arrow-left-line"></i> Dashboard</a>
        </div>
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
                    <div class="rm-search-select" data-rm-search="filter-dept">
                        <input type="hidden" id="rmFilterDept" value="">
                        <input type="text" class="form-control form-control-sm rm-search-select-input" placeholder="Search department" autocomplete="off">
                        <div class="rm-search-select-list d-none">
                            <button type="button" class="rm-search-select-item" data-value="">All</button>
                            @foreach($departments as $d)
                                <button type="button" class="rm-search-select-item" data-value="{{ $d->id }}">{{ $d->name }}</button>
                            @endforeach
                        </div>
                    </div>
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
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#rmUploadModal">
                    <i class="ri-upload-2-line"></i> Add resource
                </button>
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
                        <th>Tags</th>
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
                    <h5 class="modal-title" id="rmUploadTitle">Add resource</h5>
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
                                <div class="rm-search-select" data-rm-search="form-dept">
                                    <input type="hidden" name="department_ids[]" id="rmDeptValue" value="">
                                    <input type="text" class="form-control rm-search-select-input" id="rmDeptQuery" placeholder="Search department" autocomplete="off">
                                    <div class="rm-search-select-list d-none">
                                        <button type="button" class="rm-search-select-item" data-value="">Select department</button>
                                        @foreach($departments as $d)
                                            <button type="button" class="rm-search-select-item" data-value="{{ $d->id }}">{{ $d->name }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Tags</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="rmTagInput" list="rmTagSuggestions" placeholder="Type a tag and click Add" maxlength="128" autocomplete="off">
                                    <button type="button" class="btn btn-outline-primary" id="rmTagAddBtn">Add</button>
                                    <button type="button" class="btn btn-outline-danger" id="rmTagDeleteBtn">Delete</button>
                                </div>
                                <datalist id="rmTagSuggestions">
                                    @foreach($tags as $t)
                                        <option value="{{ $t->tag_name }}"></option>
                                    @endforeach
                                </datalist>
                                <div id="rmTagChips" class="d-flex flex-wrap gap-1 mt-2"></div>
                                @if($tags->isNotEmpty())
                                    <div class="small text-muted mt-1">Existing:
                                        @foreach($tags->unique('tag_name')->take(8) as $t)
                                            <button type="button" class="btn btn-link btn-sm p-0 rm-tag-suggest" data-name="{{ $t->tag_name }}">{{ $t->tag_name }}</button>@if(!$loop->last), @endif
                                        @endforeach
                                    </div>
                                @endif
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

    <div class="modal fade" id="rmViewModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rmViewTitle">View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="rmViewBody"></div>
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
    const tagStoreUrl = @json(route('resources-master.tags.store'));
    const tagDeleteUrl = @json(route('resources-master.tags.destroy'));
    const bulkUrl = @json(route('resources-master.bulk-upload'));
    const csvUrl = @json(route('resources-master.import.csv'));
    const zipUrl = @json(route('resources-master.import.zip'));
    const canManage = @json($canManage);
    const canForceDelete = @json($canForceDelete);
    const itemBase = @json(url('/resources-master/item'));

    let page = 1;
    let viewMode = 'cards';
    let editId = null;
    let lastRows = [];
    const selectedTags = [];

    function selectedDeptId() {
        return document.getElementById('rmDeptValue')?.value || '';
    }

    function bindSearchSelect(root) {
        const hidden = root.querySelector('input[type="hidden"]');
        const input = root.querySelector('.rm-search-select-input');
        const list = root.querySelector('.rm-search-select-list');
        if (!hidden || !input || !list) return;

        const items = () => [...list.querySelectorAll('.rm-search-select-item')];

        function openList(all) {
            list.classList.remove('d-none');
            if (all) {
                items().forEach(el => el.classList.remove('d-none'));
                list.querySelector('.rm-search-select-empty')?.remove();
            } else {
                filterList();
            }
        }
        function closeList() {
            list.classList.add('d-none');
            const selected = items().find(el => el.dataset.value === hidden.value);
            input.value = selected ? selected.textContent.trim() : '';
        }
        function filterList() {
            const q = input.value.trim().toLowerCase();
            let visible = 0;
            items().forEach(el => {
                const match = !q || el.textContent.toLowerCase().includes(q);
                el.classList.toggle('d-none', !match);
                if (match) visible++;
            });
            let empty = list.querySelector('.rm-search-select-empty');
            if (!visible) {
                if (!empty) {
                    empty = document.createElement('div');
                    empty.className = 'rm-search-select-empty';
                    empty.textContent = 'No matches';
                    list.appendChild(empty);
                }
            } else if (empty) {
                empty.remove();
            }
        }
        function pick(el) {
            hidden.value = el.dataset.value || '';
            input.value = el.textContent.trim();
            closeList();
        }

        input.addEventListener('focus', () => { openList(true); input.select(); });
        input.addEventListener('click', () => openList(true));
        input.addEventListener('input', () => {
            if (input.value.trim() === '') hidden.value = '';
            openList();
        });
        input.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeList();
                input.blur();
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                const first = items().find(el => !el.classList.contains('d-none'));
                if (first) pick(first);
            }
        });
        items().forEach(el => el.addEventListener('click', () => pick(el)));
        document.addEventListener('click', e => {
            if (!root.contains(e.target)) closeList();
        });

        root.resetSearchSelect = () => {
            hidden.value = '';
            input.value = '';
            closeList();
        };
        root.setSearchSelect = (value) => {
            hidden.value = value ? String(value) : '';
            const selected = items().find(el => el.dataset.value === hidden.value);
            input.value = selected ? selected.textContent.trim() : '';
            closeList();
        };
    }

    document.querySelectorAll('[data-rm-search]').forEach(bindSearchSelect);

    function addTagToFilter(tag) {
        const sel = document.getElementById('rmFilterTag');
        const list = document.getElementById('rmTagSuggestions');
        if (!tag || !tag.id) return;
        if (sel && !sel.querySelector('option[value="' + tag.id + '"]')) {
            const opt = document.createElement('option');
            opt.value = String(tag.id);
            opt.textContent = tag.tag_name;
            sel.appendChild(opt);
        }
        if (list && ![...list.options].some(o => o.value.toLowerCase() === String(tag.tag_name).toLowerCase())) {
            const opt = document.createElement('option');
            opt.value = tag.tag_name;
            list.appendChild(opt);
        }
    }

    function renderTagChips() {
        const box = document.getElementById('rmTagChips');
        if (!box) return;
        box.innerHTML = '';
        selectedTags.forEach((tag, idx) => {
            const chip = document.createElement('span');
            chip.className = 'rm-tag-chip';
            chip.appendChild(document.createTextNode(tag.name));
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            if (tag.id) {
                hidden.name = 'tag_ids[]';
                hidden.value = String(tag.id);
            } else {
                hidden.name = 'tag_names[]';
                hidden.value = tag.name;
            }
            chip.appendChild(hidden);
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'btn-close';
            close.setAttribute('aria-label', 'Remove');
            close.addEventListener('click', () => {
                selectedTags.splice(idx, 1);
                renderTagChips();
            });
            chip.appendChild(close);
            box.appendChild(chip);
        });
    }

    function resetTags() {
        selectedTags.length = 0;
        const input = document.getElementById('rmTagInput');
        if (input) input.value = '';
        renderTagChips();
    }

    function tagAlreadySelected(name) {
        const needle = name.toLowerCase();
        return selectedTags.some(t => t.name.toLowerCase() === needle);
    }

    async function addTagName(rawName) {
        const name = (rawName || '').trim();
        if (!name) return;
        if (tagAlreadySelected(name)) {
            const input = document.getElementById('rmTagInput');
            if (input) input.value = '';
            return;
        }
        try {
            const body = new URLSearchParams({ tag_name: name });
            const dept = selectedDeptId();
            if (dept) body.set('department_id', dept);
            const res = await fetch(tagStoreUrl, {
                method: 'POST',
                headers: { ...headers(), 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'same-origin'
            });
            const json = await res.json().catch(() => ({}));
            if (res.ok && json.tag) {
                selectedTags.push({ id: json.tag.id, name: json.tag.tag_name || name });
                addTagToFilter(json.tag);
            } else {
                selectedTags.push({ id: null, name });
            }
        } catch (e) {
            selectedTags.push({ id: null, name });
        }
        const input = document.getElementById('rmTagInput');
        if (input) input.value = '';
        renderTagChips();
    }

    function removeTagFromFilter(ids, name) {
        const idSet = new Set((ids || []).map(String));
        const sel = document.getElementById('rmFilterTag');
        if (sel) {
            [...sel.querySelectorAll('option')].forEach(opt => {
                if (opt.value && idSet.has(opt.value)) opt.remove();
            });
        }
        const list = document.getElementById('rmTagSuggestions');
        if (list && name) {
            [...list.options].forEach(o => {
                if (o.value.toLowerCase() === name.toLowerCase()) o.remove();
            });
        }
        document.querySelectorAll('.rm-tag-suggest').forEach(btn => {
            if (name && (btn.dataset.name || '').toLowerCase() === name.toLowerCase()) {
                btn.remove();
            }
        });
    }

    async function deleteTagName(rawName) {
        let name = (rawName || '').trim();
        let id = null;
        if (!name && selectedTags.length) {
            const last = selectedTags[selectedTags.length - 1];
            name = last.name;
            id = last.id;
        }
        if (!name && !id) return;
        if (!confirm('Delete tag "' + name + '"?')) return;
        const params = new URLSearchParams();
        if (id) params.set('tag_id', String(id));
        if (name) params.set('tag_name', name);
        try {
            const res = await fetch(tagDeleteUrl + '?' + params.toString(), {
                method: 'DELETE',
                headers: headers(),
                credentials: 'same-origin'
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                alert(json.message || 'Could not delete tag');
                return;
            }
            const deletedIds = (json.deleted_ids || []).map(String);
            for (let i = selectedTags.length - 1; i >= 0; i--) {
                const t = selectedTags[i];
                if ((t.id && deletedIds.includes(String(t.id))) || (name && t.name.toLowerCase() === name.toLowerCase())) {
                    selectedTags.splice(i, 1);
                }
            }
            removeTagFromFilter(json.deleted_ids || [], name);
            const input = document.getElementById('rmTagInput');
            if (input) input.value = '';
            renderTagChips();
        } catch (e) {
            alert('Could not delete tag');
        }
    }

    document.getElementById('rmTagAddBtn')?.addEventListener('click', () => {
        addTagName(document.getElementById('rmTagInput')?.value);
    });
    document.getElementById('rmTagDeleteBtn')?.addEventListener('click', () => {
        deleteTagName(document.getElementById('rmTagInput')?.value);
    });
    document.getElementById('rmTagInput')?.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addTagName(e.target.value);
        }
    });
    document.querySelectorAll('.rm-tag-suggest').forEach(btn => {
        btn.addEventListener('click', () => addTagName(btn.dataset.name));
    });

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
        lastRows = json.data || [];
        render(lastRows);
        renderPagination(json);
    }

    function deptNames(r) {
        return (r.departments || []).map(d => d.name).join(', ') || '—';
    }

    function tagBadges(r) {
        const tags = r.tags || [];
        if (!tags.length) return '<span class="text-muted">—</span>';
        return tags.map(t => `<span class="badge rounded-pill bg-light text-dark border me-1">${escapeHtml(t.tag_name || t.name || '')}</span>`).join('');
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

    function clIdHtml(r) {
        const id = String(r.id ?? '');
        return `<div class="rm-clid">
            <span class="rm-clid-dot" aria-hidden="true"></span>
            <span class="rm-clid-detail">
                <span>${escapeHtml(id)}</span>
                <button type="button" class="rm-clid-copy" data-copy="${escapeHtml(id)}" title="Copy ID" aria-label="Copy ID"><i class="ri-file-copy-line"></i></button>
            </span>
        </div>`;
    }

    function formatDate(iso) {
        if (!iso) return '';
        const m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!m) return '';
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${parseInt(m[3], 10)} ${months[parseInt(m[2], 10) - 1]} ${m[1].slice(-2)}`;
    }

    function actionButtons(r) {
        let html = '';
        const dl = `${itemBase}/${r.id}/download`;
        const preview = `${itemBase}/${r.id}/preview`;
        html += `<button type="button" class="btn btn-sm btn-outline-secondary rm-icon rm-view" title="View" aria-label="View" data-id="${r.id}" data-type="${escapeHtml(r.file_type || '')}" data-title="${escapeHtml(r.title)}" data-desc="${escapeHtml(r.description || '')}" data-depts="${escapeHtml(deptNames(r))}" data-preview="${preview}" data-link="${escapeHtml(r.external_link || '')}" data-has-file="${r.file_path ? '1' : '0'}"><i class="ri-eye-line"></i></button>`;
        if (canManage) {
            html += `<button type="button" class="btn btn-sm btn-outline-primary rm-icon rm-edit" title="Edit" aria-label="Edit" data-id="${r.id}"><i class="ri-pencil-line"></i></button>`;
        }
        if (r.file_type === 'link' && r.external_link) {
            html += `<a href="${dl}" class="btn btn-sm btn-primary rm-icon" title="Open" aria-label="Open" target="_blank" rel="noopener"><i class="ri-external-link-line"></i></a>`;
        } else if (r.file_path) {
            html += `<a href="${dl}" class="btn btn-sm btn-primary rm-icon" title="Download" aria-label="Download"><i class="ri-download-line"></i></a>`;
        }
        if (r.file_type === 'image' && r.file_path) {
            html += `<button type="button" class="btn btn-sm btn-outline-secondary rm-icon rm-preview-img" title="Preview" aria-label="Preview" data-src="${dl}"><i class="ri-image-line"></i></button>`;
        }
        if (r.file_type === 'video') {
            html += `<button type="button" class="btn btn-sm btn-outline-secondary rm-icon rm-preview-vid" title="Play" aria-label="Play" data-id="${r.id}" data-link="${r.external_link || ''}"><i class="ri-play-line"></i></button>`;
        }
        if (r.checklist_schema && r.checklist_schema.length) {
            html += `<button type="button" class="btn btn-sm btn-outline-info rm-icon rm-checklist" title="Checklist" aria-label="Checklist" data-id="${r.id}"><i class="ri-checkbox-line"></i></button>`;
        }
        if (canManage) {
            html += `<button type="button" class="btn btn-sm btn-outline-danger rm-icon rm-del" title="Archive" aria-label="Archive" data-id="${r.id}"><i class="ri-archive-line"></i></button>`;
        }
        if (canForceDelete) {
            html += `<button type="button" class="btn btn-sm btn-danger rm-icon rm-force" title="Delete" aria-label="Delete" data-id="${r.id}"><i class="ri-delete-bin-line"></i></button>`;
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
                    <td>${tagBadges(r)}</td>
                    <td>${formatDate(r.updated_at)}</td>
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

    function parseCsv(text) {
        const rows = [];
        let row = [];
        let cell = '';
        let inQuotes = false;
        const src = String(text || '').replace(/^\uFEFF/, '');
        for (let i = 0; i < src.length; i++) {
            const c = src[i];
            const n = src[i + 1];
            if (c === '"') {
                if (inQuotes && n === '"') {
                    cell += '"';
                    i++;
                } else {
                    inQuotes = !inQuotes;
                }
            } else if (c === ',' && !inQuotes) {
                row.push(cell);
                cell = '';
            } else if ((c === '\n' || c === '\r') && !inQuotes) {
                row.push(cell);
                cell = '';
                if (c === '\r' && n === '\n') i++;
                if (row.some(v => String(v).trim() !== '')) rows.push(row);
                row = [];
            } else {
                cell += c;
            }
        }
        if (cell !== '' || row.length) {
            row.push(cell);
            if (row.some(v => String(v).trim() !== '')) rows.push(row);
        }
        return rows;
    }

    function formatCsvCell(header, value) {
        const key = String(header || '').trim().toLowerCase();
        const v = String(value || '').trim();
        if (key === 'status') {
            const map = { todo: 'secondary', working: 'info', done: 'success', complete: 'success', blocked: 'danger', pending: 'warning' };
            const cls = map[v.toLowerCase()] || 'secondary';
            return v ? `<span class="badge text-bg-${cls}">${escapeHtml(v)}</span>` : '<span class="text-muted">—</span>';
        }
        if (key === 'priority') {
            const map = { high: 'danger', medium: 'warning', normal: 'secondary', low: 'light' };
            const cls = map[v.toLowerCase()] || 'secondary';
            return v ? `<span class="badge text-bg-${cls}">${escapeHtml(v)}</span>` : '<span class="text-muted">—</span>';
        }
        if (key === 'group') {
            return v ? `<span class="badge bg-light text-dark border">${escapeHtml(v)}</span>` : '<span class="text-muted">—</span>';
        }
        if (key === 'image') {
            if (!v) return '<span class="rm-csv-img-empty" title="No image"><i class="ri-image-line"></i></span>';
            return `<img src="${escapeHtml(v)}" alt="" class="rm-csv-thumb">`;
        }
        if (key === 'links' || key === 'link') {
            if (!v) return '<span class="text-muted">—</span>';
            return `<span class="rm-csv-link">${escapeHtml(v).replace(/(https?:\/\/[^\s,]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>')}</span>`;
        }
        return v ? escapeHtml(v) : '<span class="text-muted">—</span>';
    }

    function renderCsvFormView(rows) {
        if (!rows.length) return '<p class="text-muted mb-0">No data in this file.</p>';
        const headers = rows[0];
        let html = '<div class="rm-readonly-view">';
        for (let r = 1; r < rows.length; r++) {
            html += '<div class="rm-readonly-record">';
            if (rows.length > 2) {
                html += `<div class="small text-muted mb-2">Record ${r}</div>`;
            }
            html += '<div class="row g-2">';
            headers.forEach((h, c) => {
                const key = String(h || '').trim().toLowerCase();
                const wide = ['task', 'links', 'link', 'description', 'title'].includes(key);
                html += `<div class="${wide ? 'col-12' : 'col-md-6'} mb-2">
                    <label class="form-label">${escapeHtml(h)}</label>
                    <div class="form-control rm-readonly-field">${formatCsvCell(h, rows[r][c] || '')}</div>
                </div>`;
            });
            html += '</div></div>';
        }
        html += '</div>';
        return html;
    }

    function looksLikeCsv(text) {
        const first = String(text || '').split(/\r?\n/).find(l => l.trim());
        return !!(first && first.includes(','));
    }

    function openVideoPreview(id, link) {
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
    }

    function bindDynamic() {
        document.querySelectorAll('.rm-view').forEach(btn => {
            btn.addEventListener('click', () => {
                const type = btn.dataset.type || '';
                const link = btn.dataset.link || '';
                const preview = btn.dataset.preview;
                const title = btn.dataset.title || 'View';
                if (type === 'image' && btn.dataset.hasFile === '1') {
                    document.getElementById('rmLightboxImg').src = preview;
                    new bootstrap.Modal(document.getElementById('rmLightbox')).show();
                    return;
                }
                if (type === 'video') {
                    openVideoPreview(btn.dataset.id, link);
                    return;
                }
                if (type === 'link' && link) {
                    window.open(link, '_blank', 'noopener');
                    return;
                }
                const body = document.getElementById('rmViewBody');
                document.getElementById('rmViewTitle').textContent = title;
                if (btn.dataset.hasFile === '1' && (type === 'spreadsheet' || type === 'checklist')) {
                    body.innerHTML = '<div class="text-center text-muted py-5">Loading…</div>';
                    new bootstrap.Modal(document.getElementById('rmViewModal')).show();
                    fetch(preview, { credentials: 'same-origin', headers: { Accept: 'text/plain,*/*' } })
                        .then(res => res.text())
                        .then(text => {
                            body.innerHTML = looksLikeCsv(text)
                                ? renderCsvFormView(parseCsv(text))
                                : `<iframe src="${preview}" class="w-100" style="min-height:70vh;border:0;background:#fff"></iframe>`;
                        })
                        .catch(() => {
                            body.innerHTML = `<iframe src="${preview}" class="w-100" style="min-height:70vh;border:0;background:#fff"></iframe>`;
                        });
                    return;
                }
                if (btn.dataset.hasFile === '1') {
                    body.innerHTML = `<iframe src="${preview}" class="w-100" style="min-height:70vh;border:0;background:#fff"></iframe>`;
                } else {
                    body.innerHTML = `
                        <p class="mb-1"><span class="text-muted">Type:</span> ${escapeHtml(type || '—')}</p>
                        <p class="mb-1"><span class="text-muted">Departments:</span> ${escapeHtml(btn.dataset.depts || '—')}</p>
                        <p class="mb-0">${escapeHtml(btn.dataset.desc || 'No description')}</p>`;
                }
                new bootstrap.Modal(document.getElementById('rmViewModal')).show();
            });
        });
        document.querySelectorAll('.rm-clid-copy').forEach(btn => {
            btn.addEventListener('click', async e => {
                e.preventDefault();
                e.stopPropagation();
                const text = btn.dataset.copy || '';
                try {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        await navigator.clipboard.writeText(text);
                    } else {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        ta.remove();
                    }
                    btn.classList.add('copied');
                    const icon = btn.querySelector('i');
                    if (icon) icon.className = 'ri-check-line';
                    setTimeout(() => {
                        btn.classList.remove('copied');
                        if (icon) icon.className = 'ri-file-copy-line';
                    }, 1200);
                } catch (err) {}
            });
        });
        document.querySelectorAll('.rm-edit').forEach(btn => {
            btn.addEventListener('click', () => {
                const row = lastRows.find(r => String(r.id) === String(btn.dataset.id));
                if (row) openEdit(row);
            });
        });
        document.querySelectorAll('.rm-preview-img').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('rmLightboxImg').src = btn.dataset.src;
                new bootstrap.Modal(document.getElementById('rmLightbox')).show();
            });
        });
        document.querySelectorAll('.rm-preview-vid').forEach(btn => {
            btn.addEventListener('click', () => openVideoPreview(btn.dataset.id, btn.dataset.link || ''));
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

    function defaultUploadTitle() {
        return 'Add resource';
    }

    function resetFormMode() {
        editId = null;
        const form = document.getElementById('rmUploadForm');
        form?.reset();
        resetTags();
        document.querySelector('[data-rm-search="form-dept"]')?.resetSearchSelect?.();
        const prev = document.getElementById('rmFilePreview');
        if (prev) prev.innerHTML = '';
        const titleEl = document.getElementById('rmUploadTitle');
        if (titleEl) titleEl.textContent = defaultUploadTitle();
    }

    function openEdit(r) {
        resetFormMode();
        editId = r.id;
        const titleEl = document.getElementById('rmUploadTitle');
        if (titleEl) titleEl.textContent = 'Edit resource';
        const form = document.getElementById('rmUploadForm');
        const titleInput = form?.querySelector('[name="title"]');
        const descInput = form?.querySelector('[name="description"]');
        const linkInput = form?.querySelector('[name="external_link"]');
        if (titleInput) titleInput.value = r.title || '';
        if (descInput) descInput.value = r.description || '';
        if (linkInput) linkInput.value = r.external_link || '';
        const dept = (r.departments || [])[0];
        if (dept) document.querySelector('[data-rm-search="form-dept"]')?.setSearchSelect?.(String(dept.id));
        (r.tags || []).forEach(t => selectedTags.push({ id: t.id, name: t.tag_name }));
        renderTagChips();
        const prev = document.getElementById('rmFilePreview');
        if (prev && r.original_filename) prev.textContent = 'Current file: ' + r.original_filename;
        new bootstrap.Modal(document.getElementById('rmUploadModal')).show();
    }

    document.querySelector('[data-bs-target="#rmUploadModal"]')?.addEventListener('click', resetFormMode);

    document.getElementById('rmSubmitUpload')?.addEventListener('click', async () => {
        const pending = document.getElementById('rmTagInput')?.value?.trim();
        if (pending) await addTagName(pending);
        const form = document.getElementById('rmUploadForm');
        const fd = new FormData(form);
        if (editId) fd.append('_method', 'PUT');
        const url = editId ? `${itemBase}/${editId}` : storeUrl;
        const bar = document.querySelector('#rmProgress .progress-bar');
        document.getElementById('rmProgress').style.display = 'block';
        bar.style.width = '30%';
        const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd, credentials: 'same-origin' });
        bar.style.width = '100%';
        setTimeout(() => { document.getElementById('rmProgress').style.display = 'none'; bar.style.width = '0%'; }, 400);
        if (res.ok) {
            bootstrap.Modal.getInstance(document.getElementById('rmUploadModal'))?.hide();
            resetFormMode();
            load();
        } else {
            const err = await res.json().catch(() => ({}));
            alert(err.message || 'Save failed');
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
