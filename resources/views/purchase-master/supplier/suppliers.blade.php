@extends('layouts.vertical', ['title' => 'Suppliers', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <style>
        .upload-zone {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px dashed #dee2e6;
            position: relative;
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: #198754;
            background-color: rgba(25, 135, 84, 0.05);
        }
    </style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Suppliers', 'sub_title' => 'Suppliers'])

@if(Session::has('flash_message'))
<div class="alert alert-primary bg-primary text-white alert-dismissible fade show" role="alert" style="background-color: #169e28 !important; color: #fff !important;">
    {{ Session::get('flash_message') }}
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-title mb-0">Suppliers</h4>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#addSupplierModal">
                            <i class="mdi mdi-plus me-1"></i> Add Supplier
                        </button>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal"
                            data-bs-target="#bulkImportModal">
                            <i class="mdi mdi-file-import me-1"></i> Bulk Import
                        </button>
                    </div>
                </div>

                <!-- Bulk Import Modal -->
                <div class="modal fade" id="bulkImportModal" tabindex="-1" aria-labelledby="bulkImportModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered shadow-none">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title fw-bold" id="bulkImportModalLabel">
                                    <i class="mdi mdi-file-import me-2"></i> Bulk Import Suppliers
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <form action="{{ route('supplier.import') }}" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                    @csrf
                                    <div class="text-center mb-4">
                                        <div class="upload-zone p-4 border-2 border-dashed rounded-3 position-relative" id="drop-zone">
                                            <i class="mdi mdi-file-excel text-success" style="font-size: 3rem;"></i>
                                            <h5 class="mt-3 mb-2">Drop your Excel file here</h5>
                                            <p class="text-muted mb-3">or click to browse</p>

                                            <input type="file" name="file" id="file-input" accept=".xlsx, .xls, .csv" class="position-absolute w-100 h-100 top-0 start-0 opacity-0" required style="cursor: pointer;">
                                        </div>
                                        <!-- File name display -->
                                        <div id="file-name" class="mt-2 text-success fw-semibold"></div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <a href="{{ asset('sample_excel/sample_supplier_import.xlsx') }}" class="btn btn-light">
                                            <i class="mdi mdi-download me-1"></i> Download Template
                                        </a>
                                        <button type="submit" class="btn btn-success">
                                            <i class="mdi mdi-upload me-1"></i> Upload & Import
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <label for="category-filter" class="form-label fw-semibold">Category</label>
                        <select class="form-select select2" id="category-filter" data-placeholder="Filter by category">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->name }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="type-filter" class="form-label fw-semibold">Type</label>
                        @php
                            $types = ['Supplier','Forwarders', 'Photographer'];
                        @endphp
                        <select class="form-select select2" id="type-filter" data-placeholder="Filter by type">
                            <option value="">Select Type</option>
                            @foreach($types as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="search-input" class="form-label fw-semibold">Search by name</label>
                        <div class="input-group">
                            <span class="input-group-text" style="height: 42px;"><i class="mdi mdi-magnify"></i></span>
                            <input type="text" id="search-input" class="form-control" placeholder="Search suppliers..." style="height: 42px;">
                        </div>
                    </div>
                </div>

                <div class="table-responsive" style="position: relative; overflow: visible;">
                    <table class="table table-centered table-hover mb-0" id="suppliers-table" style="overflow: visible;">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Name</th>
                                <th>Company</th>
                                <th>Parents</th>
                                <th>Zone</th>
                                <th>Phone</th>
                                <th>Rating</th>
                                <th>Email</th>
                                <th>WhatsApp</th>
                                <th>WeChat</th>
                                <th>Alibaba</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @include('purchase-master.supplier.partials.rows', ['suppliers' => $suppliers, 'categories' => $categories])
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <div class="pagination-wrapper">
                        {{ $suppliers->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                </div>

                <style>
                    .pagination-wrapper {
                        width: auto;
                        overflow-x: auto;
                    }
                    .pagination-wrapper .pagination {
                        margin: 0;
                        background: #fff;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
                        border-radius: 4px;
                        display: flex;
                        flex-wrap: nowrap;
                        gap: 4px;
                    }
                    .pagination-wrapper .page-item .page-link {
                        padding: 0.5rem 1rem;
                        min-width: 40px;
                        text-align: center;
                        color: #464646;
                        border: 1px solid #f1f1f1;
                        font-weight: 500;
                        transition: all 0.2s ease;
                        border-radius: 6px;
                    }
                    .pagination-wrapper .page-item.active .page-link {
                        background: linear-gradient(135deg, #727cf5, #6366f1);
                        border: none;
                        color: white;
                        font-weight: 600;
                        box-shadow: 0 2px 4px rgba(114,124,245,0.2);
                    }
                    .pagination-wrapper .page-item .page-link:hover:not(.active) {
                        background-color: #f8f9fa;
                        color: #727cf5;
                        border-color: #e9ecef;
                    }
                    /* Hide the "Showing x to y of z results" text */
                    .pagination-wrapper p.small,
                    .pagination-wrapper div.flex.items-center.justify-between {
                        display: none !important;
                    }
                    @media (max-width: 576px) {
                        .pagination-wrapper .page-item .page-link {
                            padding: 0.4rem 0.8rem;
                            min-width: 35px;
                            font-size: 0.875rem;
                        }
                    }
                </style>
            </div>
        </div>
    </div>
</div>

<!-- Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="supplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="{{ route('supplier.create') }}" class="needs-validation" novalidate id="addSupplierForm">
                @csrf
                
                @if ($errors->any())
                    <div class="alert alert-danger m-3">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
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
                            @php
                                $types = ['Supplier','Forwarders', 'Photographer'];
                            @endphp
                            <select name="type" class="form-select" required>
                                <option value="">Select Type</option>
                                @foreach($types as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category_id[]" class="form-select select2" data-placeholder="Select Category" multiple required style="min-height: 42px;">
                                @foreach($categories as $category)
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
                            <input type="text" name="parent" class="form-control" placeholder="Use commas to separate multiple Parents (e.g., TV-BOX, CAMERA)">
                            <small class="text-muted">Separate multiple parents with commas</small>
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
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="Email Address">
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
                                    <input type="text" name="website" class="form-control" placeholder="enter website URL">
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
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-content-save"></i> Save Supplier
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rating Modal -->
<div class="modal fade" id="ratingModal" tabindex="-1" aria-labelledby="ratingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="{{ route('supplier.rating.save') }}" class="needs-validation" novalidate>
                @csrf
                <input type="hidden" id="modal-supplier-id" name="supplier_id">
                <input type="hidden" id="modal-parent" name="parent">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="ratingModalLabel">
                        🌟 Rate Supplier Performance
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3 mb-4">
                        <!-- Supplier Name -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">👤 Supplier</label>
                            <input type="text" class="form-control" id="modal-supplier-name" readonly style="background-color: #e9ecef;">
                        </div>

                        <!-- Evaluation Date -->
                        <div class="col-md-6">
                            <label for="evaluation_date" class="form-label fw-semibold">🗓️ Evaluation Date</label>
                            <input type="date" name="evaluation_date" id="evaluation_date" class="form-control" required value="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <!-- Rating Table -->
                    <h5 class="mb-3 fw-semibold">📊 Evaluation Criteria</h5>
                    @php
                        $criteria = [
                            ['emoji' => '💎', 'label' => 'Product Quality', 'weight' => 20],
                            ['emoji' => '🚚', 'label' => 'Timely Delivery', 'weight' => 15],
                            ['emoji' => '📄', 'label' => 'Document Accuracy', 'weight' => 5],
                            ['emoji' => '💰', 'label' => 'Pricing', 'weight' => 15],
                            ['emoji' => '📦', 'label' => 'Packaging & Labeling', 'weight' => 5],
                            ['emoji' => '✅', 'label' => 'Item Match (PO)', 'weight' => 10],
                            ['emoji' => '🤝', 'label' => 'Commercial Terms', 'weight' => 10],
                            ['emoji' => '💬', 'label' => 'Responsiveness', 'weight' => 5],
                            ['emoji' => '🛠️', 'label' => 'Issue Resolution', 'weight' => 5],
                            ['emoji' => '🛡️', 'label' => 'Reliability', 'weight' => 10],
                        ];
                    @endphp

                    <div class="row g-3">
                        @foreach ($criteria as $i => $item)
                        <div class="col-md-6">
                            <div class="p-3 border rounded d-flex justify-content-between align-items-center h-100">
                                <div>
                                    <label for="score_{{ $i }}" class="form-label fw-semibold d-block mb-1">
                                        {{ $item['emoji'] }} {{ $item['label'] }}
                                    </label>
                                    <small class="text-muted">Weight: {{ $item['weight'] }}%</small>
                                </div>
                                <div class="flex-shrink-0" style="width: 90px;">
                                    <input type="number" id="score_{{ $i }}" name="criteria[{{ $i }}][score]" class="form-control form-control-sm text-center" min="1" max="10" required placeholder="1-10">
                                    <input type="hidden" name="criteria[{{ $i }}][label]" value="{{ $item['label'] }}">
                                    <input type="hidden" name="criteria[{{ $i }}][weight]" value="{{ $item['weight'] }}">
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <!-- Submit Button -->
                    <div class="d-flex justify-content-end mt-4">
                        <button class="btn btn-primary" type="submit">
                            <i class="mdi mdi-content-save me-1"></i> Submit Rating
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>


@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        
        $(document).ready(function() {
            const fileInput = document.getElementById('file-input');
            const fileNameDisplay = document.getElementById('file-name');
            const dropZone = document.getElementById('drop-zone');

            fileInput.addEventListener('change', function () {
                if (fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    fileNameDisplay.textContent = 'Selected file: ' + file.name;
                }
            });

            // Optional drag-and-drop styling
            dropZone.addEventListener('dragover', function (e) {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
            dropZone.addEventListener('dragleave', function () {
                dropZone.classList.remove('dragover');
            });
            dropZone.addEventListener('drop', function (e) {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    const file = e.dataTransfer.files[0];
                    fileNameDisplay.textContent = 'Selected file: ' + file.name;
                }
            });
        });

        // Initialize Select2 for all select2 elements
        function initSelect2(container) {
            const scope = container || document;
            $(scope).find('.select2').each(function() {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    const $select = $(this);
                    const $modal = $select.closest('.modal');
                    
                    // Get selected values from HTML options before initializing Select2
                    const selectedValues = [];
                    $select.find('option:selected').each(function() {
                        const val = $(this).val();
                        if (val && val !== '') {
                            selectedValues.push(val);
                        }
                    });
                    
                    // Check if this is a category select
                    const isCategorySelect = $select.attr('name') === 'category_id[]';
                    const optionCount = $select.find('option').length;
                    
                    if (isCategorySelect) {
                        console.log('Initializing category Select2 - Options count:', optionCount);
                    }
                    
                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: function () {
                            return $select.data('placeholder') || 'Select...';
                        },
                        dropdownParent: $modal.length ? $modal : $(document.body),
                        allowClear: false
                    });
                    
                    // Ensure selected values are set after initialization
                    if (selectedValues.length > 0) {
                        // Set the value in Select2
                        $select.val(selectedValues).trigger('change');
                        
                        // Also ensure the underlying select options are marked as selected
                        // This is important for form submission
                        $select.find('option').prop('selected', false);
                        selectedValues.forEach(function(val) {
                            $select.find('option[value="' + val + '"]').prop('selected', true);
                        });
                    }
                    
                    if (isCategorySelect) {
                        console.log('Category Select2 initialized - Is accessible:', $select.hasClass('select2-hidden-accessible'));
                    }
                }
            });
        }

        $(document).ready(function () {
            // Initialize Select2 on page load
            initSelect2();
            
            // Initialize Select2 when edit modal is shown
            $(document).on('shown.bs.modal', '[id^="editSupplierModal"]', function() {
                const modal = $(this);
                // Small delay to ensure DOM is ready and Bootstrap modal animation completes
                setTimeout(function() {
                    // Destroy existing Select2 instances in this modal first
                    modal.find('.select2').each(function() {
                        if ($(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2('destroy');
                        }
                    });
                    // Then initialize fresh
                    initSelect2(modal[0]);
                }, 150);
            });
            
            // Initialize Select2 when add modal is shown
            $('#addSupplierModal').on('shown.bs.modal', function() {
                const modal = $(this);
                // Small delay to ensure DOM is ready and Bootstrap modal animation completes
                setTimeout(function() {
                    // Destroy existing Select2 instances in this modal first
                    modal.find('.select2').each(function() {
                        if ($(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2('destroy');
                        }
                    });
                    // Then initialize fresh
                    initSelect2(modal[0]);
                    
                    // Debug: Check if category select was initialized
                    const categorySelect = modal.find('select[name="category_id[]"]');
                    console.log('Add Modal - Category select found:', categorySelect.length);
                    console.log('Add Modal - Is Select2 initialized:', categorySelect.hasClass('select2-hidden-accessible'));
                    console.log('Add Modal - Options count:', categorySelect.find('option').length);
                }, 150);
            });
            
            // Reset form when add modal is closed
            $('#addSupplierModal').on('hidden.bs.modal', function() {
                const modal = $(this);
                // Destroy Select2 instances before reset
                modal.find('.select2').each(function() {
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('destroy');
                    }
                });
                // Reset form
                modal.find('form')[0].reset();
            });
            
            // Handle form submission with validation
            $(document).on('submit', 'form[action="{{ route('supplier.create') }}"]', function(e) {
                const form = $(this);
                const formElement = form[0]; // Get native form element
                
                // Use FormData as the source of truth - this is what will actually be submitted
                const formData = new FormData(formElement);
                const formDataCategories = formData.getAll('category_id[]');
                
                // Filter out empty/null/undefined values
                const finalCategories = formDataCategories.filter(function(val) {
                    return val != null && val !== '' && val !== undefined;
                });
                
                // Debug: Log what we found
                console.log('=== Category Validation Debug ===');
                console.log('FormData categories (raw):', formDataCategories);
                console.log('FormData categories length:', formDataCategories.length);
                console.log('Filtered categories:', finalCategories);
                console.log('Filtered categories length:', finalCategories.length);
                console.log('Will block submission?', finalCategories.length === 0);
                
                // Validate category selection - use FormData as primary check
                // ONLY block if FormData has NO valid categories
                if (finalCategories.length === 0) {
                    console.log('❌ BLOCKING: No categories found in FormData');
                    e.preventDefault();
                    alert('Please select at least one category.');
                    // Try to find and focus the category select
                    const categorySelect = form.find('select[name="category_id[]"]');
                    if (categorySelect.length > 0) {
                        if (categorySelect.hasClass('select2-hidden-accessible')) {
                            categorySelect.select2('open');
                        } else {
                            categorySelect.focus();
                        }
                    }
                    return false;
                } else {
                    console.log('✅ ALLOWING: Categories found in FormData:', finalCategories);
                    // Categories are valid, continue with other validations
                }
                
                // Validate type - use FormData
                const typeValue = formData.get('type');
                console.log('Type value from FormData:', typeValue);
                if (!typeValue || typeValue === '' || typeValue === null) {
                    e.preventDefault();
                    alert('Please select a type.');
                    const typeSelect = form.find('select[name="type"]');
                    if (typeSelect.length > 0) {
                        typeSelect.focus();
                    }
                    return false;
                }
                
                // Validate name - use FormData
                const nameValue = formData.get('name');
                console.log('Name value from FormData:', nameValue);
                if (!nameValue || !nameValue.trim()) {
                    e.preventDefault();
                    alert('Please enter supplier name.');
                    const nameInput = form.find('input[name="name"]');
                    if (nameInput.length > 0) {
                        nameInput.focus();
                    }
                    return false;
                }
                
                console.log('✅ All validations passed - submitting form');
            });

            let searchTimer;
            $('#search-input').on('keyup', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    const value = $(this).val().toLowerCase();
                    $("#suppliers-table tbody tr").each(function () {
                        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                    });
                }, 300);
            });

            $( '#category-filter' ).select2( {
                theme: "bootstrap-5",
                width: $( this ).data( 'width' ) ? $( this ).data( 'width' ) : $( this ).hasClass( 'w-100' ) ? '100%' : 'style',
                placeholder: $( this ).data( 'placeholder' ),
            } );

            
            $('#category-filter').on('change', function () {
                const selectedCategory = $(this).val().toLowerCase();
                $("#suppliers-table tbody tr").each(function () {
                    const categories = $(this).find("td:eq(1)").text().toLowerCase();
                    if (selectedCategory === '') {
                        $(this).show(); // Show all if no category selected
                    } else {
                        $(this).toggle(categories.includes(selectedCategory)); // Show/hide based on match
                    }
                });
            });

            $( '#type-filter' ).select2( {
                theme: "bootstrap-5",
                width: $( this ).data( 'width' ) ? $( this ).data( 'width' ) : $( this ).hasClass( 'w-100' ) ? '100%' : 'style',
                placeholder: $( this ).data( 'placeholder' ),
            } );

            $('#type-filter').on('change', function () {
                const value = $(this).val().toLowerCase();
                $("#suppliers-table tbody tr").each(function () {
                    const type = $(this).find("td:eq(0)").text().toLowerCase(); // Type is column 1 (index 0)
                    if (value === '') {
                        $(this).show(); // Show all if no type selected
                    } else {
                        $(this).toggle(type.includes(value)); // Show/hide based on exact match
                    }
                });
            });
        });

        function openWhatsApp(number) {
            const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
            const baseURL = isMobile
                ? 'https://api.whatsapp.com/send?phone='
                : 'https://web.whatsapp.com/send?phone=';
            window.open(baseURL + number, '_blank');
        }

        //rating modal
        $('.rate-btn').on('click', function () {
            const supplierId = $(this).data('supplier-id');
            const supplierName = $(this).data('supplier-name');
            const parent = $(this).data('parent');
            const skus = $(this).data('skus'); 

            $('#modal-supplier-id').val(supplierId);
            $('#modal-parent').val(parent);

            $('#modal-supplier-name').val(supplierName);

            const skuSelect = $('#modal-skus');
            skuSelect.empty();
            if (Array.isArray(skus)) {
                skus.forEach(sku => {
                    skuSelect.append(new Option(`${parent} → ${sku}`, sku, true, true));
                });
            }

            skuSelect.trigger('change');
        });


        document.body.style.zoom = '90%';

    </script>
@endsection
