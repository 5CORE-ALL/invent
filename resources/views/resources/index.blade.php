@extends('layouts.vertical', ['title' => 'Resources'])

@section('css')
<style>
    .res-toolbar { background: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: 12px; padding: 1rem 1.25rem; }
    .res-breadcrumb a { color: #495057; text-decoration: none; }
    .res-breadcrumb a:hover { color: var(--bs-primary); }
    .res-folder-card, .res-item-card {
        border: 1px solid rgba(0,0,0,.08);
        border-radius: 12px;
        background: #fff;
        transition: box-shadow .15s, transform .15s;
        height: 100%;
        cursor: pointer;
        overflow: hidden;
        min-width: 0;
    }
    .res-folder-card:hover, .res-item-card:hover {
        box-shadow: 0 8px 24px rgba(0,0,0,.08);
        transform: translateY(-2px);
    }
    .res-icon-wrap {
        width: 52px; height: 52px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    .res-icon-folder { background: rgba(255,193,7,.15); color: #b78103; }
    .res-icon-link { background: rgba(13,110,253,.12); color: #0d6efd; }
    .res-icon-spreadsheet { background: rgba(25,135,84,.12); color: #198754; }
    .res-icon-image { background: rgba(111,66,193,.12); color: #6f42c1; }
    .res-icon-pdf { background: rgba(220,53,69,.12); color: #dc3545; }
    .res-icon-default { background: rgba(108,117,125,.12); color: #6c757d; }
    .res-item-title {
        font-weight: 600;
        font-size: .95rem;
        line-height: 1.35;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        word-break: break-word;
        overflow-wrap: anywhere;
    }
    .res-card-body { min-width: 0; flex: 1 1 0; }
    .res-item-card > .d-flex { min-width: 0; }
    #resItemsGrid > [class*="col-"] { min-width: 0; }
    .res-meta { font-size: .75rem; color: #6c757d; }
    .res-empty { border: 2px dashed rgba(0,0,0,.08); border-radius: 12px; padding: 3rem 1rem; text-align: center; color: #6c757d; }
    .res-actions .btn { padding: .2rem .45rem; }

    .res-table-wrap { background: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: 12px; overflow: hidden; }
    .res-table { font-size: .875rem; }
    .res-table thead th {
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6c757d;
        background: #f8f9fa;
        border-bottom: 1px solid rgba(0,0,0,.08);
        white-space: nowrap;
    }
    .res-table tbody td { vertical-align: middle; }
    .res-table .res-row-name { cursor: pointer; }
    .res-table .res-icon-wrap { width: 38px; height: 38px; font-size: 1.15rem; border-radius: 9px; }
    .res-table .res-row-title { font-weight: 600; color: #212529; word-break: break-word; }
    .res-view-toggle .btn.active { background: var(--bs-primary); border-color: var(--bs-primary); color: #fff; }

    .res-layout { align-items: flex-start; }
    .res-sidebar {
        background: #fff;
        border: 1px solid rgba(0,0,0,.08);
        border-radius: 12px;
        position: sticky;
        top: 80px;
        max-height: calc(100vh - 100px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .res-sidebar-head {
        padding: .85rem 1rem;
        border-bottom: 1px solid rgba(0,0,0,.06);
        font-weight: 600;
        font-size: .85rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6c757d;
        flex-shrink: 0;
    }
    .res-sidebar-search {
        padding: .65rem .75rem;
        border-bottom: 1px solid rgba(0,0,0,.06);
        flex-shrink: 0;
    }
    .res-sidebar-body {
        overflow-y: auto;
        padding: .35rem 0 .75rem;
        flex: 1;
    }
    .res-tree-root { list-style: none; margin: 0; padding: 0; }
    .res-tree-list { list-style: none; margin: 0; padding: 0; }
    .res-tree-node > .res-tree-list { display: none; }
    .res-tree-node.open > .res-tree-list { display: block; }
    .res-tree-row {
        display: flex;
        align-items: center;
        gap: .15rem;
        min-width: 0;
    }
    .res-tree-toggle,
    .res-tree-spacer {
        width: 22px;
        height: 22px;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        background: transparent;
        color: #6c757d;
        padding: 0;
    }
    .res-tree-toggle i { transition: transform .15s; font-size: 1rem; }
    .res-tree-node.open > .res-tree-row .res-tree-toggle i { transform: rotate(90deg); }
    .res-tree-link {
        display: flex;
        align-items: center;
        gap: .45rem;
        flex: 1;
        min-width: 0;
        padding: .35rem .65rem .35rem 0;
        color: #495057;
        text-decoration: none;
        border-radius: 8px;
        font-size: .875rem;
    }
    .res-tree-link:hover { background: rgba(13,110,253,.06); color: #0d6efd; }
    .res-tree-node.active > .res-tree-row .res-tree-link {
        background: rgba(13,110,253,.1);
        color: #0d6efd;
        font-weight: 600;
    }
    .res-tree-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        flex: 1;
        min-width: 0;
    }
    .res-tree-count {
        font-size: .7rem;
        background: #f1f3f5;
        color: #6c757d;
        border-radius: 999px;
        padding: .1rem .45rem;
        flex-shrink: 0;
    }
    .res-tree-node.active .res-tree-count { background: rgba(13,110,253,.15); color: #0d6efd; }
    .res-tree-node.hidden-by-filter { display: none; }
    @media (max-width: 991.98px) {
        .res-sidebar { position: static; max-height: 320px; margin-bottom: 1rem; }
    }
</style>
@endsection

@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Resources', 'sub_title' => 'Links, documents & files'])

    <div class="row g-3 res-layout">
        <div class="col-lg-3 col-xl-2">
            <aside class="res-sidebar">
                <div class="res-sidebar-head">Folders</div>
                <div class="res-sidebar-search">
                    <input type="search" id="resFolderSearch" class="form-control form-control-sm" placeholder="Filter folders...">
                </div>
                <div class="res-sidebar-body" id="resFolderTree">
                    <ul class="res-tree-root">
                        <li class="res-tree-node {{ $folderId === 0 ? 'active' : '' }}" data-folder-name="all resources">
                            <div class="res-tree-row" style="padding-left: 12px;">
                                <span class="res-tree-spacer"></span>
                                <a href="{{ route('resources.index') }}" class="res-tree-link">
                                    <i class="ri-home-4-{{ $folderId === 0 ? 'fill' : 'line' }}"></i>
                                    <span class="res-tree-name">All Resources</span>
                                    @if($folderTree['root_count'] > 0)
                                        <span class="res-tree-count">{{ $folderTree['root_count'] }}</span>
                                    @endif
                                </a>
                            </div>
                        </li>
                        @include('resources.partials.folder-tree-nodes', ['nodes' => $folderTree['folders'], 'depth' => 0])
                    </ul>
                </div>
            </aside>
        </div>

        <div class="col-lg-9 col-xl-10">

    <div class="res-toolbar mb-3">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
            <nav class="res-breadcrumb small" id="resBreadcrumb">
                @foreach($breadcrumbs as $i => $crumb)
                    @if($i > 0)<span class="mx-1 text-muted">/</span>@endif
                    @if($loop->last)
                        <span class="fw-semibold">{{ $crumb['name'] }}</span>
                    @else
                        <a href="{{ route('resources.index', ['folder' => $crumb['id']]) }}">{{ $crumb['name'] }}</a>
                    @endif
                @endforeach
            </nav>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-soft-primary btn-sm" data-bs-toggle="modal" data-bs-target="#resFolderModal">
                    <i class="ri-folder-add-line"></i> New folder
                </button>
                <button type="button" class="btn btn-soft-success btn-sm" data-bs-toggle="modal" data-bs-target="#resLinkModal">
                    <i class="ri-links-line"></i> Add link
                </button>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#resFileModal">
                    <i class="ri-upload-2-line"></i> Upload file
                </button>
            </div>
        </div>

        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Search</label>
                <input type="search" id="resSearch" class="form-control form-control-sm" placeholder="Search title, file name, or URL">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Type</label>
                <select id="resTypeFilter" class="form-select form-select-sm">
                    <option value="all">All types</option>
                    <option value="link">Links</option>
                    <option value="gsheet">G Sheet</option>
                    <option value="spreadsheet">Excel</option>
                    <option value="image">Images</option>
                    <option value="pdf">PDFs</option>
                    <option value="document">Docs</option>
                    <option value="video">Videos</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Department</label>
                <select id="resDeptFilter" class="form-select form-select-sm" title="Department">
                    <option value="all">All</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Tag</label>
                <select id="resTagFilter" class="form-select form-select-sm" title="Tag">
                    <option value="all">All</option>
                    @foreach($tags as $tag)
                        <option value="{{ $tag }}">{{ $tag }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Resources</label>
                <select id="resResourceFilter" class="form-select form-select-sm" title="Resources">
                    <option value="all">All</option>
                    @foreach($resourceOptions as $resourceOption)
                        <option value="{{ $resourceOption }}">{{ $resourceOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="button" class="btn btn-primary btn-sm flex-fill" id="resApplyFilters">Apply</button>
                <button type="button" class="btn btn-light btn-sm flex-fill" id="resClearFilters">Clear</button>
            </div>
        </div>
    </div>


    <div id="resItemsWrap">
        <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
            <h6 class="text-muted text-uppercase small mb-0">Items</h6>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted" id="resCount"></span>
                <div class="btn-group btn-group-sm res-view-toggle" role="group" aria-label="View mode">
                    <button type="button" class="btn btn-light active" id="resViewGrid" title="Grid view"><i class="ri-grid-fill"></i></button>
                    <button type="button" class="btn btn-light" id="resViewTable" title="Table view"><i class="ri-table-line"></i></button>
                </div>
            </div>
        </div>
        <div id="resItemsGrid" class="row g-3"></div>
        <div id="resItemsTable" class="res-table-wrap d-none">
            <table class="table table-hover align-middle mb-0 res-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th title="Department">Dept</th>
                        <th title="Tag">Tag</th>
                        <th title="Resources">Resources</th>
                        <th>Size</th>
                        <th>Modified</th>
                        <th>Added by</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="resTableBody"></tbody>
            </table>
        </div>
        <div id="resEmpty" class="res-empty d-none">
            <i class="ri-folder-open-line fs-1 d-block mb-2"></i>
            <div>No resources in this folder yet.</div>
            <div class="small mt-1">Add a link or upload a file to get started.</div>
        </div>
        <nav id="resPagination" class="mt-3 d-none"></nav>
    </div>

    {{-- New folder --}}
    <div class="modal fade" id="resFolderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="resFolderForm">
                    <div class="modal-header">
                        <h5 class="modal-title">New folder</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Folder name</label>
                        <input type="text" name="name" class="form-control" required maxlength="255" placeholder="e.g. Training materials">
                        <input type="hidden" name="parent_id" value="{{ $folderId }}">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Add link --}}
    <div class="modal fade" id="resLinkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="resLinkForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Add link</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">URL</label>
                            <input type="url" name="location_url" class="form-control" required placeholder="https://docs.google.com/...">
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Department</label>
                                <select name="department_id" class="form-select">
                                    <option value="">No department</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Tag</label>
                                <select name="tag" class="form-select">
                                    <option value="">No tag</option>
                                    @foreach($tags as $tag)
                                        <option value="{{ $tag }}">{{ $tag }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Resources</label>
                                <select name="resource" class="form-select">
                                    <option value="">No resource</option>
                                    @foreach($resourceOptions as $resourceOption)
                                        <option value="{{ $resourceOption }}">{{ $resourceOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <input type="hidden" name="folder_id" value="{{ $folderId }}">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save link</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Upload file --}}
    <div class="modal fade" id="resFileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="resFileForm" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Upload file</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File</label>
                            <input type="file" name="file" class="form-control" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Department</label>
                                <select name="department_id" class="form-select">
                                    <option value="">No department</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Tag</label>
                                <select name="tag" class="form-select">
                                    <option value="">No tag</option>
                                    @foreach($tags as $tag)
                                        <option value="{{ $tag }}">{{ $tag }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Resources</label>
                                <select name="resource" class="form-select">
                                    <option value="">No resource</option>
                                    @foreach($resourceOptions as $resourceOption)
                                        <option value="{{ $resourceOption }}">{{ $resourceOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <input type="hidden" name="folder_id" value="{{ $folderId }}">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit item --}}
    <div class="modal fade" id="resEditModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="resEditForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit resource</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="resEditId">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" id="resEditTitle" class="form-control" required maxlength="255">
                        </div>
                        <div class="mb-3" id="resEditUrlWrap">
                            <label class="form-label">URL</label>
                            <input type="url" name="location_url" id="resEditUrl" class="form-control">
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Department</label>
                                <select name="department_id" id="resEditDepartment" class="form-select">
                                    <option value="">No department</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Tag</label>
                                <select name="tag" id="resEditTag" class="form-select">
                                    <option value="">No tag</option>
                                    @foreach($tags as $tag)
                                        <option value="{{ $tag }}">{{ $tag }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Resources</label>
                                <select name="resource" id="resEditResource" class="form-select">
                                    <option value="">No resource</option>
                                    @foreach($resourceOptions as $resourceOption)
                                        <option value="{{ $resourceOption }}">{{ $resourceOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

        </div>
    </div>
@endsection

@section('script')
<script>
(function () {
    const folderId = {{ (int) $folderId }};
    const canDelete = {{ auth()->user() && auth()->user()->email === 'president@5core.com' ? 'true' : 'false' }};
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let currentPage = 1;
    let currentItems = [];
    let viewMode = localStorage.getItem('resViewMode') === 'table' ? 'table' : 'grid';

    const grid = document.getElementById('resItemsGrid');
    const tableWrap = document.getElementById('resItemsTable');
    const tableBody = document.getElementById('resTableBody');
    const empty = document.getElementById('resEmpty');
    const countEl = document.getElementById('resCount');
    const pagination = document.getElementById('resPagination');
    const viewGridBtn = document.getElementById('resViewGrid');
    const viewTableBtn = document.getElementById('resViewTable');

    function iconClass(type) {
        return {
            link: 'res-icon-link',
            gsheet: 'res-icon-spreadsheet',
            spreadsheet: 'res-icon-spreadsheet',
            image: 'res-icon-image',
            pdf: 'res-icon-pdf',
            video: 'res-icon-link',
            document: 'res-icon-default',
        }[type] || 'res-icon-default';
    }

    function typeLabel(type) {
        if (type === 'gsheet') return 'G Sheet';
        return (type || 'file').replace('_', ' ');
    }

    function formatDate(value) {
        if (!value) return '';
        return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function formatSize(bytes) {
        const n = parseInt(bytes, 10);
        if (!n || isNaN(n)) return '';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let i = 0, size = n;
        while (size >= 1024 && i < units.length - 1) { size /= 1024; i++; }
        return `${size.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
    }

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    async function loadItems(page = 1) {
        currentPage = page;
        const search = document.getElementById('resSearch').value.trim();
        const type = document.getElementById('resTypeFilter').value;
        const dept = document.getElementById('resDeptFilter').value;
        const tag = document.getElementById('resTagFilter').value;
        const resource = document.getElementById('resResourceFilter').value;
        const params = new URLSearchParams({ folder: folderId, page, per_page: 24 });
        if (search) params.set('search', search);
        if (type && type !== 'all') params.set('type', type);
        if (dept && dept !== 'all') params.set('department', dept);
        if (tag && tag !== 'all') params.set('tag', tag);
        if (resource && resource !== 'all') params.set('resource', resource);

        const res = await fetch(`{{ route('resources.data') }}?${params}`, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();
        renderItems(json.data || []);
        renderPagination(json.meta || {});
        countEl.textContent = `${json.meta?.total ?? 0} item(s)`;
    }

    function openItem(url, isLink) {
        if (isLink === '1' || isLink === true) {
            window.open(url, '_blank');
        } else if (url && url !== '#') {
            window.location = url;
        }
    }

    function renderItems(items) {
        currentItems = items;
        grid.innerHTML = '';
        tableBody.innerHTML = '';

        if (!items.length) {
            empty.classList.remove('d-none');
            grid.classList.add('d-none');
            tableWrap.classList.add('d-none');
            return;
        }
        empty.classList.add('d-none');

        if (viewMode === 'table') {
            grid.classList.add('d-none');
            tableWrap.classList.remove('d-none');
            renderTable(items);
        } else {
            tableWrap.classList.add('d-none');
            grid.classList.remove('d-none');
            renderGrid(items);
        }
    }

    function renderGrid(items) {
        items.forEach(item => {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-4 col-lg-3 col-xl-2';
            const openUrl = item.open_url || '#';
            const isLink = item.is_link;
            col.innerHTML = `
                <div class="res-item-card p-3 h-100 d-flex flex-column">
                    <div class="d-flex align-items-start gap-3 flex-grow-1" data-open="${escapeHtml(openUrl)}" data-link="${isLink ? '1' : '0'}" style="cursor:pointer">
                        <div class="res-icon-wrap ${iconClass(item.file_type)}"><i class="${item.icon || 'ri-file-line'}"></i></div>
                        <div class="flex-grow-1 min-w-0 res-card-body">
                            <div class="res-item-title" title="${escapeHtml(item.title)}">${escapeHtml(item.title)}</div>
                            <div class="res-meta mt-1 text-capitalize">${escapeHtml(typeLabel(item.file_type))}</div>
                            <div class="res-meta">${formatDate(item.updated_at)}</div>
                        </div>
                    </div>
                    <div class="res-actions d-flex gap-1 justify-content-end mt-2 pt-2 border-top">
                        <button type="button" class="btn btn-light btn-sm res-edit" data-item='${JSON.stringify(item).replace(/'/g, '&#39;')}' title="Edit"><i class="ri-edit-line"></i></button>
                        ${canDelete ? `<button type="button" class="btn btn-light btn-sm text-danger res-delete" data-id="${item.id}" title="Delete"><i class="ri-delete-bin-line"></i></button>` : ''}
                    </div>
                </div>`;

            col.querySelector('[data-open]').addEventListener('click', function (e) {
                if (e.target.closest('.res-actions')) return;
                openItem(this.dataset.open, this.dataset.link);
            });

            grid.appendChild(col);
        });

        bindItemActions(grid);
    }

    function renderTable(items) {
        items.forEach(item => {
            const tr = document.createElement('tr');
            const openUrl = item.open_url || '#';
            const isLink = item.is_link;
            tr.innerHTML = `
                <td>
                    <div class="d-flex align-items-center gap-2 res-row-name" data-open="${escapeHtml(openUrl)}" data-link="${isLink ? '1' : '0'}">
                        <div class="res-icon-wrap ${iconClass(item.file_type)}"><i class="${item.icon || 'ri-file-line'}"></i></div>
                        <span class="res-row-title" title="${escapeHtml(item.title)}">${escapeHtml(item.title)}</span>
                    </div>
                </td>
                <td class="text-capitalize">${escapeHtml(typeLabel(item.file_type))}</td>
                <td>${item.department_name ? `<span class="badge bg-light text-dark border" title="Department">${escapeHtml(item.department_name)}</span>` : '<span class="text-muted">&mdash;</span>'}</td>
                <td>${item.tag ? `<span class="badge bg-soft-primary text-primary" title="Tag">${escapeHtml(item.tag)}</span>` : '<span class="text-muted">&mdash;</span>'}</td>
                <td>${item.resource ? `<span class="badge bg-soft-info text-info" title="Resources">${escapeHtml(item.resource)}</span>` : '<span class="text-muted">&mdash;</span>'}</td>
                <td>${item.is_link ? '<span class="text-muted">&mdash;</span>' : (formatSize(item.file_size) || '<span class="text-muted">&mdash;</span>')}</td>
                <td class="text-nowrap">${formatDate(item.updated_at) || '<span class="text-muted">&mdash;</span>'}</td>
                <td>${escapeHtml(item.creator?.name || '') || '<span class="text-muted">&mdash;</span>'}</td>
                <td class="text-end res-actions">
                    <button type="button" class="btn btn-light btn-sm res-edit" data-item='${JSON.stringify(item).replace(/'/g, '&#39;')}' title="Edit"><i class="ri-edit-line"></i></button>
                    ${canDelete ? `<button type="button" class="btn btn-light btn-sm text-danger res-delete" data-id="${item.id}" title="Delete"><i class="ri-delete-bin-line"></i></button>` : ''}
                </td>`;

            tr.querySelector('.res-row-name').addEventListener('click', function () {
                openItem(this.dataset.open, this.dataset.link);
            });

            tableBody.appendChild(tr);
        });

        bindItemActions(tableBody);
    }

    function bindItemActions(container) {
        container.querySelectorAll('.res-edit').forEach(btn => {
            btn.addEventListener('click', () => {
                const item = JSON.parse(btn.dataset.item);
                document.getElementById('resEditId').value = item.id;
                document.getElementById('resEditTitle').value = item.title || '';
                document.getElementById('resEditUrl').value = item.location_url || '';
                document.getElementById('resEditDepartment').value = item.department_id || '';
                document.getElementById('resEditTag').value = item.tag || '';
                document.getElementById('resEditResource').value = item.resource || '';
                document.getElementById('resEditUrlWrap').classList.toggle('d-none', !item.is_link);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('resEditModal')).show();
            });
        });

        container.querySelectorAll('.res-delete').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Delete this resource?')) return;
                await fetch(`{{ url('/resources/item') }}/${btn.dataset.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                loadItems(currentPage);
            });
        });
    }

    function setViewMode(mode) {
        viewMode = mode === 'table' ? 'table' : 'grid';
        localStorage.setItem('resViewMode', viewMode);
        viewGridBtn.classList.toggle('active', viewMode === 'grid');
        viewTableBtn.classList.toggle('active', viewMode === 'table');
        renderItems(currentItems);
    }

    function renderPagination(meta) {
        if (!meta || meta.last_page <= 1) {
            pagination.classList.add('d-none');
            pagination.innerHTML = '';
            return;
        }
        pagination.classList.remove('d-none');
        let html = '<ul class="pagination pagination-sm mb-0">';
        for (let p = 1; p <= meta.last_page; p++) {
            html += `<li class="page-item ${p === meta.current_page ? 'active' : ''}">
                <button type="button" class="page-link" data-page="${p}">${p}</button></li>`;
        }
        html += '</ul>';
        pagination.innerHTML = html;
        pagination.querySelectorAll('[data-page]').forEach(btn => {
            btn.addEventListener('click', () => loadItems(parseInt(btn.dataset.page, 10)));
        });
    }

    async function postForm(url, form, isMultipart = false) {
        const opts = {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: isMultipart ? new FormData(form) : new URLSearchParams(new FormData(form)),
        };
        const res = await fetch(url, opts);
        const json = await res.json();
        if (!res.ok) throw json;
        return json;
    }

    document.getElementById('resApplyFilters').addEventListener('click', () => loadItems(1));
    document.getElementById('resClearFilters').addEventListener('click', () => {
        document.getElementById('resSearch').value = '';
        document.getElementById('resTypeFilter').value = 'all';
        document.getElementById('resDeptFilter').value = 'all';
        document.getElementById('resTagFilter').value = 'all';
        document.getElementById('resResourceFilter').value = 'all';
        loadItems(1);
    });
    document.getElementById('resDeptFilter').addEventListener('change', () => loadItems(1));
    document.getElementById('resTagFilter').addEventListener('change', () => loadItems(1));
    document.getElementById('resResourceFilter').addEventListener('change', () => loadItems(1));
    document.getElementById('resSearch').addEventListener('keydown', e => {
        if (e.key === 'Enter') loadItems(1);
    });

    document.getElementById('resFolderForm').addEventListener('submit', async e => {
        e.preventDefault();
        await postForm('{{ route('resources.folder.store') }}', e.target);
        bootstrap.Modal.getInstance(document.getElementById('resFolderModal')).hide();
        e.target.reset();
        e.target.querySelector('[name=parent_id]').value = folderId;
        window.location.reload();
    });

    document.getElementById('resLinkForm').addEventListener('submit', async e => {
        e.preventDefault();
        await postForm('{{ route('resources.link.store') }}', e.target);
        bootstrap.Modal.getInstance(document.getElementById('resLinkModal')).hide();
        e.target.reset();
        e.target.querySelector('[name=folder_id]').value = folderId;
        loadItems(1);
    });

    document.getElementById('resFileForm').addEventListener('submit', async e => {
        e.preventDefault();
        await postForm('{{ route('resources.file.store') }}', e.target, true);
        bootstrap.Modal.getInstance(document.getElementById('resFileModal')).hide();
        e.target.reset();
        e.target.querySelector('[name=folder_id]').value = folderId;
        loadItems(1);
    });

    document.getElementById('resEditForm').addEventListener('submit', async e => {
        e.preventDefault();
        const id = document.getElementById('resEditId').value;
        const body = new URLSearchParams(new FormData(e.target));
        body.append('_method', 'PUT');
        await fetch(`{{ url('/resources/item') }}/${id}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body,
        });
        bootstrap.Modal.getInstance(document.getElementById('resEditModal')).hide();
        loadItems(currentPage);
    });

    viewGridBtn.addEventListener('click', () => setViewMode('grid'));
    viewTableBtn.addEventListener('click', () => setViewMode('table'));
    viewGridBtn.classList.toggle('active', viewMode === 'grid');
    viewTableBtn.classList.toggle('active', viewMode === 'table');

    loadItems(1);

    document.querySelectorAll('.res-tree-toggle').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            e.stopPropagation();
            btn.closest('.res-tree-node')?.classList.toggle('open');
        });
    });

    const folderSearch = document.getElementById('resFolderSearch');
    if (folderSearch) {
        folderSearch.addEventListener('input', () => {
            const q = folderSearch.value.trim().toLowerCase();
            document.querySelectorAll('#resFolderTree .res-tree-node').forEach(node => {
                if (!node.dataset.folderName) return;
                const match = !q || node.dataset.folderName.includes(q);
                node.classList.toggle('hidden-by-filter', !match);
                if (match && q) {
                    node.classList.add('open');
                    node.querySelectorAll('.res-tree-node').forEach(child => child.classList.remove('hidden-by-filter'));
                }
            });
            if (!q) {
                document.querySelectorAll('#resFolderTree .res-tree-node.open').forEach(node => {
                    if (!node.classList.contains('active') && !node.querySelector('.res-tree-node.active')) {
                        node.classList.remove('open');
                    }
                });
            }
        });
    }
})();
</script>
@endsection
