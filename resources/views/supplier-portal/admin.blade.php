@extends('layouts.vertical', ['title' => $title])

@section('css')
<style>
    #spAdminTabs { flex-wrap: wrap; row-gap: 4px; }
    #spAdminTabs .nav-link { white-space: nowrap; }
    .sp-lookup-wrap { position: relative; }
    .sp-lookup-list {
        position: fixed;
        z-index: 2000;
        display: none;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 6px;
        max-height: 220px;
        overflow: auto;
        box-shadow: 0 8px 20px rgba(0,0,0,.08);
        padding: 4px 0;
    }
    .sp-lookup-list button {
        display: block;
        width: 100%;
        text-align: left;
        border: 0;
        background: #fff;
        padding: 7px 10px;
        font-size: 13px;
    }
    .sp-lookup-list button:hover,
    .sp-lookup-list button.is-active { background: #fdecec; }
    .sp-lookup-empty { padding: 8px 10px; color: #888; font-size: 12px; }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="mb-1">Supplier Portal</h4>
            <div class="text-muted">Upload logos and packaging files. Suppliers open the public link — no login.</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#spMainModal">Main</button>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#spHeaderModal">Page Header</button>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#spDataModal">+ Data</button>
            <a class="btn btn-outline-dark" href="{{ $publicUrl }}" target="_blank" rel="noopener">Open public page</a>
            <button type="button" class="btn btn-danger" id="spCopyLink">Copy public link</button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="modal fade" id="spMainModal" tabindex="-1" aria-labelledby="spMainModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="spMainModalLabel">Page content</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="{{ route('supplier-portal.admin.settings') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Company name</label>
                                <input class="form-control" name="company_name" value="{{ old('company_name', $settings->company_name) }}" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Hero title</label>
                                <input class="form-control" name="hero_title" value="{{ old('hero_title', $settings->hero_title) }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Hero subtitle</label>
                                <textarea class="form-control" name="hero_subtitle" rows="2">{{ old('hero_subtitle', $settings->hero_subtitle) }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Help / contact email</label>
                                <input class="form-control" type="email" name="contact_email" value="{{ old('contact_email', $settings->contact_email) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Footer tagline</label>
                                <input class="form-control" name="footer_tagline" value="{{ old('footer_tagline', $settings->footer_tagline) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Full-width banner image</label>
                                <input class="form-control" type="file" name="hero_image" accept="image/*">
                                <div class="form-text">Wide image (about 1920×500 or larger). Welcome text sits on top of it.</div>
                                @if($settings->hero_image_path)
                                    <div class="mt-2 d-flex align-items-center gap-3">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->hero_image_path) }}" alt="" style="height:64px;border-radius:6px;object-fit:cover;">
                                        <button form="spHeroDelete" class="btn btn-sm btn-outline-danger">Remove banner</button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button class="btn btn-danger" type="submit">Save page content</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <form id="spHeroDelete" method="post" action="{{ route('supplier-portal.admin.hero.destroy') }}" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    @php
        $headers = $headers ?? [];
        foreach (array_keys($categories) as $headerKey) {
            if (! isset($headers[$headerKey])) {
                $headers[$headerKey] = collect();
            }
        }
    @endphp
    <div class="modal fade" id="spHeaderModal" tabindex="-1" aria-labelledby="spHeaderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" action="{{ route('supplier-portal.admin.headers') }}" id="spHeaderForm">
                    @csrf
                    <input type="hidden" name="tab" id="spHeaderTab" value="{{ $activeTab }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="spHeaderModalLabel">Page Header</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3">Add one or more titles with instructions / guidelines for each category page. These show above the files on the public portal.</p>
                        <ul class="nav nav-tabs nav-bordered mb-3" id="spHeaderTabs" role="tablist">
                            @foreach($categories as $key => $label)
                                <li class="nav-item" role="presentation">
                                    <button
                                        type="button"
                                        class="nav-link{{ $key === $activeTab ? ' active' : '' }}"
                                        id="sp-header-tab-{{ $key }}"
                                        data-bs-toggle="tab"
                                        data-bs-target="#sp-header-pane-{{ $key }}"
                                        data-tab="{{ $key }}"
                                        role="tab"
                                    >{{ $label }}</button>
                                </li>
                            @endforeach
                        </ul>
                        <div class="tab-content">
                            @foreach($categories as $key => $label)
                                @php $pageHeaders = $headers[$key] ?? collect(); @endphp
                                <div
                                    class="tab-pane fade{{ $key === $activeTab ? ' show active' : '' }}"
                                    id="sp-header-pane-{{ $key }}"
                                    role="tabpanel"
                                    data-category="{{ $key }}"
                                >
                                    <div class="sp-header-list" data-category="{{ $key }}">
                                        @forelse($pageHeaders as $header)
                                            <div class="sp-header-card border rounded p-3 mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <strong>Header</strong>
                                                    <button type="button" class="btn btn-sm btn-outline-danger sp-header-remove">Remove</button>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Title</label>
                                                    <input class="form-control" name="headers[{{ $key }}][{{ $loop->index }}][title]" value="{{ $header->title }}" placeholder="e.g. Artwork guidelines">
                                                </div>
                                                <div>
                                                    <label class="form-label">Instructions / Guidelines</label>
                                                    <textarea class="form-control" name="headers[{{ $key }}][{{ $loop->index }}][instructions]" rows="3" placeholder="Write instructions for this page">{{ $header->instructions }}</textarea>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="sp-header-card border rounded p-3 mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <strong>Header</strong>
                                                    <button type="button" class="btn btn-sm btn-outline-danger sp-header-remove">Remove</button>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Title</label>
                                                    <input class="form-control" name="headers[{{ $key }}][0][title]" value="" placeholder="e.g. Artwork guidelines">
                                                </div>
                                                <div>
                                                    <label class="form-label">Instructions / Guidelines</label>
                                                    <textarea class="form-control" name="headers[{{ $key }}][0][instructions]" rows="3" placeholder="Write instructions for this page"></textarea>
                                                </div>
                                            </div>
                                        @endforelse
                                    </div>
                                    <button type="button" class="btn btn-outline-dark btn-sm sp-header-add" data-category="{{ $key }}">+ Add header</button>
                                    @if(\App\Support\SupplierPortalPackingData::usesPackingGrid($key))
                                        @include('supplier-portal.partials.packing-inner-grid', ['prefix' => 'spPiHeader_'.$key, 'editable' => true])
                                    @endif
                                    @if(\App\Support\SupplierPortalDimWtData::usesDimWtGrid($key))
                                        @include('supplier-portal.partials.dim-wt-item-grid', ['prefix' => 'spDwHeader_'.$key, 'editable' => true, 'variant' => 'pkg'])
                                    @endif
                                    @if(\App\Support\SupplierPortalDimWtData::usesDimWtCoverGrid($key))
                                        @include('supplier-portal.partials.dim-wt-item-grid', ['prefix' => 'spDwCoverHeader_'.$key, 'editable' => true, 'variant' => 'cover'])
                                    @endif
                                    @if(\App\Support\SupplierPortalDimWtData::usesDimWtSkuGrid($key))
                                        @include('supplier-portal.partials.dim-wt-item-grid', ['prefix' => 'spDwSkuHeader_'.$key, 'editable' => true, 'variant' => 'sku', 'category' => $key])
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button class="btn btn-danger" type="submit">Save page headers</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <template id="spHeaderCardTpl">
        <div class="sp-header-card border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Header</strong>
                <button type="button" class="btn btn-sm btn-outline-danger sp-header-remove">Remove</button>
            </div>
            <div class="mb-2">
                <label class="form-label">Title</label>
                <input class="form-control" name="headers[__CAT__][__I__][title]" value="" placeholder="e.g. Artwork guidelines">
            </div>
            <div>
                <label class="form-label">Instructions / Guidelines</label>
                <textarea class="form-control" name="headers[__CAT__][__I__][instructions]" rows="3" placeholder="Write instructions for this page"></textarea>
            </div>
        </div>
    </template>

    <div class="modal fade" id="spDataModal" tabindex="-1" aria-labelledby="spDataModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post" action="{{ route('supplier-portal.admin.assets.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="spDataModalLabel">Add new file</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" id="spDataCategory" required>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}" @selected(old('category', $activeTab) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Parent</label>
                                <div class="sp-lookup-wrap">
                                    <input
                                        class="form-control sp-lookup"
                                        name="parent"
                                        id="spDataParent"
                                        value="{{ old('parent') }}"
                                        placeholder="Type to search parent"
                                        autocomplete="off"
                                        data-type="parent"
                                        data-pair="#spDataSku"
                                    >
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SKU</label>
                                <div class="sp-lookup-wrap">
                                    <input
                                        class="form-control sp-lookup"
                                        name="sku"
                                        id="spDataSku"
                                        value="{{ old('sku') }}"
                                        placeholder="Type to search SKU"
                                        autocomplete="off"
                                        data-type="sku"
                                        data-pair="#spDataParent"
                                    >
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-muted fw-normal">(optional for multi-upload)</span></label>
                            <input class="form-control" name="title" value="{{ old('title') }}" placeholder="e.g. 5 Core Logo – Full Color">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Files</label>
                            <input class="form-control" type="file" name="files[]" multiple required>
                            <div class="form-text">Number is assigned automatically (e.g. BA-01) and shown under the file.</div>
                        </div>
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="add_to_siblings"
                                value="1"
                                id="spAddSiblings"
                                @checked(old('add_to_siblings'))
                            >
                            <label class="form-check-label" for="spAddSiblings">Add to siblings</label>
                            <div class="form-text" id="spSiblingsHint">Also attach this file to every SKU under the same parent.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button class="btn btn-danger" type="submit">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header pb-0">
            <ul class="nav nav-tabs nav-bordered" id="spAdminTabs" role="tablist">
                @foreach($categories as $key => $label)
                    @php $count = ($grouped[$key] ?? collect())->count(); @endphp
                    <li class="nav-item" role="presentation">
                        <button
                            type="button"
                            class="nav-link{{ $key === $activeTab ? ' active' : '' }}"
                            id="sp-tab-btn-{{ $key }}"
                            data-bs-toggle="tab"
                            data-bs-target="#sp-tab-{{ $key }}"
                            data-tab="{{ $key }}"
                            role="tab"
                            aria-controls="sp-tab-{{ $key }}"
                            aria-selected="{{ $key === $activeTab ? 'true' : 'false' }}"
                        >
                            {{ $label }}
                            <span class="badge bg-light text-dark ms-1">{{ $count }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="card-body tab-content">
            @foreach($categories as $key => $label)
                <div
                    class="tab-pane fade{{ $key === $activeTab ? ' show active' : '' }}"
                    id="sp-tab-{{ $key }}"
                    role="tabpanel"
                    aria-labelledby="sp-tab-btn-{{ $key }}"
                >
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Preview</th>
                                    <th>Title</th>
                                    <th>Parent</th>
                                    <th>SKU</th>
                                    <th>Category</th>
                                    <th>File</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($grouped[$key] as $asset)
                                    <tr>
                                        <td style="width:88px">
                                            <div class="text-center">
                                                @if($asset->isImage())
                                                    <img src="{{ $asset->publicUrl() }}" alt="" style="height:44px;max-width:70px;object-fit:contain;">
                                                @else
                                                    <span class="badge bg-danger">{{ $asset->extensionLabel() }}</span>
                                                @endif
                                                <div class="small fw-semibold mt-1">{{ $asset->codeLabel($loop->iteration) }}</div>
                                            </div>
                                        </td>
                                        <td>
                                            <form id="sp-asset-{{ $asset->id }}" method="post" action="{{ route('supplier-portal.admin.assets.update', $asset) }}">
                                                @csrf
                                                @method('PUT')
                                            </form>
                                            <input class="form-control form-control-sm" form="sp-asset-{{ $asset->id }}" name="title" value="{{ $asset->title }}" required>
                                        </td>
                                        <td style="min-width:150px">
                                            <div class="sp-lookup-wrap">
                                                <input
                                                    class="form-control form-control-sm sp-lookup"
                                                    form="sp-asset-{{ $asset->id }}"
                                                    name="parent"
                                                    value="{{ $asset->parent }}"
                                                    placeholder="Parent"
                                                    autocomplete="off"
                                                    data-type="parent"
                                                    data-pair="#sp-sku-{{ $asset->id }}"
                                                    id="sp-parent-{{ $asset->id }}"
                                                >
                                            </div>
                                        </td>
                                        <td style="min-width:150px">
                                            <div class="sp-lookup-wrap">
                                                <input
                                                    class="form-control form-control-sm sp-lookup"
                                                    form="sp-asset-{{ $asset->id }}"
                                                    name="sku"
                                                    value="{{ $asset->sku }}"
                                                    placeholder="SKU"
                                                    autocomplete="off"
                                                    data-type="sku"
                                                    data-pair="#sp-parent-{{ $asset->id }}"
                                                    id="sp-sku-{{ $asset->id }}"
                                                >
                                            </div>
                                        </td>
                                        <td style="min-width:200px">
                                            <select class="form-select form-select-sm" form="sp-asset-{{ $asset->id }}" name="category" required>
                                                @foreach($categories as $optionKey => $optionLabel)
                                                    <option value="{{ $optionKey }}" @selected((\App\Models\SupplierPortalAsset::resolveCategoryKey((string) $asset->category) ?? $asset->category) === $optionKey)>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-muted small">{{ $asset->file_name }} · {{ $asset->sizeLabel() }}</td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-secondary" form="sp-asset-{{ $asset->id }}" type="submit">Edit</button>
                                            <a class="btn btn-sm btn-outline-dark" href="{{ route('supplier-portal.download', $asset) }}">Download</a>
                                            <form method="post" action="{{ route('supplier-portal.admin.assets.destroy', $asset) }}" class="d-inline" onsubmit="return confirm('Remove this file from the supplier page?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-muted">No files in this section yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(\App\Support\SupplierPortalPackingData::usesPackingGrid($key))
                        @include('supplier-portal.partials.packing-inner-grid', ['prefix' => 'spPiAdmin_'.$key, 'editable' => true])
                    @endif
                    @if(\App\Support\SupplierPortalDimWtData::usesDimWtGrid($key))
                        @include('supplier-portal.partials.dim-wt-item-grid', ['prefix' => 'spDwAdmin_'.$key, 'editable' => true, 'variant' => 'pkg'])
                    @endif
                    @if(\App\Support\SupplierPortalDimWtData::usesDimWtCoverGrid($key))
                        @include('supplier-portal.partials.dim-wt-item-grid', ['prefix' => 'spDwCoverAdmin_'.$key, 'editable' => true, 'variant' => 'cover'])
                    @endif
                    @if(\App\Support\SupplierPortalDimWtData::usesDimWtSkuGrid($key))
                        @include('supplier-portal.partials.dim-wt-item-grid', ['prefix' => 'spDwSkuAdmin_'.$key, 'editable' => true, 'variant' => 'sku', 'category' => $key])
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.bootstrap) return;
    @if($errors->hasAny(['company_name', 'hero_title', 'hero_subtitle', 'contact_email', 'footer_tagline', 'hero_image']))
    bootstrap.Modal.getOrCreateInstance(document.getElementById('spMainModal')).show();
    @elseif($errors->hasAny(['headers']))
    bootstrap.Modal.getOrCreateInstance(document.getElementById('spHeaderModal')).show();
    @elseif($errors->hasAny(['category', 'title', 'sku', 'parent', 'add_to_siblings', 'files', 'files.0', 'files.*']))
    bootstrap.Modal.getOrCreateInstance(document.getElementById('spDataModal')).show();
    @endif
});
(function () {
    var searchUrl = @json(route('supplier-portal.admin.products.search'));
    var list = document.createElement('div');
    list.className = 'sp-lookup-list';
    document.body.appendChild(list);
    var activeInput = null;
    var timer = null;

    function hideList() {
        list.style.display = 'none';
        list.innerHTML = '';
        activeInput = null;
    }

    function placeList(input) {
        var r = input.getBoundingClientRect();
        list.style.left = r.left + 'px';
        list.style.top = (r.bottom + 2) + 'px';
        list.style.width = Math.max(r.width, 200) + 'px';
        list.style.display = 'block';
    }

    function pick(input, item) {
        var type = input.getAttribute('data-type');
        if (type === 'sku') {
            input.value = item.sku || '';
            var pair = document.querySelector(input.getAttribute('data-pair') || '');
            if (pair && item.parent) pair.value = item.parent;
        } else {
            input.value = item.parent || '';
        }
        hideList();
        if (typeof updateSiblingHint === 'function') updateSiblingHint();
    }

    function render(input, items) {
        list.innerHTML = '';
        if (!items.length) {
            list.innerHTML = '<div class="sp-lookup-empty">No matches</div>';
            placeList(input);
            return;
        }
        items.forEach(function (item) {
            var btn = document.createElement('button');
            btn.type = 'button';
            if (input.getAttribute('data-type') === 'sku') {
                btn.textContent = item.sku + (item.parent ? '  ·  ' + item.parent : '');
            } else {
                btn.textContent = item.parent;
            }
            btn.addEventListener('mousedown', function (e) {
                e.preventDefault();
                pick(input, item);
            });
            list.appendChild(btn);
        });
        placeList(input);
    }

    function search(input) {
        var q = (input.value || '').trim();
        if (q.length < 1) {
            hideList();
            return;
        }
        var type = input.getAttribute('data-type') || 'sku';
        fetch(searchUrl + '?type=' + encodeURIComponent(type) + '&q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) { return res.json(); }).then(function (data) {
            if (activeInput !== input) return;
            render(input, data.items || []);
        }).catch(function () {
            if (activeInput === input) hideList();
        });
    }

    document.addEventListener('input', function (e) {
        var input = e.target.closest('.sp-lookup');
        if (!input) return;
        activeInput = input;
        clearTimeout(timer);
        timer = setTimeout(function () { search(input); }, 180);
    });
    document.addEventListener('focusin', function (e) {
        if (e.target.classList && e.target.classList.contains('sp-lookup') && e.target.value.trim()) {
            activeInput = e.target;
            search(e.target);
        }
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.sp-lookup') && !e.target.closest('.sp-lookup-list')) {
            hideList();
        }
    });
    window.addEventListener('scroll', function (e) {
        if (list.contains(e.target)) return;
        hideList();
    }, true);

    var hint = document.getElementById('spSiblingsHint');
    var hintTimer = null;
    var defaultHint = 'Also attach this file to every SKU under the same parent.';
    function updateSiblingHint() {
        if (!hint) return;
        var parent = (document.getElementById('spDataParent')?.value || '').trim();
        var sku = (document.getElementById('spDataSku')?.value || '').trim();
        var q = parent || sku;
        if (!q) {
            hint.textContent = defaultHint;
            return;
        }
        fetch(searchUrl + '?type=siblings&q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) { return res.json(); }).then(function (data) {
            var n = data.count || 0;
            if (n > 1) {
                hint.textContent = 'This file will be added to ' + n + ' SKUs under this parent.';
            } else if (n === 1) {
                hint.textContent = 'Only one SKU found under this parent.';
            } else {
                hint.textContent = 'No sibling SKUs found for this parent.';
            }
        }).catch(function () {
            hint.textContent = defaultHint;
        });
    }
    ['spDataParent', 'spDataSku'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', updateSiblingHint);
        el.addEventListener('blur', updateSiblingHint);
        el.addEventListener('input', function () {
            clearTimeout(hintTimer);
            hintTimer = setTimeout(updateSiblingHint, 400);
        });
    });
})();
document.querySelectorAll('#spAdminTabs [data-bs-toggle="tab"]').forEach(function (btn) {
    btn.addEventListener('shown.bs.tab', function (e) {
        var key = e.target.getAttribute('data-tab');
        if (!key) return;
        var url = new URL(window.location.href);
        url.searchParams.set('tab', key);
        history.replaceState(null, '', url);
        var cat = document.getElementById('spDataCategory');
        if (cat) cat.value = key;
        var headerTab = document.getElementById('spHeaderTab');
        if (headerTab) headerTab.value = key;
    });
});
document.querySelectorAll('#spHeaderTabs [data-bs-toggle="tab"]').forEach(function (btn) {
    btn.addEventListener('shown.bs.tab', function (e) {
        var key = e.target.getAttribute('data-tab');
        var headerTab = document.getElementById('spHeaderTab');
        if (key && headerTab) headerTab.value = key;
    });
});
document.querySelectorAll('.sp-header-add').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var cat = btn.getAttribute('data-category');
        var list = document.querySelector('.sp-header-list[data-category="' + cat + '"]');
        var tpl = document.getElementById('spHeaderCardTpl');
        if (!list || !tpl) return;
        var html = tpl.innerHTML.replace(/__CAT__/g, cat).replace(/__I__/g, String(list.children.length));
        list.insertAdjacentHTML('beforeend', html);
    });
});
document.addEventListener('click', function (e) {
    var remove = e.target.closest('.sp-header-remove');
    if (!remove) return;
    var card = remove.closest('.sp-header-card');
    var list = remove.closest('.sp-header-list');
    if (card) card.remove();
    if (list && list.children.length === 0) {
        var addBtn = list.parentElement.querySelector('.sp-header-add');
        if (addBtn) addBtn.click();
    }
});
document.getElementById('spCopyLink')?.addEventListener('click', async function () {
    try {
        await navigator.clipboard.writeText(@json($publicUrl));
        this.textContent = 'Copied';
        setTimeout(() => { this.textContent = 'Copy public link'; }, 1600);
    } catch (e) {
        prompt('Copy this link', @json($publicUrl));
    }
});
</script>
@endsection
