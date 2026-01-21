@extends('layouts.vertical', ['title' => 'RFQ Form'])
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
</style>
@endsection
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'RFQ Form', 'sub_title' => 'RFQ Form'])

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
                    <div class="d-flex flex-wrap gap-2">
                        <button id="add-new-row" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#createRFQFormModal">
                            <i class="fas fa-plus-circle me-1"></i> Create RFQ Form
                        </button>
                    </div>
                </div>
                <div id="rfq-form-table"></div>
            </div>
        </div>
    </div>
</div>

{{-- add rfq form modal --}}
<div class="modal fade" id="createRFQFormModal" tabindex="-1" aria-labelledby="createRFQFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="createRFQFormModalLabel">
                    <i class="fas fa-file-invoice me-2"></i> Create RFQ Form
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="rfqFormCreate" method="POST" action="{{ route('rfq-form.store') }}" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div class="modal-body">

                    <!-- Section 1: Basic Info -->
                    <div class="border p-3 rounded mb-3">
                        <h6 class="fw-bold mb-3">Basic Information</h6>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="rfq_form_name" class="form-label">RFQ Form Name <span class="text-danger">*</span></label>
                                <input type="text" name="rfq_form_name" id="rfq_form_name" class="form-control" placeholder="Stand" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="title" class="form-label">Form Heading / Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control" placeholder="Enter form heading" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="main_image" class="form-label">Form Image (optional)</label>
                                <input type="file" name="main_image" id="main_image" class="form-control" accept="image/*">
                                <img id="mainImagePreview" src="#" alt="Preview" class="img-fluid mt-2" style="display:none; max-height:150px;">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <label for="subtitle" class="form-label">Form Subtitle / Description</label>
                                <textarea name="subtitle" id="subtitle" class="form-control" rows="3" placeholder="Enter form description">5 Core, USA is currently exploring potential purchase of the product listed. This information will help us evaluate your product for procurement consideration.😊( 5 Core，美国目前正在探索对所列产品的潜在采购。 请提供完整准确的产品规格、价格、最小起订量 (MOQ) 和交易条款。这些信息将有助于我们评估您的产品以供采购参考。😊)</textarea>
                            </div>
                            <div class="col-md-2 d-flex flex-column align-item-center justify-content-center">
                                <div class="form-check mb-2">
                                    <input type="hidden" name="dimension_inner" value="false">
                                    <input class="form-check-input" type="checkbox" value="true" id="checkbox1" name="dimension_inner">
                                    <label class="form-check-label" for="checkbox1">Dimension Inner Box</label>
                                </div>

                                <div class="form-check mb-2">
                                    <input type="hidden" name="product_dimension" value="false">
                                    <input class="form-check-input" type="checkbox" value="true" id="checkbox2" name="product_dimension">
                                    <label class="form-check-label" for="checkbox2">Product Dimension</label>
                                </div>

                                <div class="form-check mb-2">
                                    <input type="hidden" name="package_dimension" value="false">
                                    <input class="form-check-input" type="checkbox" value="true" id="checkbox3" name="package_dimension">
                                    <label class="form-check-label" for="checkbox3">Package Dimension</label>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Dynamic Fields -->
                    <div class="border p-3 rounded">
                        <h6 class="fw-bold mb-3">Add Fields</h6>

                        <div id="dynamicFieldsWrapper">
                            <div class="row g-3 mb-2 field-item">
                                <div class="col-md-3">
                                    <input type="text" name="fields[0][label]" class="form-control field-label" placeholder="Field Label" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="fields[0][name]" class="form-control field-name" placeholder="Field Name (auto)" readonly>
                                </div>
                                <div class="col-md-2">
                                    <select name="fields[0][type]" class="form-select field-type">
                                        <option value="text">Text</option>
                                        <option value="number">Number</option>
                                        <option value="select">Select</option>
                                    </select>
                                </div>
                                <div class="col-md-3 select-options-wrapper" style="display:none;">
                                    <input type="text" name="fields[0][options]" class="form-control" placeholder="Options (comma separated)">
                                </div>
                                <div class="col-md-1">
                                    <input type="checkbox" name="fields[0][required]" class="form-check-input mt-2" value="1"> Required
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-danger btn-sm remove-field">X</button>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-success btn-sm mt-2" id="addFieldBtn">
                            <i class="fas fa-plus"></i> Add Field
                        </button>
                    </div>


                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Create Form</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- edit rfq form modal --}}
<div class="modal fade" id="editRFQFormModal" tabindex="-1" aria-labelledby="editRFQFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title fw-bold" id="editRFQFormModalLabel">
                    <i class="fas fa-edit me-2"></i> Edit RFQ Form
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="rfqFormEdit" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="edit_form_id" name="form_id">

                    <!-- Similar fields as Create Modal -->
                    <div class="border p-3 rounded mb-3">
                        <h6 class="fw-bold mb-3">Basic Information</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_rfq_form_name" class="form-label">RFQ Form Name</label>
                                <input type="text" name="rfq_form_name" id="edit_rfq_form_name" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_title" class="form-label">Form Heading / Title</label>
                                <input type="text" name="title" id="edit_title" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_main_image" class="form-label">Form Image</label>
                                <input type="file" name="main_image" id="edit_main_image" class="form-control" accept="image/*">
                                <img id="editMainImagePreview" src="#" class="img-fluid mt-2" style="display:none; max-height:50px;">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="edit_subtitle" class="form-label">Form Subtitle</label>
                                <textarea name="subtitle" id="edit_subtitle" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="col-md-2 d-flex flex-column align-item-center justify-content-center">
                                <div class="form-check mb-2">
                                    <input type="hidden" name="dimension_inner" value="false">
                                    <input class="form-check-input" type="checkbox" value="true" id="editDimensionInner" name="dimension_inner">
                                    <label class="form-check-label" for="editDimensionInner">Dimension Inner Box</label>
                                </div>

                                <div class="form-check mb-2">
                                    <input type="hidden" name="product_dimension" value="false">
                                    <input class="form-check-input" type="checkbox" value="true" id="editProductDimension" name="product_dimension">
                                    <label class="form-check-label" for="editProductDimension">Product Dimension</label>
                                </div>

                                <div class="form-check mb-2">
                                    <input type="hidden" name="package_dimension" value="false">
                                    <input class="form-check-input" type="checkbox" value="true" id="editPackageDimension" name="package_dimension">
                                    <label class="form-check-label" for="editPackageDimension">Package Dimension</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Fields -->
                    <div class="border p-3 rounded">
                        <h6 class="fw-bold mb-3">Edit Fields</h6>
                        <div id="editDynamicFieldsWrapper"></div>
                        <button type="button" class="btn btn-success btn-sm mt-2" id="addEditFieldBtn">
                            <i class="fas fa-plus"></i> Add Field
                        </button>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning">Update Form</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Send to Supplier Modal --}}
<div class="modal fade" id="sendToSupplierModal" tabindex="-1" aria-labelledby="sendToSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="sendToSupplierModalLabel">
                    <i class="fas fa-envelope me-2"></i> Send RFQ Form to Supplier
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="sendToSupplierForm" method="POST" autocomplete="off">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="send_form_id" name="form_id">
                    <input type="hidden" id="send_form_slug" name="form_slug">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Form Name:</label>
                        <p id="send_form_name" class="text-muted"></p>
                    </div>

                    <div class="mb-3">
                        <label for="supplier_search" class="form-label">Search Supplier <span class="text-danger">*</span></label>
                        <input type="text" id="supplier_search" class="form-control" placeholder="Type to search suppliers by name, company, or email...">
                        <small class="text-muted">Start typing to search and select suppliers</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Selected Suppliers <span class="text-danger">*</span></label>
                        <div id="selectedSuppliersList" class="border rounded p-3" style="min-height: 100px; max-height: 200px; overflow-y: auto;">
                            <p class="text-muted text-center mb-0">No suppliers selected</p>
                        </div>
                        <input type="hidden" id="selected_supplier_ids" name="supplier_ids">
                    </div>

                    <div class="mb-3">
                        <label for="email_subject" class="form-label">Email Subject <span class="text-danger">*</span></label>
                        <input type="text" id="email_subject" name="email_subject" class="form-control" placeholder="RFQ Form - [Form Name]" required>
                    </div>

                    <div class="mb-3">
                        <label for="email_message" class="form-label">Additional Message (Optional)</label>
                        <textarea id="email_message" name="email_message" class="form-control" rows="4" placeholder="Add any additional message for suppliers..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="sendEmailBtn">
                        <i class="fas fa-paper-plane me-2"></i> Send Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    // Toast notification helper function
    function showToast(type, message) {
        // Remove any existing toast
        document.querySelectorAll('.custom-toast').forEach(t => t.remove());

        const toast = document.createElement('div');
        toast.className = `custom-toast toast align-items-center text-bg-${type} border-0 show position-fixed top-0 end-0 m-4`;
        toast.style.zIndex = 2000;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        document.body.appendChild(toast);

        const bsToast = new bootstrap.Toast(toast, { autohide: true, delay: 3000 });
        bsToast.show();

        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {

        const table = new Tabulator("#rfq-form-table", {
            ajaxURL: "/rfq-form/data",
            ajaxConfig: "GET",
            layout: "fitColumns",
            pagination: true,
            paginationSize: 50,
            paginationMode: "local",
            movableColumns: false,
            resizableColumns: true,
            height: "500px",
            columns: [
                {
                    title: "Form Name",
                    field: "name",
                    formatter: function(cell){
                        let value = cell.getValue();
                        return value;
                    }
                },
                {
                    title: "Report Link",
                    field: "slug",
                    formatter: function(cell, formatterParams, onRendered){
                        const slug = cell.getValue();
                        if(!slug) return "";

                        const fullUrl = window.location.origin + `/rfq-form/reports/${slug}`;

                        return `
                            <div class="d-flex justify-content-center align-item-center">
                                <a href="${fullUrl}" target="_blank" class="btn btn-sm btn-info me-2">Report <i class="fa-solid fa-link"></i></a>
                            </div>
                        `;
                    },
                },
                {
                    title: "Form Link",
                    field: "slug",
                    formatter: function(cell, formatterParams, onRendered){
                        const slug = cell.getValue();
                        if(!slug) return "";

                        const fullUrl = window.location.origin + `/api/rfq-form/${slug}`;

                        return `
                            <div class="d-flex justify-content-center align-item-center">
                                <a href="${fullUrl}" target="_blank" class="btn btn-sm btn-outline-info me-2"><i class="fa-solid fa-link"></i></a>
                                <button class="btn btn-sm btn-outline-primary copy-btn" data-slug="${fullUrl}"><i class="fa-regular fa-copy"></i></button>
                            </div>
                        `;
                    },
                    cellClick: function(e, cell){
                        if(e.target.classList.contains('copy-btn')){
                            const slug = e.target.dataset.slug;
                            navigator.clipboard.writeText(slug).then(() => {
                                showToast('success', 'Link copied successfully!');
                            }).catch(() => {
                                alert('Failed to copy slug');
                            });
                        }
                    }
                },
                {
                    title: "Created Date",
                    field: "created_at",
                    formatter: function(cell){
                        let value = cell.getValue();
                        return value ? moment(value).format('YYYY-MM-DD') : '';
                    }
                },
                {
                    title: "Updated Date",
                    field: "updated_at",
                    formatter: function(cell){
                        let value = cell.getValue();
                        return value ? moment(value).format('YYYY-MM-DD') : '';
                    }
                },
                {
                    title: "Action",
                    field: "name",
                    hozAlign: "center",
                    formatter: function(cell, formatterParams, onRendered) {
                        const rowData = cell.getData();
                        const editUrl = `/rfq-form/edit/${rowData.id}`;
                        const deleteUrl = `/rfq-form/delete/${rowData.id}`;

                        return `
                            <div class="d-flex justify-content-center align-item-center">
                                <button class="btn btn-sm btn-success me-2 edit-btn" data-id="${rowData.id}" title="Edit" style="cursor:pointer;">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button class="btn btn-sm btn-info me-2 copy-btn" data-id="${rowData.id}" title="Copy" style="cursor:pointer;">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                                <button class="btn btn-sm btn-primary me-2 send-email-btn" data-id="${rowData.id}" data-slug="${rowData.slug}" data-name="${rowData.name}" title="Send to Supplier" style="cursor:pointer;">
                                    <i class="fa-solid fa-envelope"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="${rowData.id}" title="Delete" style="cursor:pointer;">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        `;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                        
                        if(e.target.closest('.edit-btn')) {
                            e.preventDefault();
                            const btn = e.target.closest('.edit-btn');
                            const id = btn.dataset.id;

                            fetch(`/rfq-form/edit/${id}`)
                            .then(res => res.json())
                            .then(res => {
                                if(res.success) {
                                    const data = res.data;

                                    document.getElementById('edit_form_id').value = data.id;
                                    document.getElementById('edit_rfq_form_name').value = data.name;
                                    document.getElementById('edit_title').value = data.title;
                                    document.getElementById('edit_subtitle').value = data.subtitle;

                                    document.getElementById("editDimensionInner").checked = data.dimension_inner === true || data.dimension_inner === 1 || data.dimension_inner === "true";
                                    document.getElementById("editProductDimension").checked = data.product_dimension === true || data.product_dimension === 1 || data.product_dimension === "true";
                                    document.getElementById("editPackageDimension").checked = data.package_dimension === true || data.package_dimension === 1 || data.package_dimension === "true";

                                    if(data.main_image){
                                        document.getElementById('editMainImagePreview').src = "/storage/" + data.main_image;
                                        document.getElementById('editMainImagePreview').style.display = 'block';
                                    } else {
                                        document.getElementById('editMainImagePreview').style.display = 'none';
                                    }

                                    // Dynamic fields
                                    const wrapper = document.getElementById('editDynamicFieldsWrapper');
                                    wrapper.innerHTML = '';
                                    if(data.fields && data.fields.length > 0) {
                                        data.fields.forEach((field, index) => {
                                            wrapper.insertAdjacentHTML('beforeend', `
                                                <div class="row g-3 mb-2 field-item">
                                                    <div class="col-md-3">
                                                        <input type="text" name="fields[${index}][label]" class="form-control field-label" value="${field.label || ''}" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <input type="text" name="fields[${index}][name]" class="form-control field-name" value="${field.name || ''}" readonly>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <select name="fields[${index}][type]" class="form-select field-type">
                                                            <option value="text" ${field.type === 'text' ? 'selected':''}>Text</option>
                                                            <option value="number" ${field.type === 'number' ? 'selected':''}>Number</option>
                                                            <option value="select" ${field.type === 'select' ? 'selected':''}>Select</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3 select-options-wrapper" style="${field.type === 'select' ? 'display:block':'display:none'};">
                                                        <input type="text" name="fields[${index}][options]" class="form-control" value="${field.options || ''}">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <input type="checkbox" name="fields[${index}][required]" class="form-check-input mt-2" value="1" ${field.required ? 'checked':''}>
                                                    </div>
                                                    <div class="col-md-1">
                                                        <button type="button" class="btn btn-danger btn-sm remove-field">X</button>
                                                    </div>
                                                </div>
                                            `);
                                        });
                                    }

                                    let myModal = new bootstrap.Modal(document.getElementById('editRFQFormModal'));
                                    myModal.show();
                                } else {
                                    alert('Failed to load form data');
                                }
                            })
                            .catch(() => alert('Error loading form data'));
                            return;
                        }
                        
                        if(e.target.closest('.copy-btn')) {
                            e.preventDefault();
                            const btn = e.target.closest('.copy-btn');
                            const id = btn.dataset.id;

                            fetch(`/rfq-form/edit/${id}`)
                            .then(res => res.json())
                            .then(res => {
                                if(res.success) {
                                    const data = res.data;

                                    // Set flag to prevent modal reset
                                    isCopyAction = true;

                                    // Populate create modal with form data
                                    document.getElementById('rfq_form_name').value = data.name + ' (Copy)';
                                    document.getElementById('title').value = data.title;
                                    document.getElementById('subtitle').value = data.subtitle || '';

                                    document.getElementById("checkbox1").checked = data.dimension_inner === true || data.dimension_inner === 1 || data.dimension_inner === "true";
                                    document.getElementById("checkbox2").checked = data.product_dimension === true || data.product_dimension === 1 || data.product_dimension === "true";
                                    document.getElementById("checkbox3").checked = data.package_dimension === true || data.package_dimension === 1 || data.package_dimension === "true";

                                    // Clear image preview
                                    document.getElementById('mainImagePreview').style.display = 'none';
                                    document.getElementById('main_image').value = '';

                                    // Dynamic fields
                                    const wrapper = document.getElementById('dynamicFieldsWrapper');
                                    wrapper.innerHTML = '';
                                    if(data.fields && data.fields.length > 0) {
                                        data.fields.forEach((field, index) => {
                                            wrapper.insertAdjacentHTML('beforeend', `
                                                <div class="row g-3 mb-2 field-item">
                                                    <div class="col-md-3">
                                                        <input type="text" name="fields[${index}][label]" class="form-control field-label" value="${field.label || ''}" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <input type="text" name="fields[${index}][name]" class="form-control field-name" value="${field.name || ''}" readonly>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <select name="fields[${index}][type]" class="form-select field-type">
                                                            <option value="text" ${field.type === 'text' ? 'selected':''}>Text</option>
                                                            <option value="number" ${field.type === 'number' ? 'selected':''}>Number</option>
                                                            <option value="select" ${field.type === 'select' ? 'selected':''}>Select</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3 select-options-wrapper" style="${field.type === 'select' ? 'display:block':'display:none'};">
                                                        <input type="text" name="fields[${index}][options]" class="form-control" value="${field.options || ''}">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <input type="checkbox" name="fields[${index}][required]" class="form-check-input mt-2" value="1" ${field.required ? 'checked':''}>
                                                    </div>
                                                    <div class="col-md-1">
                                                        <button type="button" class="btn btn-danger btn-sm remove-field">X</button>
                                                    </div>
                                                </div>
                                            `);
                                        });
                                    } else {
                                        // Add one empty field if no fields exist
                                        wrapper.insertAdjacentHTML('beforeend', `
                                            <div class="row g-3 mb-2 field-item">
                                                <div class="col-md-3">
                                                    <input type="text" name="fields[0][label]" class="form-control field-label" placeholder="Field Label" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="text" name="fields[0][name]" class="form-control field-name" placeholder="Field Name (auto)" readonly>
                                                </div>
                                                <div class="col-md-2">
                                                    <select name="fields[0][type]" class="form-select field-type">
                                                        <option value="text">Text</option>
                                                        <option value="number">Number</option>
                                                        <option value="select">Select</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 select-options-wrapper" style="display:none;">
                                                    <input type="text" name="fields[0][options]" class="form-control" placeholder="Options (comma separated)">
                                                </div>
                                                <div class="col-md-1">
                                                    <input type="checkbox" name="fields[0][required]" class="form-check-input mt-2" value="1"> Required
                                                </div>
                                                <div class="col-md-1">
                                                    <button type="button" class="btn btn-danger btn-sm remove-field">X</button>
                                                </div>
                                            </div>
                                        `);
                                    }

                                    // Update field count
                                    fieldCount = data.fields && data.fields.length > 0 ? data.fields.length : 1;

                                    // Open create modal
                                    let myModal = new bootstrap.Modal(document.getElementById('createRFQFormModal'));
                                    myModal.show();
                                } else {
                                    alert('Failed to load form data');
                                }
                            })
                            .catch(() => alert('Error loading form data'));
                            return;
                        }
                        
                        if(e.target.closest('.send-email-btn')) {
                            e.preventDefault();
                            const btn = e.target.closest('.send-email-btn');
                            const id = btn.dataset.id;
                            const slug = btn.dataset.slug;
                            const name = btn.dataset.name;

                            document.getElementById('send_form_id').value = id;
                            document.getElementById('send_form_slug').value = slug;
                            document.getElementById('send_form_name').textContent = name;
                            document.getElementById('email_subject').value = `RFQ Form - ${name}`;
                            
                            // Reset form
                            document.getElementById('selectedSuppliersList').innerHTML = '<p class="text-muted text-center mb-0">No suppliers selected</p>';
                            document.getElementById('selected_supplier_ids').value = '';
                            document.getElementById('supplier_search').value = '';
                            document.getElementById('email_message').value = '';
                            selectedSuppliers = [];

                            let myModal = new bootstrap.Modal(document.getElementById('sendToSupplierModal'));
                            myModal.show();
                            return;
                        }
                        
                        if(e.target.closest('.delete-btn')) {
                            e.preventDefault();
                            const btn = e.target.closest('.delete-btn');
                            const id = btn.dataset.id;

                            if(confirm('Are you sure you want to delete this form?')) {
                                fetch(`/rfq-form/delete/${id}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Content-Type': 'application/json'
                                    }
                                })
                                .then(r => r.json())
                                .then(r => {
                                    if(r.success) {
                                        cell.getRow().delete();
                                        showToast('success', 'Form deleted successfully!');
                                    } else {
                                        alert('Failed to delete form: ' + r.message);
                                    }
                                })
                                .catch(() => alert('Error deleting form'));
                            }
                        }
                    }
                }

            ],
            ajaxResponse: function(url, params, response){
                return response.data;
            },
        });


        let fieldCount = 1;

        function slugify(text){
            return text.toLowerCase().replace(/\s+/g,'_').replace(/[^a-z0-9_]/g,'');
        }

        function createFieldRow(index){
            return `
            <div class="row g-3 mb-2 field-item">
                <div class="col-md-3">
                    <input type="text" name="fields[${index}][label]" class="form-control field-label" placeholder="Field Label" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="fields[${index}][name]" class="form-control field-name" placeholder="Field Name (auto)" readonly>
                </div>
                <div class="col-md-2">
                    <select name="fields[${index}][type]" class="form-select field-type">
                        <option value="text">Text</option>
                        <option value="number">Number</option>
                        <option value="select">Select</option>
                    </select>
                </div>
                <div class="col-md-3 select-options-wrapper" style="display:none;">
                    <input type="text" name="fields[${index}][options]" class="form-control" placeholder="Options (comma separated)">
                </div>
                <div class="col-md-1">
                    <input type="checkbox" name="fields[${index}][required]" class="form-check-input mt-2" value="1"> Required
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger btn-sm remove-field">X</button>
                </div>
            </div>
            `;
        }

        // Add new field
        document.getElementById('addFieldBtn').addEventListener('click', function(){
            let wrapper = document.getElementById('dynamicFieldsWrapper');
            wrapper.insertAdjacentHTML('beforeend', createFieldRow(fieldCount));
            fieldCount++;
        });

        document.getElementById('addEditFieldBtn').addEventListener('click', function(){
            let wrapper = document.getElementById('editDynamicFieldsWrapper');
            wrapper.insertAdjacentHTML('beforeend', createFieldRow(fieldCount));
            fieldCount++;
        });

        // Remove field
        document.addEventListener('click', function(e){
            if(e.target && e.target.classList.contains('remove-field')){
                e.target.closest('.field-item').remove();
            }
        });

        // Show/hide options input if type is select
        document.addEventListener('change', function(e){
            if(e.target && e.target.classList.contains('field-type')){
                let optionsWrapper = e.target.closest('.field-item').querySelector('.select-options-wrapper');
                if(e.target.value === 'select'){
                    optionsWrapper.style.display = 'block';
                } else {
                    optionsWrapper.style.display = 'none';
                }
            }
        });

        // Auto-fill field name from label
        document.addEventListener('input', function(e){
            if(e.target && e.target.classList.contains('field-label')){
                let nameInput = e.target.closest('.field-item').querySelector('.field-name');
                nameInput.value = slugify(e.target.value);
            }
        });

        // Reset create modal when opened fresh (not from copy)
        let isCopyAction = false;
        const createModal = document.getElementById('createRFQFormModal');
        createModal.addEventListener('show.bs.modal', function() {
            if(!isCopyAction) {
                // Reset form
                document.getElementById('rfqFormCreate').reset();
                document.getElementById('rfq_form_name').value = '';
                document.getElementById('title').value = '';
                document.getElementById('subtitle').value = '';
                document.getElementById('main_image').value = '';
                document.getElementById('mainImagePreview').style.display = 'none';
                
                // Reset checkboxes
                document.getElementById("checkbox1").checked = false;
                document.getElementById("checkbox2").checked = false;
                document.getElementById("checkbox3").checked = false;
                
                // Reset fields wrapper to initial state
                const wrapper = document.getElementById('dynamicFieldsWrapper');
                wrapper.innerHTML = `
                    <div class="row g-3 mb-2 field-item">
                        <div class="col-md-3">
                            <input type="text" name="fields[0][label]" class="form-control field-label" placeholder="Field Label" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="fields[0][name]" class="form-control field-name" placeholder="Field Name (auto)" readonly>
                        </div>
                        <div class="col-md-2">
                            <select name="fields[0][type]" class="form-select field-type">
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="select">Select</option>
                            </select>
                        </div>
                        <div class="col-md-3 select-options-wrapper" style="display:none;">
                            <input type="text" name="fields[0][options]" class="form-control" placeholder="Options (comma separated)">
                        </div>
                        <div class="col-md-2">
                            <input type="checkbox" name="fields[0][required]" class="form-check-input mt-2" value="1"> Required
                        </div>
                    </div>
                `;
                fieldCount = 1;
            }
            isCopyAction = false; // Reset flag
        });

        // Supplier selection functionality
        let selectedSuppliers = [];
        let supplierSearchTimeout;

        function setupSupplierSearch() {
            const supplierSearchInput = document.getElementById('supplier_search');
            if(!supplierSearchInput) return;
            
            // Remove existing listener if any
            const newInput = supplierSearchInput.cloneNode(true);
            supplierSearchInput.parentNode.replaceChild(newInput, supplierSearchInput);
            
            newInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.trim();
                
                if(searchTerm.length < 2) {
                    hideSupplierDropdown();
                    return;
                }

                clearTimeout(supplierSearchTimeout);
                supplierSearchTimeout = setTimeout(() => {
                    const url = `{{ url('/rfq-form/suppliers/search') }}?q=${encodeURIComponent(searchTerm)}`;
                    fetch(url, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json'
                        }
                    })
                        .then(res => {
                            if(!res.ok) {
                                throw new Error(`HTTP error! status: ${res.status}`);
                            }
                            return res.json();
                        })
                        .then(data => {
                            if(data.success && data.suppliers && data.suppliers.length > 0) {
                                showSupplierDropdown(data.suppliers);
                            } else {
                                hideSupplierDropdown();
                            }
                        })
                        .catch(error => {
                            console.error('Error searching suppliers:', error);
                            hideSupplierDropdown();
                        });
                }, 300);
            });
        }

        // Setup supplier search when modal is shown
        const sendToSupplierModal = document.getElementById('sendToSupplierModal');
        if(sendToSupplierModal) {
            sendToSupplierModal.addEventListener('shown.bs.modal', function() {
                setupSupplierSearch();
            });
        }

        function showSupplierDropdown(suppliers) {
            hideSupplierDropdown();
            
            const dropdown = document.createElement('div');
            dropdown.id = 'supplierDropdown';
            dropdown.className = 'list-group position-absolute w-100';
            dropdown.style.cssText = 'z-index: 1050; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;';
            
            suppliers.forEach(supplier => {
                if(selectedSuppliers.find(s => s.id === supplier.id)) return;
                
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>${supplier.name || 'N/A'}</strong>
                            ${supplier.company ? `<br><small class="text-muted">${supplier.company}</small>` : ''}
                        </div>
                        <div class="text-end">
                            <small class="text-muted">${supplier.email || 'No email'}</small>
                        </div>
                    </div>
                `;
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    if(supplier.email) {
                        addSupplier(supplier);
                        hideSupplierDropdown();
                        document.getElementById('supplier_search').value = '';
                    } else {
                        alert('This supplier does not have an email address.');
                    }
                });
                dropdown.appendChild(item);
            });
            
            const searchInput = document.getElementById('supplier_search');
            searchInput.parentElement.style.position = 'relative';
            searchInput.parentElement.appendChild(dropdown);
        }

        function hideSupplierDropdown() {
            const dropdown = document.getElementById('supplierDropdown');
            if(dropdown) dropdown.remove();
        }

        function addSupplier(supplier) {
            if(selectedSuppliers.find(s => s.id === supplier.id)) return;
            
            selectedSuppliers.push(supplier);
            updateSelectedSuppliersList();
            updateSupplierIdsInput();
        }

        function removeSupplier(supplierId) {
            selectedSuppliers = selectedSuppliers.filter(s => s.id !== supplierId);
            updateSelectedSuppliersList();
            updateSupplierIdsInput();
        }

        function updateSelectedSuppliersList() {
            const listDiv = document.getElementById('selectedSuppliersList');
            
            if(selectedSuppliers.length === 0) {
                listDiv.innerHTML = '<p class="text-muted text-center mb-0">No suppliers selected</p>';
                return;
            }
            
            listDiv.innerHTML = selectedSuppliers.map(supplier => `
                <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                    <div>
                        <strong>${supplier.name || 'N/A'}</strong>
                        ${supplier.company ? `<br><small class="text-muted">${supplier.company}</small>` : ''}
                        <br><small class="text-muted">${supplier.email}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeSupplier(${supplier.id})">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
            `).join('');
        }

        function updateSupplierIdsInput() {
            document.getElementById('selected_supplier_ids').value = selectedSuppliers.map(s => s.id).join(',');
        }

        // Make removeSupplier available globally
        window.removeSupplier = removeSupplier;

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if(!e.target.closest('#supplier_search') && !e.target.closest('#supplierDropdown')) {
                hideSupplierDropdown();
            }
        });

        // Send to Supplier Form Submit
        document.getElementById('sendToSupplierForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if(selectedSuppliers.length === 0) {
                alert('Please select at least one supplier.');
                return;
            }

            const formData = new FormData(this);
            const sendBtn = document.getElementById('sendEmailBtn');
            const originalText = sendBtn.innerHTML;
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Sending...';

            fetch('{{ url('/rfq-form/send-email') }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(async res => {
                const contentType = res.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await res.text();
                    throw new Error(`Server returned non-JSON response (${res.status}): ${text.substring(0, 200)}`);
                }
                
                if(!res.ok) {
                    const errorData = await res.json().catch(() => ({}));
                    throw new Error(errorData.message || `HTTP error! status: ${res.status}`);
                }
                return res.json();
            })
            .then(res => {
                if(res.success) {
                    let message = 'Email sent successfully to ' + res.sent_count + ' supplier(s)!';
                    if(res.failed_count > 0) {
                        message += ' (' + res.failed_count + ' email(s) failed to send)';
                    }
                    showToast('success', message);
                    bootstrap.Modal.getInstance(document.getElementById('sendToSupplierModal')).hide();
                } else {
                    let errorMsg = res.message || 'Unknown error';
                    if(res.errors && res.errors.length > 0) {
                        errorMsg += '\n\nErrors:\n' + res.errors.slice(0, 3).join('\n');
                    }
                    alert('Failed to send email: ' + errorMsg);
                }
            })
            .catch(error => {
                console.error('Error sending email:', error);
                let errorMsg = 'Error sending email. ';
                if(error.message) {
                    errorMsg += error.message;
                } else {
                    errorMsg += 'Please check your internet connection and try again.';
                }
                alert(errorMsg);
            })
            .finally(() => {
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalText;
            });
        });

        // Update Submit
        document.getElementById('rfqFormEdit').addEventListener('submit', function(e){
            e.preventDefault();

            const id = document.getElementById('edit_form_id').value;

            // Collect all dynamic fields properly
            let fields = [];
            document.querySelectorAll('#editDynamicFieldsWrapper .field-item').forEach((item, index) => {
                const label = item.querySelector('.field-label')?.value || '';
                const name = item.querySelector('.field-name')?.value || '';
                const type = item.querySelector('.field-type')?.value || 'text';
                const options = item.querySelector('[name*="[options]"]')?.value || '';
                const required = item.querySelector('[name*="[required]"]')?.checked ? 1 : 0;
                fields.push({ label, name, type, options, required, order: index + 1 });
            });

            const formData = new FormData(this);
            formData.append('fields_json', JSON.stringify(fields));

            fetch(`/rfq-form/update/${id}`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(res => {
                if(res.success){
                    showToast('success', 'Form updated successfully!');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert("Update failed: " + res.message);
                }
            })
            .catch(() => alert("Error updating form"));
        });

    });
</script>

@endsection