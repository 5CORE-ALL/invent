@extends('layouts.vertical', ['title' => 'RFQ Form'])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
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

    .tabulator-row.rfq-context-row {
        background-color: #fff8e1 !important;
        box-shadow: inset 0 0 0 2px #f59e0b;
    }

    .tabulator-cell .rfq-row-dropdown .dropdown-menu {
        z-index: 2000;
    }

    #addSupplierModal .modal-content,
    #addSupplierModal .modal-body {
        background-color: #fff !important;
    }
    .supplier-approval-dot { width: 14px; height: 14px; border-radius: 50%; border: 2px solid rgba(0,0,0,0.12); }
    .supplier-approval-dot--red { background-color: #dc3545; }
    .supplier-approval-dot--green { background-color: #198754; }
    .supplier-approval-dot--yellow { background-color: #ffc107; }
    .approval-form-dots label:has(input[type="radio"]:checked) { font-weight: 600; }
    .approval-form-dots input[type="radio"]:checked + span {
        box-shadow: 0 0 0 2px #495057;
        border-radius: 50%;
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
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'rfq_form'])
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-warning dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-file-import me-1"></i> Import
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" id="downloadFormTemplateBtn"><i class="fas fa-download me-2"></i>Download Template</a></li>
                                <li><a class="dropdown-item" href="#" id="importFormBtn"><i class="fas fa-file-import me-2"></i>Import Form</a></li>
                            </ul>
                        </div>
                        <input type="file" id="importFormInput" accept=".xlsx,.xls,.csv" style="display:none;">
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-success dropdown-toggle" type="button" id="rfq-actions-dropdown-btn" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-file-invoice me-1"></i> RFQ
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#" id="rfq-action-create">
                                        <i class="fas fa-plus-circle me-2"></i> Create RFQ Form
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" id="rfq-action-target">
                                        <i class="fas fa-bullseye me-2"></i> Target
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" id="rfq-action-last-purchase">
                                        <i class="fa-solid fa-clock-rotate-left me-2"></i> Last Purchase
                                    </a>
                                </li>
                            </ul>
                        </div>
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

                        <div class="row g-3 mb-1 small text-muted fw-semibold">
                            <div class="col-md-3">Label</div>
                            <div class="col-md-2">Name</div>
                            <div class="col-md-2">Type</div>
                            <div class="col-md-2">Part (Basics / Details)</div>
                            <div class="col-md-2">Options</div>
                            <div class="col-md-1">Req.</div>
                        </div>
                        <div id="dynamicFieldsWrapper">
                            <div class="row g-3 mb-2 field-item">
                                <div class="col-md-3">
                                    <input type="text" name="fields[0][label]" class="form-control field-label" placeholder="Field Label" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="text" name="fields[0][name]" class="form-control field-name" placeholder="Field Name (auto)" readonly>
                                </div>
                                <div class="col-md-2">
                                    <select name="fields[0][type]" class="form-select field-type">
                                        <option value="text">Text</option>
                                        <option value="number">Number</option>
                                        <option value="select">Select</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="fields[0][part]" class="form-select field-part">
                                        <option value="basics">Basics</option>
                                        <option value="details" selected>Details</option>
                                    </select>
                                </div>
                                <div class="col-md-2 select-options-wrapper" style="display:none;">
                                    <input type="text" name="fields[0][options]" class="form-control" placeholder="Options (comma separated)">
                                </div>
                                <div class="col-md-1">
                                    <input type="checkbox" name="fields[0][required]" class="form-check-input mt-2" value="1"> Required
                                </div>
                                <div class="col-md-12">
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
                        <div class="row g-3 mb-1 small text-muted fw-semibold">
                            <div class="col-md-3">Label</div>
                            <div class="col-md-2">Name</div>
                            <div class="col-md-2">Type</div>
                            <div class="col-md-2">Part (Basics / Details)</div>
                            <div class="col-md-2">Options</div>
                            <div class="col-md-1">Req.</div>
                        </div>
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

{{-- Supplier Links Modal (Basics / Details / Combined) --}}
<div class="modal fade" id="supplierLinksModal" tabindex="-1" aria-labelledby="supplierLinksModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="supplierLinksModalLabel">
                    <i class="fa-solid fa-share-nodes me-2"></i> Supplier Links
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2">
                    <span id="links_form_name" class="fw-bold"></span><br>
                    These links share one supplier token, so <strong>Basics</strong> and <strong>Details</strong> submissions
                    are automatically combined into the same supplier row in the report. Generate a new token for each supplier.
                </p>

                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="small text-muted">Token:</span>
                    <code id="links_token" class="px-2 py-1 bg-light border rounded"></code>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="regenerateTokenBtn">
                        <i class="fa-solid fa-rotate"></i> New Token
                    </button>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold text-primary">1. Basics link (Supplier details + Pricing/MOQ)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control link-field" id="link_basics" readonly>
                        <button class="btn btn-outline-primary copy-link-btn" data-target="link_basics" type="button"><i class="fa-regular fa-copy"></i></button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold text-success">2. Details link (Specs + Dimensions + Photos + Notes)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control link-field" id="link_details" readonly>
                        <button class="btn btn-outline-success copy-link-btn" data-target="link_details" type="button"><i class="fa-regular fa-copy"></i></button>
                    </div>
                </div>

                <div class="mb-1">
                    <label class="form-label fw-semibold text-dark">3. Combined link (Both parts together)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control link-field" id="link_combined" readonly>
                        <button class="btn btn-outline-dark copy-link-btn" data-target="link_combined" type="button"><i class="fa-regular fa-copy"></i></button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
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
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="supplier_search" class="form-label mb-0">Search Supplier <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="rfqAddSupplierBtn" title="Add a new supplier">
                                <i class="mdi mdi-plus me-1"></i> Add Supplier
                            </button>
                        </div>
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

{{-- Linked SKU Modal --}}
<div class="modal fade" id="linkedSkusModal" tabindex="-1" aria-labelledby="linkedSkusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="linkedSkusModalLabel">
                    <i class="fa-solid fa-tags me-2"></i> Linked SKUs
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="linked_skus_form_id">
                <div class="mb-3">
                    <label class="form-label fw-bold">Form Name:</label>
                    <p id="linked_skus_form_name" class="text-muted mb-0"></p>
                </div>

                <div class="mb-3 position-relative">
                    <label for="sku_search" class="form-label">Search SKU <span class="text-danger">*</span></label>
                    <input type="text" id="sku_search" class="form-control" placeholder="Type to search by SKU or parent...">
                    <small class="text-muted">Start typing to search and select multiple SKUs</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Selected SKUs</label>
                    <div id="selectedSkusList" class="border rounded p-3" style="min-height: 80px; max-height: 220px; overflow-y: auto;">
                        <p class="text-muted text-center mb-0">No SKUs selected</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveLinkedSkusBtn">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Target / Last Purchase Modal --}}
<div class="modal fade" id="rfqReportMetaModal" tabindex="-1" aria-labelledby="rfqReportMetaTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable shadow-none modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="rfqReportMetaTitle">Edit</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="rfqReportMetaBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="rfqReportMetaSaveBtn">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

{{-- History Modal --}}
<div class="modal fade" id="rfqHistoryModal" tabindex="-1" aria-labelledby="rfqHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title fw-bold" id="rfqHistoryModalLabel">
                    <i class="fa-solid fa-clock-rotate-left me-2"></i> RFQ Form History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 class="fw-bold mb-3" id="rfqHistoryFormName"></h6>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Action</th>
                                <th>User</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="rfqHistoryBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add Supplier modal (same as supplier list page) --}}
<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="supplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="{{ route('supplier.create') }}" class="needs-validation" novalidate id="addSupplierForm">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="supplierModalLabel">
                        <i class="mdi mdi-account-plus me-2"></i> Add Supplier
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            @php $supplierTypes = ['Supplier', 'Forwarders', 'Photographer']; @endphp
                            <select name="type" class="form-select" required>
                                <option value="">Select Type</option>
                                @foreach ($supplierTypes as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category_id[]" class="form-select select2" data-placeholder="Select Category" multiple required style="min-height: 42px;">
                                @foreach ($categories ?? [] as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="Supplier Name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Company</label>
                            <input type="text" name="company" class="form-control" placeholder="Company Name">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Parents</label>
                            <input type="text" name="parent" class="form-control" placeholder="Use commas to separate multiple Parents">
                        </div>
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Country Code</label>
                                    <input type="text" name="country_code" class="form-control" placeholder="+86">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Phone</label>
                                    <input type="text" name="phone" class="form-control" placeholder="Phone Number">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">City</label>
                            <input type="text" name="city" class="form-control" placeholder="City">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Zone</label>
                            <select name="zone" class="form-select">
                                <option value="">Select Zone</option>
                                <option value="GHZ">GHZ</option>
                                <option value="Ningbo">Ningbo</option>
                                <option value="Tianjin">Tianjin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Approved</label>
                            <div class="d-flex align-items-center gap-2 approval-form-dots flex-wrap">
                                <label class="mb-0 cursor-pointer small text-muted border rounded px-2 py-1" title="Not set">
                                    <input type="radio" name="approval_status" value="" class="d-none" checked> None
                                </label>
                                <label class="mb-0 cursor-pointer d-inline-flex align-items-center" title="disqualified">
                                    <input type="radio" name="approval_status" value="red" class="d-none">
                                    <span class="d-inline-block supplier-approval-dot supplier-approval-dot--red border-0"></span>
                                </label>
                                <label class="mb-0 cursor-pointer d-inline-flex align-items-center" title="Qualified">
                                    <input type="radio" name="approval_status" value="green" class="d-none">
                                    <span class="d-inline-block supplier-approval-dot supplier-approval-dot--green border-0"></span>
                                </label>
                                <label class="mb-0 cursor-pointer d-inline-flex align-items-center" title="Explore">
                                    <input type="radio" name="approval_status" value="yellow" class="d-none">
                                    <span class="d-inline-block supplier-approval-dot supplier-approval-dot--yellow border-0"></span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required placeholder="Email Address">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">WhatsApp</label>
                            <input type="text" name="whatsapp" class="form-control" placeholder="WhatsApp Number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">WeChat</label>
                            <input type="text" name="wechat" class="form-control" placeholder="WeChat ID">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Alibaba</label>
                            <input type="text" name="alibaba" class="form-control" placeholder="Alibaba Profile">
                        </div>
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Website URL</label>
                                    <input type="text" name="website" class="form-control" placeholder="Website URL">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Others</label>
                                    <input type="text" name="others" class="form-control" placeholder="Other Details">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Address</label>
                                    <input type="text" name="address" class="form-control" placeholder="Full Address">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Bank Details</label>
                            <textarea name="bank_details" class="form-control" rows="2" placeholder="Bank Details"></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary" id="addSupplierSubmitBtn">
                            <i class="mdi mdi-content-save"></i> Save Supplier
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
@section('script')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
    // Add Supplier modal (supplier list page) — Select2 + AJAX save
    $(function () {
        const $modal = $('#addSupplierModal');
        if (!$modal.length) return;

        function initCategorySelect2() {
            const $sel = $modal.find('select[name="category_id[]"]');
            if ($sel.length && !$sel.hasClass('select2-hidden-accessible')) {
                $sel.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $sel.data('placeholder') || 'Select Category',
                    dropdownParent: $modal,
                    allowClear: false
                });
            }
        }

        $modal.on('shown.bs.modal', function () {
            setTimeout(initCategorySelect2, 100);
        });

        $modal.on('hidden.bs.modal', function () {
            const $sel = $modal.find('select[name="category_id[]"]');
            if ($sel.hasClass('select2-hidden-accessible')) {
                $sel.select2('destroy');
            }
            $modal.find('form')[0].reset();
            $sel.val(null).trigger('change');
        });

        $('#addSupplierForm').on('submit', function (e) {
            e.preventDefault();
            const form = this;
            const $btn = $('#addSupplierSubmitBtn');
            const fd = new FormData(form);
            const cats = fd.getAll('category_id[]').filter(v => v != null && v !== '');
            if (cats.length === 0) {
                alert('Please select at least one category.');
                return;
            }
            if (!fd.get('type')) { alert('Please select a type.'); return; }
            if (!String(fd.get('name') || '').trim()) { alert('Please enter supplier name.'); return; }
            if (!String(fd.get('email') || '').trim()) { alert('Please enter supplier email.'); return; }

            const orig = $btn.html();
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-1"></i> Saving...');

            $.ajax({
                url: form.action,
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .done(function (res) {
                if (res && res.success) {
                    const newSupplier = {
                        id: res.supplier && res.supplier.id ? res.supplier.id : null,
                        name: res.supplier && res.supplier.name ? res.supplier.name : String(fd.get('name') || '').trim(),
                        email: String(fd.get('email') || '').trim(),
                        company: String(fd.get('company') || '').trim()
                    };
                    const addInst = bootstrap.Modal.getInstance($modal[0]);
                    const reopenSend = function () {
                        $modal[0].removeEventListener('hidden.bs.modal', reopenSend);
                        if (newSupplier.id && newSupplier.email && typeof addSupplier === 'function') {
                            addSupplier(newSupplier);
                            showToast('success', 'Supplier added and selected for email.');
                        }
                        const sendEl = document.getElementById('sendToSupplierModal');
                        if (sendEl) bootstrap.Modal.getOrCreateInstance(sendEl).show();
                    };
                    $modal[0].addEventListener('hidden.bs.modal', reopenSend);
                    if (addInst) addInst.hide();
                    else reopenSend();
                } else {
                    alert((res && res.message) ? res.message : 'Could not save supplier.');
                }
            })
            .fail(function (xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Error saving supplier.';
                alert(msg);
            })
            .always(function () {
                $btn.prop('disabled', false).html(orig);
            });
        });
    });
</script>
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

    // Format a date string as "1 APR 26" (or "1 APR" when withYear is false)
    function formatRfqDate(value, withYear = true) {
        if (!value) return '';
        const d = new Date(String(value).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        const months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
        let out = d.getDate() + ' ' + months[d.getMonth()];
        if (withYear) out += ' ' + String(d.getFullYear()).slice(-2);
        return out;
    }

    const RFQ_BASE_LABELS = {
        supplierName: 'Supplier Name',
        companyName: 'Alias',
        supplierLink: 'Supplier Link',
        productName: 'Product Name',
    };

    function escapeHtml(str) {
        return String(str === null || str === undefined ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function parseRfqFields(formData) {
        let fields = formData.fields || [];
        if (typeof fields === 'string') {
            try { fields = JSON.parse(fields) || []; } catch (e) { fields = []; }
        }
        return Array.isArray(fields) ? fields : [];
    }

    function parseLinkedSkus(formData) {
        let skus = formData.linked_skus || [];
        if (typeof skus === 'string') {
            try { skus = JSON.parse(skus) || []; } catch (e) { skus = []; }
        }
        if (!Array.isArray(skus)) return [];
        return skus.map(function(s) { return String(s).trim(); }).filter(Boolean);
    }

    function buildRfqFieldKeys(formData) {
        const keys = ['supplierName', 'companyName', 'supplierLink', 'productName'];
        parseRfqFields(formData).forEach(function(field) {
            if (field && field.name && keys.indexOf(field.name) === -1) {
                keys.push(field.name);
            }
        });
        return keys;
    }

    function buildRfqMetaKeys(formData) {
        const linkedSkus = parseLinkedSkus(formData);
        if (linkedSkus.length > 0) {
            return linkedSkus;
        }
        return buildRfqFieldKeys(formData);
    }

    function labelForRfqKey(key, formData) {
        const meta = parseReportMeta(formData);
        if (meta.labels && meta.labels[key]) return meta.labels[key];
        if (RFQ_BASE_LABELS[key]) return RFQ_BASE_LABELS[key];
        const field = parseRfqFields(formData).find(function(f) { return f.name === key; });
        if (field) return field.label || field.name;
        return String(key).replace(/([A-Z])/g, ' $1').replace(/^./, function(c) { return c.toUpperCase(); });
    }

    function parseReportMeta(formData) {
        let meta = formData.report_meta || {};
        if (typeof meta === 'string') {
            try { meta = JSON.parse(meta) || {}; } catch (e) { meta = {}; }
        }
        return meta && typeof meta === 'object' ? meta : {};
    }

    function openReportMetaModal(formData, section) {
        const keys = buildRfqMetaKeys(formData);
        const meta = parseReportMeta(formData);
        const values = meta[section] || {};
        const title = section === 'target' ? 'Target' : 'Last Purchase';

        document.getElementById('rfqReportMetaTitle').textContent = title + ' — ' + (formData.name || '');
        document.getElementById('rfqReportMetaBody').innerHTML = keys.length
            ? keys.map(function(key) {
                if (key === 'additionalPhotos') return '';
                const val = values[key] != null ? values[key] : '';
                return `<div class="mb-3">
                    <label class="form-label mb-1 small fw-semibold">${escapeHtml(labelForRfqKey(key, formData))}</label>
                    <input type="text" class="form-control form-control-sm rfq-meta-input" data-key="${escapeHtml(key)}" value="${escapeHtml(String(val))}">
                </div>`;
            }).join('')
            : '<p class="text-muted mb-0">Link SKUs in the Linked SKU column, or define form fields first.</p>';

        const modal = document.getElementById('rfqReportMetaModal');
        modal.dataset.formId = formData.id;
        modal.dataset.section = section;
        new bootstrap.Modal(modal).show();
    }

    // Show the created/updated history for an RFQ form in a modal
    function showRfqHistory(data) {
        document.getElementById('rfqHistoryFormName').textContent = data.name || '';

        const rows = [
            {
                action: 'Created',
                user: data.created_by || '-',
                date: formatRfqDate(data.created_at) || '-'
            },
            {
                action: 'Last Updated',
                user: data.updated_by || '-',
                date: formatRfqDate(data.updated_at) || '-'
            }
        ];

        document.getElementById('rfqHistoryBody').innerHTML = rows.map(r => `
            <tr>
                <td><span class="badge bg-light text-dark">${r.action}</span></td>
                <td>${r.user}</td>
                <td class="fw-semibold">${r.date}</td>
            </tr>
        `).join('');

        new bootstrap.Modal(document.getElementById('rfqHistoryModal')).show();
    }

    document.addEventListener('DOMContentLoaded', function () {

        document.getElementById('rfqReportMetaSaveBtn').addEventListener('click', function() {
            const modal = document.getElementById('rfqReportMetaModal');
            const formId = modal.dataset.formId;
            const section = modal.dataset.section;
            if (!formId || !section) return;

            const values = {};
            document.querySelectorAll('#rfqReportMetaBody .rfq-meta-input').forEach(function(input) {
                values[input.dataset.key] = input.value;
            });

            const btn = this;
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

            fetch(`/rfq-form/${formId}/report-meta`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ section: section, values: values })
            })
            .then(function(res) { return res.json(); })
            .then(function(res) {
                if (res.success) {
                    const row = table.getRows().find(function(r) { return String(r.getData().id) === String(formId); });
                    if (row) {
                        const data = row.getData();
                        data.report_meta = res.report_meta || data.report_meta;
                        row.update(data);
                    }
                    bootstrap.Modal.getInstance(modal).hide();
                    showToast('success', res.message || 'Saved successfully!');
                } else {
                    alert(res.message || 'Failed to save');
                }
            })
            .catch(function() { alert('Error saving data'); })
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = orig;
            });
        });

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
                    title: "Linked SKU",
                    field: "linked_skus",
                    width: 220,
                    headerSort: false,
                    formatter: function(cell){
                        const data = cell.getData();
                        let skus = data.linked_skus || [];
                        if(typeof skus === 'string'){
                            try { skus = JSON.parse(skus) || []; } catch(e){ skus = []; }
                        }
                        if(!Array.isArray(skus)) skus = [];

                        const badges = skus.length
                            ? skus.map(s => `<span class="badge bg-info-subtle text-dark border me-1 mb-1">${s}</span>`).join('')
                            : '<span class="text-muted fst-italic">No SKUs</span>';

                        return `
                            <div class="d-flex flex-column align-items-start py-1">
                                <div class="mb-1" style="line-height:1.6;">${badges}</div>
                                <button class="btn btn-sm btn-outline-primary manage-skus-btn" data-id="${data.id}" title="Add SKU" style="cursor:pointer; padding:2px 8px;">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        `;
                    },
                    cellClick: function(e, cell){
                        if(e.target.closest('.manage-skus-btn')){
                            e.preventDefault();
                            e.stopPropagation();
                            openLinkedSkusModal(cell.getData());
                        }
                    }
                },
                {
                    title: "Form Link",
                    field: "slug",
                    width: 220,
                    formatter: function(cell, formatterParams, onRendered){
                        const slug = cell.getValue();
                        if(!slug) return "";

                        const base = window.location.origin + `/api/rfq-form/${slug}`;
                        const basicsUrl = `${base}?part=basics`;
                        const detailsUrl = `${base}?part=details`;

                        return `
                            <div class="d-flex flex-column gap-1 py-1">
                                <div class="d-flex align-items-center gap-1">
                                    <a href="${basicsUrl}" target="_blank" class="btn btn-sm btn-primary flex-grow-1 text-start">
                                        <i class="fa-solid fa-link me-1"></i> Basics
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary copy-form-link" data-link="${basicsUrl}" title="Copy Basics link">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <a href="${detailsUrl}" target="_blank" class="btn btn-sm btn-success flex-grow-1 text-start">
                                        <i class="fa-solid fa-link me-1"></i> Details
                                    </a>
                                    <button class="btn btn-sm btn-outline-success copy-form-link" data-link="${detailsUrl}" title="Copy Details link">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                    },
                    cellClick: function(e, cell){
                        const copyBtn = e.target.closest('.copy-form-link');
                        if(copyBtn){
                            const link = copyBtn.dataset.link;
                            navigator.clipboard.writeText(link).then(() => {
                                showToast('success', 'Link copied successfully!');
                            }).catch(() => {
                                alert('Failed to copy link');
                            });
                        }
                    }
                },
                {
                    title: "RFQ",
                    field: "_rfq_menu_col",
                    headerSort: false,
                    hozAlign: "center",
                    width: 90,
                    formatter: function() {
                        return `
                            <div class="dropdown d-inline-block rfq-row-dropdown">
                                <button class="btn btn-sm btn-success dropdown-toggle py-0 px-2 rfq-row-dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-display="static" aria-expanded="false">RFQ</button>
                                <ul class="dropdown-menu dropdown-menu-end rfq-row-dropdown-menu">
                                    <li><a class="dropdown-item rfq-row-create" href="#"><i class="fas fa-plus-circle me-2"></i> Create RFQ Form</a></li>
                                    <li><a class="dropdown-item rfq-row-target" href="#"><i class="fas fa-bullseye me-2"></i> Target</a></li>
                                    <li><a class="dropdown-item rfq-row-last-purchase" href="#"><i class="fa-solid fa-clock-rotate-left me-2"></i> Last Purchase</a></li>
                                </ul>
                            </div>
                        `;
                    },
                    cellClick: function(e, cell) {
                        const toggle = e.target.closest('.rfq-row-dropdown-toggle');
                        if (toggle && !e.target.closest('.dropdown-item')) {
                            e.stopPropagation();
                            e.preventDefault();
                            rfqRowDropdownContext = cell.getRow();
                            bootstrap.Dropdown.getOrCreateInstance(toggle).toggle();
                            return;
                        }
                        if (e.target.closest('.rfq-row-dropdown-menu')) {
                            e.stopPropagation();
                        }
                    },
                },
                {
                    title: "Report",
                    field: "_report_col",
                    formatter: function(cell, formatterParams, onRendered){
                        const slug = cell.getData().slug;
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
                    title: "Created / Updated",
                    field: "updated_at",
                    hozAlign: "center",
                    formatter: function(cell){
                        const d = cell.getData();
                        const updatedDate = formatRfqDate(d.updated_at, false);
                        const updatedBy = d.updated_by || '-';

                        return `
                            <div class="d-flex align-items-center justify-content-center gap-2 py-1" style="font-size:11px;">
                                <div class="d-flex flex-column align-items-start lh-sm">
                                    <span class="fw-semibold">${updatedDate || '-'}</span>
                                    <span class="text-muted">${updatedBy}</span>
                                </div>
                                <button class="btn btn-sm btn-outline-secondary history-btn" data-id="${d.id}" title="View History" style="cursor:pointer; padding:2px 8px;">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </button>
                            </div>
                        `;
                    },
                    cellClick: function(e, cell){
                        if(e.target.closest('.history-btn')){
                            e.preventDefault();
                            e.stopPropagation();
                            showRfqHistory(cell.getData());
                        }
                    }
                },
                {
                    title: "Action",
                    field: "_action_col",
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
                                <button class="btn btn-sm btn-warning me-2 copy-btn" data-id="${rowData.id}" title="Duplicate Form" style="cursor:pointer;">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                                <button class="btn btn-sm btn-primary me-2 send-email-btn" data-id="${rowData.id}" data-slug="${rowData.slug}" data-name="${rowData.name}" title="Send to Supplier" style="cursor:pointer;">
                                    <i class="fa-solid fa-envelope"></i>
                                </button>
                                <button class="btn btn-sm btn-dark me-2 links-btn" data-id="${rowData.id}" data-slug="${rowData.slug}" data-name="${rowData.name}" title="Supplier Links (Basics / Details)" style="cursor:pointer;">
                                    <i class="fa-solid fa-share-nodes"></i>
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
                                                    <div class="col-md-2">
                                                        <input type="text" name="fields[${index}][name]" class="form-control field-name" value="${field.name || ''}" readonly>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <select name="fields[${index}][type]" class="form-select field-type">
                                                            <option value="text" ${field.type === 'text' ? 'selected':''}>Text</option>
                                                            <option value="number" ${field.type === 'number' ? 'selected':''}>Number</option>
                                                            <option value="select" ${field.type === 'select' ? 'selected':''}>Select</option>
                                                        </select>
                                                    </div>
                                                    ${fieldPartCol(index, field.part)}
                                                    <div class="col-md-2 select-options-wrapper" style="${field.type === 'select' ? 'display:block':'display:none'};">
                                                        <input type="text" name="fields[${index}][options]" class="form-control" value="${field.options || ''}">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <input type="checkbox" name="fields[${index}][required]" class="form-check-input mt-2" value="1" ${field.required ? 'checked':''}>
                                                    </div>
                                                    <div class="col-md-12">
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
                                                    <div class="col-md-2">
                                                        <input type="text" name="fields[${index}][name]" class="form-control field-name" value="${field.name || ''}" readonly>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <select name="fields[${index}][type]" class="form-select field-type">
                                                            <option value="text" ${field.type === 'text' ? 'selected':''}>Text</option>
                                                            <option value="number" ${field.type === 'number' ? 'selected':''}>Number</option>
                                                            <option value="select" ${field.type === 'select' ? 'selected':''}>Select</option>
                                                        </select>
                                                    </div>
                                                    ${fieldPartCol(index, field.part)}
                                                    <div class="col-md-2 select-options-wrapper" style="${field.type === 'select' ? 'display:block':'display:none'};">
                                                        <input type="text" name="fields[${index}][options]" class="form-control" value="${field.options || ''}">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <input type="checkbox" name="fields[${index}][required]" class="form-check-input mt-2" value="1" ${field.required ? 'checked':''}>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <button type="button" class="btn btn-danger btn-sm remove-field">X</button>
                                                    </div>
                                                </div>
                                            `);
                                        });
                                    } else {
                                        // Add one empty field if no fields exist
                                        wrapper.insertAdjacentHTML('beforeend', createFieldRow(0));
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

                        if(e.target.closest('.links-btn')) {
                            e.preventDefault();
                            const btn = e.target.closest('.links-btn');
                            openSupplierLinksModal(btn.dataset.slug, btn.dataset.name);
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

        let rfqContextRow = null;
        let rfqRowDropdownContext = null;

        function setRfqContextRow(row) {
            if (!row) return;
            rfqContextRow = row;
            table.getRows().forEach(function(r) {
                r.getElement().classList.remove('rfq-context-row');
            });
            row.getElement().classList.add('rfq-context-row');
        }

        table.on('cellClick', function(e, cell) {
            if (e.target.closest('#rfq-actions-dropdown-btn, .dropdown-menu, .modal, .rfq-row-dropdown')) {
                return;
            }
            setRfqContextRow(cell.getRow());
        });

        document.getElementById('rfq-action-create').addEventListener('click', function(e) {
            e.preventDefault();
            isCopyAction = false;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('createRFQFormModal')).show();
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.rfq-row-dropdown-menu')) return;
            const row = rfqRowDropdownContext;
            if (!row) return;
            const data = row.getData();
            if (e.target.closest('.rfq-row-create')) {
                e.preventDefault();
                isCopyAction = false;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('createRFQFormModal')).show();
            } else if (e.target.closest('.rfq-row-target')) {
                e.preventDefault();
                setRfqContextRow(row);
                openReportMetaModal(data, 'target');
            } else if (e.target.closest('.rfq-row-last-purchase')) {
                e.preventDefault();
                setRfqContextRow(row);
                openReportMetaModal(data, 'last_purchase');
            }
        });

        document.getElementById('rfq-action-target').addEventListener('click', function(e) {
            e.preventDefault();
            if (!rfqContextRow) {
                alert('Click a form row first, then choose Target from the RFQ menu.');
                return;
            }
            openReportMetaModal(rfqContextRow.getData(), 'target');
        });

        document.getElementById('rfq-action-last-purchase').addEventListener('click', function(e) {
            e.preventDefault();
            if (!rfqContextRow) {
                alert('Click a form row first, then choose Last Purchase from the RFQ menu.');
                return;
            }
            openReportMetaModal(rfqContextRow.getData(), 'last_purchase');
        });

        // Row RFQ dropdown menus: portal to body so Tabulator overflow does not clip them
        document.addEventListener('shown.bs.dropdown', function (e) {
            const toggle = e.target.closest('.rfq-row-dropdown-toggle');
            if (!toggle) return;
            const tr = toggle.closest('.tabulator-row');
            if (tr) rfqRowDropdownContext = table.getRow(tr);
            const menu = toggle.parentElement ? toggle.parentElement.querySelector('.dropdown-menu') : null;
            if (!menu) return;
            if (!menu._rfqHome) menu._rfqHome = menu.parentElement;
            document.body.appendChild(menu);
            menu.classList.add('show');
            const rect = toggle.getBoundingClientRect();
            menu.style.position = 'fixed';
            menu.style.zIndex = '20000';
            menu.style.top = (rect.bottom + 2) + 'px';
            menu.style.left = Math.max(8, rect.right - menu.offsetWidth) + 'px';
        });
        document.addEventListener('hide.bs.dropdown', function (e) {
            const toggle = e.target.closest('.rfq-row-dropdown-toggle');
            if (!toggle) return;
            const menu = document.querySelector('body > .rfq-row-dropdown-menu.show');
            if (!menu) return;
            menu.classList.remove('show');
            menu.style.cssText = '';
            if (menu._rfqHome) menu._rfqHome.appendChild(menu);
        });

        // ===== Import a new RFQ Form from a template =====
        document.getElementById('downloadFormTemplateBtn').addEventListener('click', function(e){
            e.preventDefault();
            const formSheet = [
                ['Name', 'Title', 'Subtitle', 'Dimension Inner', 'Product Dimension', 'Package Dimension'],
                ['Sample RFQ Form', 'RFQ for Sample Product', 'Short description shown on the form', 'no', 'no', 'no']
            ];
            const fieldsSheet = [
                ['Label', 'Name', 'Type', 'Part', 'Options', 'Required'],
                ['Material', 'material', 'text', 'details', '', 'yes'],
                ['Color', 'color', 'select', 'details', 'Black, White, Red', 'no'],
                ['Target Price', 'target_price', 'number', 'basics', '', 'yes']
            ];
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(formSheet), 'Form');
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(fieldsSheet), 'Fields');
            XLSX.writeFile(wb, 'rfq_form_template.xlsx');
        });

        document.getElementById('importFormBtn').addEventListener('click', function(e){
            e.preventDefault();
            document.getElementById('importFormInput').click();
        });

        document.getElementById('importFormInput').addEventListener('change', function(e){
            const file = e.target.files[0];
            if(!file) return;

            const truthy = v => ['yes','y','true','1','required'].includes(String(v).toLowerCase().trim());
            const reader = new FileReader();
            reader.onload = function(ev){
                try {
                    const wb = XLSX.read(ev.target.result, { type: 'array' });

                    // Sheet names are case-insensitive: find "Form" and "Fields"
                    const findSheet = (name) => wb.SheetNames.find(n => n.toLowerCase() === name) || null;
                    const formSheetName = findSheet('form') || wb.SheetNames[0];
                    const fieldsSheetName = findSheet('fields') || wb.SheetNames[1];

                    const formRows = XLSX.utils.sheet_to_json(wb.Sheets[formSheetName], { defval: '' });
                    const meta = formRows[0] || {};

                    const getMeta = (keys) => {
                        for(const k of keys){
                            for(const mk in meta){
                                if(mk.toLowerCase().trim() === k){ return meta[mk]; }
                            }
                        }
                        return '';
                    };

                    const payload = {
                        name: getMeta(['name', 'form name', 'rfq form name']),
                        title: getMeta(['title', 'heading', 'form heading']),
                        subtitle: getMeta(['subtitle', 'description']),
                        dimension_inner: truthy(getMeta(['dimension inner', 'dimension inner box'])) ? 'true' : null,
                        product_dimension: truthy(getMeta(['product dimension'])) ? 'true' : null,
                        package_dimension: truthy(getMeta(['package dimension'])) ? 'true' : null,
                        fields: []
                    };

                    if(!payload.name || !payload.title){
                        alert('The "Form" sheet must include at least a Name and Title.');
                        e.target.value = '';
                        return;
                    }

                    const fieldRows = fieldsSheetName ? XLSX.utils.sheet_to_json(wb.Sheets[fieldsSheetName], { defval: '' }) : [];
                    const pick = (row, keys) => {
                        for(const rk in row){
                            if(keys.includes(rk.toLowerCase().trim())) return row[rk];
                        }
                        return '';
                    };

                    payload.fields = fieldRows.map((row, i) => {
                        const label = String(pick(row, ['label'])).trim();
                        if(!label) return null;
                        let name = String(pick(row, ['name'])).trim();
                        if(!name) name = label.toLowerCase().replace(/\s+/g,'_').replace(/[^a-z0-9_]/g,'');
                        let type = String(pick(row, ['type'])).toLowerCase().trim();
                        if(!['text','number','select'].includes(type)) type = 'text';
                        let part = String(pick(row, ['part'])).toLowerCase().trim();
                        if(!['basics','details'].includes(part)) part = 'details';
                        return {
                            label: label,
                            name: name,
                            type: type,
                            part: part,
                            options: String(pick(row, ['options'])).trim(),
                            required: truthy(pick(row, ['required'])) ? 1 : 0,
                            order: i + 1
                        };
                    }).filter(Boolean);

                    if(payload.fields.length === 0){
                        alert('The "Fields" sheet must include at least one field (with a Label).');
                        e.target.value = '';
                        return;
                    }

                    fetch('/rfq-form/import', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: JSON.stringify(payload)
                    })
                    .then(r => r.json())
                    .then(res => {
                        if(res.success){
                            showToast('success', 'Form imported successfully!');
                            table.replaceData();
                        } else {
                            alert('Import failed: ' + (res.message || 'Unknown error'));
                        }
                    })
                    .catch(() => alert('Error importing form'))
                    .finally(() => { e.target.value = ''; });
                } catch(err){
                    alert('Could not read the file. Please use the provided template.');
                    e.target.value = '';
                }
            };
            reader.readAsArrayBuffer(file);
        });

        // ===== Supplier links (Basics / Details / Combined) =====
        let linksCurrentSlug = '';

        function generateToken(){
            if(window.crypto && crypto.randomUUID){
                return crypto.randomUUID().replace(/-/g, '').slice(0, 20);
            }
            return 'tok' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
        }

        function buildSupplierLinks(token){
            const base = window.location.origin + '/api/rfq-form/' + linksCurrentSlug;
            document.getElementById('links_token').textContent = token;
            document.getElementById('link_basics').value = `${base}?part=basics&token=${token}`;
            document.getElementById('link_details').value = `${base}?part=details&token=${token}`;
            document.getElementById('link_combined').value = `${base}?part=all&token=${token}`;
        }

        function openSupplierLinksModal(slug, name){
            linksCurrentSlug = slug;
            document.getElementById('links_form_name').textContent = name || '';
            buildSupplierLinks(generateToken());
            new bootstrap.Modal(document.getElementById('supplierLinksModal')).show();
        }
        window.openSupplierLinksModal = openSupplierLinksModal;

        document.getElementById('regenerateTokenBtn').addEventListener('click', function(){
            buildSupplierLinks(generateToken());
        });

        document.querySelectorAll('#supplierLinksModal .copy-link-btn').forEach(btn => {
            btn.addEventListener('click', function(){
                const input = document.getElementById(this.dataset.target);
                if(!input) return;
                navigator.clipboard.writeText(input.value).then(() => {
                    showToast('success', 'Link copied!');
                }).catch(() => {
                    input.select();
                    document.execCommand('copy');
                });
            });
        });

        // Auto-open the edit modal when arriving with ?edit=ID (e.g. from the public form's Edit button)
        const urlParams = new URLSearchParams(window.location.search);
        const autoEditId = urlParams.get('edit');
        if(autoEditId){
            const onceOpen = function(){
                const editBtn = document.querySelector(`.edit-btn[data-id="${autoEditId}"]`);
                if(editBtn){
                    table.off("renderComplete", onceOpen);
                    editBtn.click();
                }
            };
            table.on("renderComplete", onceOpen);
        }


        let fieldCount = 1;

        function slugify(text){
            return text.toLowerCase().replace(/\s+/g,'_').replace(/[^a-z0-9_]/g,'');
        }

        // Part (Basics / Details) selector column for a field row
        function fieldPartCol(index, part){
            const p = (part === 'basics') ? 'basics' : 'details';
            return `<div class="col-md-2">
                    <select name="fields[${index}][part]" class="form-select field-part">
                        <option value="basics" ${p === 'basics' ? 'selected' : ''}>Basics</option>
                        <option value="details" ${p === 'details' ? 'selected' : ''}>Details</option>
                    </select>
                </div>`;
        }

        function createFieldRow(index){
            return `
            <div class="row g-3 mb-2 field-item">
                <div class="col-md-3">
                    <input type="text" name="fields[${index}][label]" class="form-control field-label" placeholder="Field Label" required>
                </div>
                <div class="col-md-2">
                    <input type="text" name="fields[${index}][name]" class="form-control field-name" placeholder="Field Name (auto)" readonly>
                </div>
                <div class="col-md-2">
                    <select name="fields[${index}][type]" class="form-select field-type">
                        <option value="text">Text</option>
                        <option value="number">Number</option>
                        <option value="select">Select</option>
                    </select>
                </div>
                ${fieldPartCol(index)}
                <div class="col-md-2 select-options-wrapper" style="display:none;">
                    <input type="text" name="fields[${index}][options]" class="form-control" placeholder="Options (comma separated)">
                </div>
                <div class="col-md-1">
                    <input type="checkbox" name="fields[${index}][required]" class="form-check-input mt-2" value="1"> Required
                </div>
                <div class="col-md-12">
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
                wrapper.innerHTML = createFieldRow(0);
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

        document.getElementById('rfqAddSupplierBtn').addEventListener('click', function () {
            const sendEl = document.getElementById('sendToSupplierModal');
            const sendInst = bootstrap.Modal.getInstance(sendEl);
            const openAdd = function () {
                sendEl.removeEventListener('hidden.bs.modal', openAdd);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('addSupplierModal')).show();
            };
            sendEl.addEventListener('hidden.bs.modal', openAdd);
            if (sendInst) sendInst.hide();
            else openAdd();
        });

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
                const part = item.querySelector('.field-part')?.value || 'details';
                const options = item.querySelector('[name*="[options]"]')?.value || '';
                const required = item.querySelector('[name*="[required]"]')?.checked ? 1 : 0;
                fields.push({ label, name, type, part, options, required, order: index + 1 });
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

        // ===== Linked SKUs management =====
        let selectedSkus = [];
        let skuSearchTimeout;

        window.openLinkedSkusModal = function(data){
            document.getElementById('linked_skus_form_id').value = data.id;
            document.getElementById('linked_skus_form_name').textContent = data.name || '';

            let skus = data.linked_skus || [];
            if(typeof skus === 'string'){
                try { skus = JSON.parse(skus) || []; } catch(e){ skus = []; }
            }
            if(!Array.isArray(skus)) skus = [];
            selectedSkus = skus.slice();

            document.getElementById('sku_search').value = '';
            renderSelectedSkus();
            hideSkuDropdown();

            new bootstrap.Modal(document.getElementById('linkedSkusModal')).show();
        };

        function renderSelectedSkus(){
            const list = document.getElementById('selectedSkusList');
            if(!selectedSkus.length){
                list.innerHTML = '<p class="text-muted text-center mb-0">No SKUs selected</p>';
                return;
            }
            list.innerHTML = selectedSkus.map(sku => `
                <span class="badge bg-primary-subtle text-dark border d-inline-flex align-items-center me-1 mb-1 p-2">
                    ${sku}
                    <button type="button" class="btn-close ms-2" style="font-size:.6rem;" data-sku="${sku}" aria-label="Remove"></button>
                </span>
            `).join('');
        }

        document.getElementById('selectedSkusList').addEventListener('click', function(e){
            const btn = e.target.closest('[data-sku]');
            if(btn){
                const sku = btn.getAttribute('data-sku');
                selectedSkus = selectedSkus.filter(s => s !== sku);
                renderSelectedSkus();
            }
        });

        document.getElementById('sku_search').addEventListener('input', function(e){
            const term = e.target.value.trim();
            if(term.length < 1){ hideSkuDropdown(); return; }
            clearTimeout(skuSearchTimeout);
            skuSearchTimeout = setTimeout(() => {
                fetch(`{{ url('/rfq-form/skus/search') }}?q=${encodeURIComponent(term)}`, {
                    headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if(res.items && res.items.length){ showSkuDropdown(res.items); }
                    else { hideSkuDropdown(); }
                })
                .catch(() => hideSkuDropdown());
            }, 300);
        });

        function showSkuDropdown(items){
            hideSkuDropdown();
            const dropdown = document.createElement('div');
            dropdown.id = 'skuDropdown';
            dropdown.className = 'list-group position-absolute w-100';
            dropdown.style.cssText = 'z-index:1060; max-height:220px; overflow-y:auto; border:1px solid #ddd; border-radius:4px;';

            let added = 0;
            items.forEach(item => {
                if(selectedSkus.includes(item.id)) return;
                added++;
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action';
                a.textContent = item.text;
                a.addEventListener('click', function(ev){
                    ev.preventDefault();
                    if(!selectedSkus.includes(item.id)){
                        selectedSkus.push(item.id);
                        renderSelectedSkus();
                    }
                    document.getElementById('sku_search').value = '';
                    hideSkuDropdown();
                });
                dropdown.appendChild(a);
            });

            if(added === 0) return;

            const wrapper = document.getElementById('sku_search').parentElement;
            wrapper.appendChild(dropdown);
        }

        function hideSkuDropdown(){
            const d = document.getElementById('skuDropdown');
            if(d) d.remove();
        }

        document.addEventListener('click', function(e){
            if(!e.target.closest('#sku_search') && !e.target.closest('#skuDropdown')){
                hideSkuDropdown();
            }
        });

        document.getElementById('saveLinkedSkusBtn').addEventListener('click', function(){
            const id = document.getElementById('linked_skus_form_id').value;
            const btn = this;
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

            const formData = new FormData();
            if(selectedSkus.length === 0){
                formData.append('linked_skus', '');
            }
            selectedSkus.forEach(sku => formData.append('linked_skus[]', sku));

            fetch(`/rfq-form/${id}/linked-skus`, {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept':'application/json' }
            })
            .then(r => r.json())
            .then(res => {
                if(res.success){
                    const row = table.getRow(id);
                    if(row){ row.update({ linked_skus: res.linked_skus }); }
                    showToast('success', 'Linked SKUs updated successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('linkedSkusModal')).hide();
                } else {
                    alert('Failed: ' + (res.message || 'Unknown error'));
                }
            })
            .catch(() => alert('Error saving linked SKUs'))
            .finally(() => { btn.disabled = false; btn.innerHTML = original; });
        });

    });
</script>

@endsection