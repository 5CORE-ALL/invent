@extends('layouts.vertical', ['title' => $form->name . ' Form Reports'])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    /* Pagination styling */
    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
        padding: 8px 16px;
        margin: 0 4px;
        border-radius: 6px;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
        background: #e0eaff;
        color: #2563eb;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
        background: #2563eb;
        color: white;
    }

    #img-popup {
        pointer-events: none;
    }

    .thumb-img {
        height: 64px;
        width: 64px;
        object-fit: cover;
        margin: 2px;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        background: #fff;
        cursor: zoom-in;
        vertical-align: middle;
        transition: all 0.2s ease-in-out;
        position: relative;
    }

    .thumb-img:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .thumb-img:hover::after {
        content: '';
        position: fixed;
        top: 50%;
        left: 50%;
        width: auto;
        height: auto;
        max-width: 80vw;
        max-height: 80vh;
        transform: translate(-50%, -50%);
        background: url(attr(src)) no-repeat center/contain;
        border: 2px solid #ccc;
        border-radius: 8px;
        z-index: 9999;
    }
</style>
@endsection
@section('content')
@include('layouts.shared.page-title', ['page_title' => $form->name . ' Form Reports', 'sub_title' => $form->name . ' Form Reports'])

@if (Session::has('flash_message'))
    <div class="alert alert-primary bg-primary text-white alert-dismissible fade show" role="alert"
        style="background-color: #03a744 !important; color: #fff !important;">
        {{ Session::get('flash_message') }}
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-end align-items-center mb-3 gap-2">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'rfq_form_reports'])
                        <a href="{{ url('/api/rfq-form/' . $form->slug) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary">
                            <i class="fas fa-file-invoice"></i> Form
                        </a>
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-warning dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-file-import"></i> Import
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" id="downloadTemplateBtn"><i class="fas fa-download me-2"></i>Download Template</a></li>
                                <li><a class="dropdown-item" href="#" id="importFileBtn"><i class="fas fa-file-import me-2"></i>Import Suppliers</a></li>
                            </ul>
                        </div>
                        <input type="file" id="importFileInput" accept=".xlsx,.xls,.csv" style="display:none;">
                        <button id="export-btn" class="btn btn-sm btn-success">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                    </div>
                </div>
                <div id="form-reports-table"></div>
            </div>
        </div>
    </div>
</div>

{{-- Edit Column Modal --}}
<div class="modal fade" id="rfqColEditModal" tabindex="-1" aria-labelledby="rfqColEditTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="rfqColEditTitle">Edit Column</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="rfqColEditBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="rfqColEditSaveBtn">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

@endsection
@section('script')
@php
    $templateColumns = array_merge(
        ['supplierName', 'companyName', 'supplierLink', 'productName'],
        collect($form->fields)->pluck('name')->filter()->values()->all()
    );
    $templateLabels = [
        'supplierName' => 'Supplier Name',
        'companyName' => 'Alias',
        'supplierLink' => 'Supplier Link',
        'productName' => 'Product Name',
    ];
    foreach ($form->fields as $f) {
        if (!empty($f['name'])) {
            $templateLabels[$f['name']] = $f['label'] ?? $f['name'];
        }
    }
    $linkedSkus = collect($form->linked_skus ?? [])->filter()->values()->all();
    foreach ($linkedSkus as $sku) {
        $templateLabels[$sku] = $sku;
    }
@endphp
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<!-- SheetJS for Excel Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
    const LINKED_SKUS = @json($linkedSkus);
    // Replace a broken/missing image with a small "N/A" placeholder instead of a broken icon
    window.rfqImgError = function(img){
        img.onerror = null;
        img.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34"><rect width="100%25" height="100%25" fill="%23f1f1f1" stroke="%23dcdcdc"/><text x="50%25" y="56%25" font-size="8" text-anchor="middle" fill="%23999">N/A</text></svg>';
        img.style.cursor = 'default';
        img.classList.remove('thumb-img');
    };

    function escapeHtml(str){
        return String(str === null || str === undefined ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function humanizeKey(k){
        return String(k).replace(/([A-Z])/g, ' $1').replace(/^./, c => c.toUpperCase());
    }

    // Header title renderer that appends an "edit column" pencil button
    function editableHeader(field){
        return function(cell){
            const title = cell.getValue() || '';
            return `<span class="rfq-th-label">${escapeHtml(title)}</span>
                    <i class="fa-solid fa-pen-to-square rfq-col-edit" data-field="${escapeHtml(field)}"
                       title="Edit column" style="cursor:pointer; margin-left:6px; color:#0d6efd;"></i>`;
        };
    }

    // Detect image/file links inside a text value and render them inline.
    // Images load automatically (browser downloads + shows them); files become download links.
    function renderMediaValue(text){
        const urlRegex = /(https?:\/\/[^\s,;"'<>]+|\/storage\/[^\s,;"'<>]+)/gi;
        if(!urlRegex.test(text)){
            return escapeHtml(text);
        }
        urlRegex.lastIndex = 0;

        return text.replace(urlRegex, function(url){
            const clean = url.replace(/[).,]+$/, '');
            const lower = clean.split('?')[0].toLowerCase();
            const isImg = /\.(jpe?g|png|gif|webp|bmp|svg|avif)$/.test(lower);
            const isFile = /\.(pdf|docx?|xlsx?|pptx?|zip|rar|7z|csv|txt|ai|psd|cdr|eps)$/.test(lower);
            // Uploaded files (under /storage) are almost always images even when the
            // stored name has no extension, so show them inline as thumbnails.
            const isStorageUpload = /^\/storage\//.test(clean) || /\/storage\//.test(clean);

            if(isImg || (isStorageUpload && !isFile)){
                return `<img src="${clean}" class="thumb-img" loading="lazy" onerror="rfqImgError(this)">`;
            } else if(isFile){
                const name = clean.split('/').pop().split('?')[0];
                return `<a href="${clean}" target="_blank" rel="noopener noreferrer" download
                            class="btn btn-sm btn-outline-secondary" title="Download ${escapeHtml(name)}">
                            <i class="fa-solid fa-download"></i>
                        </a>`;
            }
            return `<a href="${clean}" target="_blank" rel="noopener noreferrer"
                        class="btn btn-sm btn-outline-info" title="Open link">
                        <i class="fa-solid fa-link"></i>
                    </a>`;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {

        const FORM_ID = {{ $form->id }};
        const TEMPLATE_COLUMNS = @json($templateColumns);
        const TEMPLATE_LABELS = @json($templateLabels);
        let currentSubmissions = [];
        let currentColumnMeta = {};
        let currentRowKeys = [];
        let currentLabelFor = (k) => humanizeKey(k);
        let reportMeta = {};
        let editingCtx = {};

        function showToast(type, message){
            document.querySelectorAll('.custom-toast').forEach(t => t.remove());
            const toast = document.createElement('div');
            toast.className = `custom-toast toast align-items-center text-bg-${type} border-0 show position-fixed top-0 end-0 m-4`;
            toast.style.zIndex = 2000;
            toast.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        const table = new Tabulator("#form-reports-table", {
            ajaxURL: "/rfq-form/reports-data/{{ $form->id }}",
            ajaxConfig: "GET",
            layout: "fitData",
            pagination: false,
            movableColumns: false,
            resizableColumns: true,
            height: "600px",
            columns: [],
            ajaxResponse: function(url, params, response){
                const raw = response.data || [];
                const submissions = raw.map(r => r.data || {});
                reportMeta = response.report_meta || {};

                if(submissions.length === 0){
                    table.setColumns([]);
                    currentSubmissions = [];
                    currentColumnMeta = {};
                    currentRowKeys = [];
                    return [];
                }

                // Ordered union of attribute keys across all submissions (priority first)
                const priorityKeys = ['supplierName','companyName','supplierLink','productName','additionalPhotos'];
                const orderedKeys = [];
                const seen = {};
                priorityKeys.forEach(k => {
                    if(submissions.some(s => s.hasOwnProperty(k))){ orderedKeys.push(k); seen[k] = true; }
                });
                LINKED_SKUS.forEach(k => {
                    if(k && !seen[k]){ orderedKeys.push(k); seen[k] = true; }
                });
                submissions.forEach(s => {
                    Object.keys(s).forEach(k => {
                        if(!seen[k]){ seen[k] = true; orderedKeys.push(k); }
                    });
                });

                const rowKeys = orderedKeys.filter(k => k !== 'supplierName');

                // Expose data for the edit-column modal
                currentSubmissions = raw.map(r => ({ id: r.id, data: r.data || {} }));
                currentRowKeys = rowKeys;
                const labelFor = k => (reportMeta.labels && reportMeta.labels[k]) ? reportMeta.labels[k] : (TEMPLATE_LABELS[k] || humanizeKey(k));
                currentLabelFor = labelFor;

                const columnMeta = {
                    '__target':        { type: 'target',        title: 'Target' },
                    '__last_purchase': { type: 'last_purchase', title: 'Last Purchase' },
                    '__field':         { type: 'labels',        title: 'Field' },
                };

                // Build columns: reference columns, then the attribute label, then one column per supplier
                const columns = [
                    {
                        title: "Target", field: "__target", frozen: true, headerSort: false, width: 130, hozAlign: "left",
                        titleFormatter: editableHeader('__target'),
                        formatter: function(cell){ const v = cell.getValue(); return (v === null || v === undefined || v === '') ? '-' : v; }
                    },
                    {
                        title: "Last Purchase", field: "__last_purchase", frozen: true, headerSort: false, width: 140, hozAlign: "left",
                        titleFormatter: editableHeader('__last_purchase'),
                        formatter: function(cell){ const v = cell.getValue(); return (v === null || v === undefined || v === '') ? '-' : v; }
                    },
                    {
                        title: "Field", field: "__field", frozen: true, headerSort: false, width: 200,
                        titleFormatter: editableHeader('__field'),
                        formatter: function(cell){ return `<strong>${cell.getValue()}</strong>`; }
                    }
                ];

                submissions.forEach((s, idx) => {
                    const supName = (s.supplierName && String(s.supplierName).trim() !== '')
                        ? s.supplierName
                        : ('Supplier ' + (idx + 1));
                    const field = 'sup_' + idx;
                    columnMeta[field] = { type: 'supplier', idx: idx, submissionId: currentSubmissions[idx].id, title: supName };
                    columns.push({
                        title: supName,
                        field: field,
                        headerSort: false,
                        minWidth: 180,
                        hozAlign: "left",
                        titleFormatter: editableHeader(field),
                        formatter: function(cell){
                            const v = cell.getValue();
                            if(v === null || v === undefined || v === '') return '-';
                            if(typeof v !== 'string') return v;
                            if(/<[a-z]/i.test(v)) return v; // already rendered HTML (photos/links)
                            return renderMediaValue(v);
                        }
                    });
                });

                currentColumnMeta = columnMeta;
                table.setColumns(columns);

                // Build rows: one per attribute (supplierName is shown as the column header)
                const rows = rowKeys.map(k => {
                    const row = { __key: k, __field: labelFor(k) };
                    row.__target = (reportMeta.target && reportMeta.target[k] != null) ? reportMeta.target[k] : '';
                    row.__last_purchase = (reportMeta.last_purchase && reportMeta.last_purchase[k] != null) ? reportMeta.last_purchase[k] : '';
                    submissions.forEach((s, idx) => {
                        let val = s[k];
                        if(k === 'additionalPhotos'){
                            val = (Array.isArray(val) && val.length)
                                ? `<div class="d-flex flex-wrap">` + val.map((img, i) => `<img src="/storage/${img}" class="thumb-img" data-img-id="${i}" loading="lazy" onerror="rfqImgError(this)">`).join('') + `</div>`
                                : '-';
                        } else if(k === 'supplierLink'){
                            val = val ? `<a href="${val}" target="_blank" class="btn btn-sm btn-outline-info"><i class="fa-solid fa-link"></i></a>` : '-';
                        } else if(val === null || val === undefined || val === ''){
                            val = '-';
                        }
                        row['sup_' + idx] = val;
                    });
                    return row;
                });

                return rows;
            },
        });

        // ===== Auto-translate Chinese text to English =====
        const translationCache = new Map();

        function containsChinese(s){
            return typeof s === 'string' && /[\u3400-\u4dbf\u4e00-\u9fff\uf900-\ufaff]/.test(s);
        }

        async function translateText(text){
            if(translationCache.has(text)) return translationCache.get(text);
            try {
                const res = await fetch('https://translate.googleapis.com/translate_a/single?client=gtx&sl=zh-CN&tl=en&dt=t&q=' + encodeURIComponent(text));
                const data = await res.json();
                const translated = (data && Array.isArray(data[0]))
                    ? data[0].map(seg => seg[0]).join('')
                    : text;
                translationCache.set(text, translated);
                return translated;
            } catch(e){
                return text;
            }
        }

        async function autoTranslateReport(){
            // Translate supplier column headers
            for(const col of table.getColumns()){
                const field = col.getField();
                const def = col.getDefinition();
                if(field && field.startsWith('sup_') && containsChinese(def.title)){
                    const t = await translateText(def.title);
                    if(t && t !== def.title){
                        try { col.updateDefinition({ title: t }); } catch(e){}
                    }
                }
            }

            // Translate cell values (skip cells that contain HTML such as photos/links)
            for(const row of table.getRows()){
                const data = row.getData();
                const update = {};
                for(const key in data){
                    if(!key.startsWith('sup_')) continue;
                    const val = data[key];
                    if(containsChinese(val) && !/[<>]/.test(val)){
                        const t = await translateText(val);
                        if(t && t !== val) update[key] = t;
                    }
                }
                if(Object.keys(update).length){
                    row.update(update);
                }
            }
        }

        table.on("dataLoaded", function(){
            autoTranslateReport();
        });


        // ===== Edit column modal =====
        function openColumnEditModal(field){
            const meta = currentColumnMeta[field];
            if(!meta) return;
            editingCtx = { type: meta.type, field: field, submissionId: meta.submissionId, idx: meta.idx };

            let titleText = 'Edit Column';
            if(meta.type === 'supplier') titleText = 'Edit Supplier: ' + (meta.title || '');
            else if(meta.type === 'target') titleText = 'Edit Target';
            else if(meta.type === 'last_purchase') titleText = 'Edit Last Purchase';
            else if(meta.type === 'labels') titleText = 'Edit Field Labels';
            document.getElementById('rfqColEditTitle').textContent = titleText;

            let html = '';
            const editKeys = (meta.type === 'target' || meta.type === 'last_purchase')
                ? (LINKED_SKUS.length ? LINKED_SKUS.slice() : currentRowKeys.filter(k => k !== 'additionalPhotos'))
                : currentRowKeys;

            editKeys.forEach(k => {
                if(meta.type === 'supplier' && k === 'additionalPhotos') return; // files can't be edited as text

                let value = '';
                if(meta.type === 'supplier'){
                    const sub = currentSubmissions[meta.idx];
                    value = (sub && sub.data && sub.data[k] != null) ? sub.data[k] : '';
                    if(Array.isArray(value)) value = value.join(', ');
                } else if(meta.type === 'target'){
                    value = (reportMeta.target && reportMeta.target[k] != null) ? reportMeta.target[k] : '';
                } else if(meta.type === 'last_purchase'){
                    value = (reportMeta.last_purchase && reportMeta.last_purchase[k] != null) ? reportMeta.last_purchase[k] : '';
                } else if(meta.type === 'labels'){
                    value = currentLabelFor(k);
                }

                const rowLabel = (meta.type === 'labels') ? humanizeKey(k) : currentLabelFor(k);
                html += `<div class="mb-2">
                    <label class="form-label mb-1 small fw-semibold">${escapeHtml(rowLabel)}</label>
                    <input type="text" class="form-control form-control-sm rfq-edit-input"
                        data-key="${escapeHtml(k)}" value="${escapeHtml(value)}">
                </div>`;
            });

            // Additional Photos editor (supplier columns only)
            if(meta.type === 'supplier'){
                const sub = currentSubmissions[meta.idx];
                let photos = (sub && sub.data && sub.data.additionalPhotos) ? sub.data.additionalPhotos : [];
                if(!Array.isArray(photos)) photos = [];

                let photoHtml = `<hr>
                    <label class="form-label mb-1 small fw-semibold">Additional Photos</label>
                    <div id="rfqEditPhotos" class="d-flex flex-wrap gap-2 mb-2">`;
                photos.forEach(p => {
                    photoHtml += `<div class="rfq-photo-item position-relative" data-path="${escapeHtml(p)}" style="display:inline-block;">
                            <img src="/storage/${escapeHtml(p)}" onerror="rfqImgError(this)"
                                style="width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;">
                            <button type="button" class="rfq-photo-remove btn btn-danger"
                                style="position:absolute;top:-8px;right:-8px;padding:0 7px;border-radius:50%;line-height:1.5;font-weight:bold;"
                                title="Remove">&times;</button>
                        </div>`;
                });
                photoHtml += `</div>
                    <input type="file" id="rfqNewPhotos" class="form-control form-control-sm" accept="image/*" multiple>
                    <small class="text-muted">Select images to add. Click &times; to remove an existing photo.</small>`;
                html += photoHtml;
            }

            document.getElementById('rfqColEditBody').innerHTML = html || '<p class="text-muted mb-0">Nothing to edit.</p>';

            // Remove-photo buttons
            document.querySelectorAll('#rfqEditPhotos .rfq-photo-remove').forEach(btn => {
                btn.addEventListener('click', function(){
                    const item = this.closest('.rfq-photo-item');
                    if(item) item.remove();
                });
            });

            new bootstrap.Modal(document.getElementById('rfqColEditModal')).show();
        }

        // Open the modal when an edit pencil in any header is clicked
        document.addEventListener('click', function(e){
            const icon = e.target.closest('.rfq-col-edit');
            if(icon){
                e.preventDefault();
                e.stopPropagation();
                openColumnEditModal(icon.getAttribute('data-field'));
            }
        });

        document.getElementById('rfqColEditSaveBtn').addEventListener('click', function(){
            const inputs = document.querySelectorAll('#rfqColEditBody .rfq-edit-input');
            const values = {};
            inputs.forEach(inp => { values[inp.getAttribute('data-key')] = inp.value; });

            const btn = this;
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

            let req;
            if(editingCtx.type === 'supplier'){
                const textReq = fetch(`/rfq-form/submission/${editingCtx.submissionId}/update`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ data: values })
                }).then(r => r.json());

                // Persist photo changes (kept existing + newly uploaded)
                const fd = new FormData();
                fd.append('_token', '{{ csrf_token() }}');
                document.querySelectorAll('#rfqEditPhotos .rfq-photo-item').forEach(el => {
                    fd.append('keep[]', el.dataset.path);
                });
                const fileInput = document.getElementById('rfqNewPhotos');
                if(fileInput && fileInput.files){
                    Array.from(fileInput.files).forEach(f => fd.append('photos[]', f));
                }
                const photoReq = fetch(`/rfq-form/submission/${editingCtx.submissionId}/photos`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: fd
                }).then(r => r.json());

                req = Promise.all([textReq, photoReq]).then(results => {
                    return { success: results.every(r => r && r.success) };
                });
            } else {
                const section = editingCtx.type; // target | last_purchase | labels
                req = fetch(`/rfq-form/${FORM_ID}/report-meta`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ section: section, values: values })
                }).then(r => r.json());
            }

            req
                .then(res => {
                    if(res.success){
                        const m = bootstrap.Modal.getInstance(document.getElementById('rfqColEditModal'));
                        if(m) m.hide();
                        showToast('success', 'Saved successfully!');
                        table.replaceData();
                    } else {
                        alert('Failed: ' + (res.message || 'Unknown error'));
                    }
                })
                .catch(() => alert('Error saving changes'))
                .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
        });


        let popup;

        document.addEventListener('mouseover', function(e){
            if(e.target.classList.contains('thumb-img')){
                const src = e.target.src;

                if(popup) popup.remove();

                popup = document.createElement('div');
                popup.id = 'img-popup';
                popup.style.position = 'fixed';
                popup.style.top = '50%';
                popup.style.left = '50%';
                popup.style.transform = 'translate(-50%, -50%)';
                popup.style.zIndex = 9999;
                popup.style.padding = '10px';
                popup.style.background = '#fff';
                popup.style.border = '2px solid #ccc';
                popup.style.borderRadius = '8px';
                popup.innerHTML = `<img src="${src}" style="max-width:80vw; max-height:80vh;">`;

                document.body.appendChild(popup);
            }
        });

        document.addEventListener('mouseout', function(e){
            if(e.target.classList.contains('thumb-img')){
                if(popup) {
                    popup.remove();
                    popup = null;
                }
            }
        });

        // ===== Import multiple suppliers via template =====
        const LABEL_TO_KEY = {};
        Object.keys(TEMPLATE_LABELS).forEach(key => {
            LABEL_TO_KEY[String(TEMPLATE_LABELS[key]).toLowerCase().trim()] = key;
            LABEL_TO_KEY[String(key).toLowerCase().trim()] = key;
        });
        LABEL_TO_KEY['company name'] = 'companyName';

        document.getElementById('downloadTemplateBtn').addEventListener('click', function(e){
            e.preventDefault();
            const header = TEMPLATE_COLUMNS.map(c => TEMPLATE_LABELS[c] || c);
            const ws = XLSX.utils.aoa_to_sheet([header]);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Suppliers");
            XLSX.writeFile(wb, `{{ $form->name }}_supplier_template.xlsx`);
        });

        document.getElementById('importFileBtn').addEventListener('click', function(e){
            e.preventDefault();
            document.getElementById('importFileInput').click();
        });

        document.getElementById('importFileInput').addEventListener('change', function(e){
            const file = e.target.files[0];
            if(!file) return;

            const reader = new FileReader();
            reader.onload = function(ev){
                try {
                    const wb = XLSX.read(ev.target.result, { type: 'array' });
                    const ws = wb.Sheets[wb.SheetNames[0]];
                    const json = XLSX.utils.sheet_to_json(ws, { defval: '' });

                    const rows = json.map(r => {
                        const nr = {};
                        for(const h in r){
                            const key = LABEL_TO_KEY[String(h).toLowerCase().trim()] || h;
                            nr[key] = r[h];
                        }
                        return nr;
                    }).filter(r => Object.values(r).some(v => String(v).trim() !== ''));

                    if(rows.length === 0){
                        alert('No supplier rows found in the file.');
                        e.target.value = '';
                        return;
                    }

                    fetch(`/rfq-form/${FORM_ID}/import-submissions`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: JSON.stringify({ rows: rows })
                    })
                    .then(r => r.json())
                    .then(res => {
                        if(res.success){
                            showToast('success', `Imported ${res.created} supplier(s) successfully!`);
                            table.replaceData();
                        } else {
                            alert('Import failed: ' + (res.message || 'Unknown error'));
                        }
                    })
                    .catch(() => alert('Error importing file'))
                    .finally(() => { e.target.value = ''; });
                } catch(err){
                    alert('Could not read the file. Please use the provided template.');
                    e.target.value = '';
                }
            };
            reader.readAsArrayBuffer(file);
        });

        document.getElementById("export-btn").addEventListener("click", function () {
            let allData = table.getData("active");

            if (allData.length === 0) {
                alert("No data available to export!");
                return;
            }

            const stripHtml = (html) => {
                const d = document.createElement('div');
                d.innerHTML = (html === null || html === undefined) ? '' : String(html);
                return (d.textContent || d.innerText || '').trim();
            };

            const cols = table.getColumns().map(c => ({
                field: c.getField(),
                title: c.getDefinition().title
            }));

            let exportData = allData.map(row => {
                const obj = {};
                cols.forEach(c => {
                    if(!c.field) return;
                    obj[c.title] = stripHtml(row[c.field]);
                });
                return obj;
            });

            let formName = "{{ $form->name }}";

            let ws = XLSX.utils.json_to_sheet(exportData);
            let wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Report");

            XLSX.writeFile(wb, `${formName}_form_report.xlsx`);
        });


    });
</script>

@endsection