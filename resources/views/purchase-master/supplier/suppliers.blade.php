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
        
        /* Smooth transitions for table updates */
        #suppliers-table tbody {
            transition: opacity 0.2s ease-in-out;
        }
        
        #suppliers-table tbody.fade-out {
            opacity: 0.5;
        }
        
        #suppliers-table tbody.fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Smooth pagination transitions */
        .pagination-wrapper {
            transition: opacity 0.2s ease-in-out;
        }
        
        /* Smooth count badge update */
        #supplier-count {
            transition: all 0.3s ease;
        }
        
        /* Smooth Select2 transitions */
        .select2-container {
            transition: all 0.2s ease;
        }
        
        /* Loading indicator smooth fade */
        #loading-indicator {
            transition: opacity 0.3s ease;
        }
        
        /* Smooth scroll behavior */
        html {
            scroll-behavior: smooth;
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
                    <div class="d-flex align-items-center gap-3">
                        <h4 class="card-title mb-0">Suppliers</h4>
                        <span class="badge bg-primary rounded-pill px-3 py-2" id="supplier-count" style="font-size: 1rem; font-weight: 600;">
                            <strong style="font-size: 1.1rem;">{{ number_format($filteredCount) }}</strong>
                            @if($filteredCount != $totalCount)
                                <span class="text-white-50" style="font-size: 0.95rem;">/ {{ number_format($totalCount) }}</span>
                            @endif
                        </span>
                    </div>
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

                <form method="GET" action="{{ route('supplier.list') }}" id="filter-form">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label for="category-filter" class="form-label fw-semibold">Category</label>
                            <select class="form-select select2" id="category-filter" name="category" data-placeholder="Filter by category">
                                <option value="">All Categories</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->name }}" {{ request('category') == $category->name ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="type-filter" class="form-label fw-semibold">Type</label>
                            @php
                                $types = ['Supplier','Forwarders', 'Photographer'];
                            @endphp
                            <select class="form-select select2" id="type-filter" name="type" data-placeholder="Filter by type">
                                <option value="">Select Type</option>
                                @foreach($types as $type)
                                    <option value="{{ $type }}" {{ request('type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="search-input" class="form-label fw-semibold">Search by name</label>
                            <div class="input-group">
                                <span class="input-group-text" style="height: 42px;"><i class="mdi mdi-magnify"></i></span>
                                <input type="text" id="search-input" name="search" class="form-control" placeholder="Search suppliers..." value="{{ request('search') }}" style="height: 42px;">
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-responsive" style="position: relative; overflow-x: auto;">
                    <!-- Loading indicator -->
                    <div id="loading-indicator" class="text-center py-5" style="display: none; position: absolute; top: 0; left: 0; right: 0; background: rgba(255,255,255,0.9); z-index: 10;">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted fw-semibold">Loading suppliers...</p>
                    </div>
                    <table class="table table-centered table-hover mb-0" id="suppliers-table">
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
                    <div class="pagination-wrapper" id="pagination-wrapper">
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

            // Initialize Select2 for category filter with search enabled
            const categorySelect = $('#category-filter');
            // Only initialize if not already initialized
            if (!categorySelect.hasClass('select2-hidden-accessible')) {
                categorySelect.select2({
                    theme: "bootstrap-5",
                    width: '100%',
                    placeholder: categorySelect.data('placeholder') || 'Filter by category',
                    allowClear: true,
                    minimumResultsForSearch: 0, // Always show search box
                }).on('select2:open', function() {
                    // Focus on search input when dropdown opens
                    setTimeout(function() {
                        $('.select2-search__field').focus();
                    }, 80);
                });
            }

            // Initialize Select2 for type filter with search enabled
            const typeSelect = $('#type-filter');
            // Only initialize if not already initialized
            if (!typeSelect.hasClass('select2-hidden-accessible')) {
                typeSelect.select2({
                    theme: "bootstrap-5",
                    width: '100%',
                    placeholder: typeSelect.data('placeholder') || 'Filter by type',
                    allowClear: true,
                    minimumResultsForSearch: 0, // Always show search box
                });
            }

            // Function to load suppliers via AJAX (no page refresh)
            function loadSuppliers(page = 1) {
                // Get values from Select2 properly - ensure we get the actual selected value
                // Get from the underlying select element, not from Select2 instance
                const categorySelect = $('#category-filter');
                const category = categorySelect.val() || '';
                
                const typeSelect = $('#type-filter');
                const type = typeSelect.val() || '';
                
                const search = $('#search-input').val().trim() || '';
                
                // Build query parameters
                const params = new URLSearchParams();
                if (category) params.set('category', category);
                if (type) params.set('type', type);
                if (search) params.set('search', search);
                if (page > 1) params.set('page', page);
                
                // Smooth scroll to table if not on first page or if filters are applied
                if (page === 1 && (category || type || search)) {
                    $('html, body').animate({
                        scrollTop: $('#suppliers-table').offset().top - 150
                    }, 300, 'swing');
                }
                
                // Show loading indicator with smooth fade
                $('#loading-indicator').fadeIn(200);
                $('#suppliers-table tbody').addClass('fade-out');
                $('.pagination-wrapper').fadeOut(150);
                
                // Update URL without page refresh
                const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                window.history.pushState({ path: newURL }, '', newURL);
                
                // Make AJAX request
                $.ajax({
                    url: '{{ route("supplier.list") }}',
                    method: 'GET',
                    data: params.toString(),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Get current filter values from URL params (most reliable source)
                            const urlParams = new URLSearchParams(window.location.search);
                            // Decode URL-encoded values (e.g., "Drum+Stool" becomes "Drum Stool")
                            const currentCategory = urlParams.get('category') ? decodeURIComponent(urlParams.get('category').replace(/\+/g, ' ')) : '';
                            const currentType = urlParams.get('type') ? decodeURIComponent(urlParams.get('type').replace(/\+/g, ' ')) : '';
                            const currentSearch = urlParams.get('search') ? decodeURIComponent(urlParams.get('search').replace(/\+/g, ' ')) : '';
                            
                            // First, check if modals are actually in the response HTML
                            const hasEditModals = response.html.indexOf('editSupplierModal') !== -1;
                            const hasViewModals = response.html.indexOf('viewSupplierModal') !== -1;
                            console.log('Response HTML contains edit modals:', hasEditModals);
                            console.log('Response HTML contains view modals:', hasViewModals);
                            
                            // Extract modals from raw HTML string
                            // Since modals are inside <tr> tags (invalid HTML), jQuery strips them
                            // We need to extract them manually from the string
                            const modalsToAppend = [];
                            let cleanHtml = response.html;
                            
                            // Find all modal IDs first
                            const idPattern = /id="(editSupplierModal\d+|viewSupplierModal\d+)"/g;
                            const modalIds = [];
                            let idMatch;
                            const htmlStr = response.html;
                            
                            while ((idMatch = idPattern.exec(htmlStr)) !== null) {
                                if (modalIds.indexOf(idMatch[1]) === -1) {
                                    modalIds.push(idMatch[1]);
                                }
                            }
                            
                            console.log('Found modal IDs in HTML string:', modalIds.length, modalIds);
                            
                            // For each modal ID, extract the complete modal HTML
                            modalIds.forEach(function(modalId) {
                                // Find the position of the modal ID
                                const idPos = htmlStr.indexOf('id="' + modalId + '"');
                                if (idPos === -1) return;
                                
                                // Find the opening <div> tag that contains this ID
                                // Go backwards to find the div with class="modal fade"
                                let divStart = htmlStr.lastIndexOf('<div', idPos);
                                let foundStart = false;
                                
                                // Keep going backwards until we find a div with "modal fade" class
                                while (divStart !== -1 && divStart >= 0) {
                                    const divTag = htmlStr.substring(divStart, htmlStr.indexOf('>', divStart) + 1);
                                    if (divTag.indexOf('modal') !== -1 && divTag.indexOf('fade') !== -1) {
                                        foundStart = true;
                                        break;
                                    }
                                    divStart = htmlStr.lastIndexOf('<div', divStart - 1);
                                }
                                
                                if (!foundStart) return;
                                
                                // Now find the matching closing </div> tags
                                // Count div depth to find the correct closing tag
                                let depth = 0;
                                let pos = divStart;
                                let modalEnd = -1;
                                
                                while (pos < htmlStr.length) {
                                    const openDiv = htmlStr.indexOf('<div', pos);
                                    const closeDiv = htmlStr.indexOf('</div>', pos);
                                    
                                    if (closeDiv === -1) break;
                                    
                                    if (openDiv !== -1 && openDiv < closeDiv) {
                                        depth++;
                                        pos = openDiv + 4;
                                    } else {
                                        depth--;
                                        pos = closeDiv + 6;
                                        if (depth === 0) {
                                            modalEnd = pos;
                                            break;
                                        }
                                    }
                                }
                                
                                if (modalEnd !== -1) {
                                    const modalHtml = htmlStr.substring(divStart, modalEnd);
                                    // Parse with jQuery
                                    const $modal = $(modalHtml.trim());
                                    if ($modal.length > 0 && $modal.attr('id') === modalId) {
                                        modalsToAppend.push($modal);
                                        // Remove from clean HTML
                                        cleanHtml = cleanHtml.replace(modalHtml, '');
                                        console.log('Successfully extracted modal:', modalId);
                                    }
                                }
                            });
                            
                            console.log('Total modals extracted:', modalsToAppend.length);
                            
                            // CRITICAL: Remove data-bs-toggle from buttons BEFORE inserting into DOM
                            // This prevents Bootstrap from auto-initializing when HTML is inserted
                            const cleanTempDiv = $('<div>').html(cleanHtml);
                            cleanTempDiv.find('[data-bs-toggle="modal"][data-bs-target^="#editSupplierModal"], [data-bs-toggle="modal"][data-bs-target^="#viewSupplierModal"]').each(function() {
                                $(this).removeAttr('data-bs-toggle');
                                $(this).addClass('manual-modal-trigger');
                            });
                            
                            // Update table body with rows (modals removed, data-bs-toggle already removed)
                            $('#suppliers-table tbody').removeClass('fade-out').html(cleanTempDiv.html()).addClass('fade-in');
                            
                            // Store modal IDs from extracted modals (reuse the modalIds array from above)
                            // modalIds already contains the IDs we found, but let's also verify from extracted modals
                            const extractedModalIds = [];
                            modalsToAppend.forEach(function($modal) {
                                const modalId = $modal.attr('id');
                                if (modalId) {
                                    extractedModalIds.push(modalId);
                                    console.log('Modal ID from extracted modal:', modalId);
                                }
                            });
                            
                            // Use the modalIds array that was already populated during extraction
                            
                            // Remove old modals from body (only if not currently shown and not in current set)
                            $('body').find('[id^="editSupplierModal"], [id^="viewSupplierModal"]').each(function() {
                                const modalId = $(this).attr('id');
                                // Only remove if it's not in the current set of modals
                                if (modalIds.indexOf(modalId) === -1 && extractedModalIds.indexOf(modalId) === -1 && !$(this).hasClass('show')) {
                                    // Dispose Bootstrap instance if exists
                                    try {
                                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                            const instance = bootstrap.Modal.getInstance(this);
                                            if (instance) {
                                                instance.dispose();
                                            }
                                        }
                                    } catch (e) {
                                        // Ignore errors
                                    }
                                    $(this).remove();
                                }
                            });
                            
                            // Append modals to body
                            modalsToAppend.forEach(function($modal) {
                                const modalId = $modal.attr('id');
                                
                                if (!modalId) {
                                    console.warn('Modal has no ID, skipping');
                                    return;
                                }
                                
                                // Check if modal already exists in body
                                const existingModal = $('body').find('#' + modalId);
                                if (existingModal.length > 0) {
                                    // Remove existing and replace with new one
                                    existingModal.remove();
                                }
                                
                                // Ensure modal has proper structure
                                if ($modal.find('.modal-dialog').length > 0 && $modal.find('.modal-content').length > 0) {
                                    // Ensure required attributes
                                    if (!$modal.attr('tabindex')) {
                                        $modal.attr('tabindex', '-1');
                                    }
                                    if (!$modal.attr('aria-hidden')) {
                                        $modal.attr('aria-hidden', 'true');
                                    }
                                    if (!$modal.attr('role')) {
                                        $modal.attr('role', 'dialog');
                                    }
                                    // Remove any existing Bootstrap instance data
                                    $modal.removeData('bs.modal');
                                    // Append to body
                                    $('body').append($modal);
                                    
                                    // Verify modal was appended
                                    const $verifyModal = $('body').find('#' + modalId);
                                    if ($verifyModal.length === 0) {
                                        console.error('Failed to append modal to body:', modalId);
                                    } else {
                                        console.log('Modal successfully appended to body:', modalId);
                                    }
                                } else {
                                    console.warn('Modal structure incomplete, skipping:', modalId);
                                }
                            });
                            setTimeout(function() {
                                $('#suppliers-table tbody').removeClass('fade-in');
                            }, 300);
                            
                            // Verify all modals are in body and ready (they were already appended above)
                            setTimeout(function() {
                                modalIds.forEach(function(modalId) {
                                    const $modalInBody = $('body').find('#' + modalId);
                                    if ($modalInBody.length === 0) {
                                        console.warn('Modal not found in body after append:', modalId);
                                        // Try to find it anywhere
                                        const $modalAnywhere = $('#' + modalId);
                                        if ($modalAnywhere.length > 0) {
                                            console.log('Modal found elsewhere, moving to body:', modalId);
                                            $modalAnywhere.detach().appendTo('body');
                                        }
                                    }
                                });
                            }, 100);
                            
                            // Update pagination with smooth fade-in
                            $('#pagination-wrapper').html(response.pagination).fadeIn(200);
                            
                            // Update count badge with smooth transition
                            let countHtml = '<strong style="font-size: 1.1rem;">' + formatNumber(response.filteredCount) + '</strong>';
                            if (response.filteredCount != response.totalCount) {
                                countHtml += '<span class="text-white-50" style="font-size: 0.95rem;">/ ' + formatNumber(response.totalCount) + '</span>';
                            }
                            $('#supplier-count').fadeOut(100, function() {
                                $(this).html(countHtml).fadeIn(100);
                            });
                            
                            // Restore Select2 values - Destroy and reinitialize for reliability
                            isRestoringValues = true;
                            
                            // Restore values after DOM is ready (reduced delay for smoother experience)
                            setTimeout(function() {
                                // Restore category filter
                                const categorySelect = $('#category-filter');
                                
                                // Store current value before destroying
                                const categoryValue = currentCategory || null;
                                
                                // Properly destroy and clean up Select2
                                if (categorySelect.hasClass('select2-hidden-accessible')) {
                                    try {
                                        categorySelect.select2('destroy');
                                    } catch(e) {
                                        // If destroy fails, force cleanup
                                        console.log('Select2 destroy had issues, forcing cleanup');
                                    }
                                }
                                
                                // Remove any leftover Select2 containers (safety cleanup)
                                categorySelect.nextAll('.select2-container').remove();
                                categorySelect.siblings('.select2-container').remove();
                                
                                // Remove select2 classes and attributes that might be left behind
                                categorySelect.removeClass('select2-hidden-accessible');
                                categorySelect.removeAttr('data-select2-id');
                                categorySelect.find('option').removeAttr('data-select2-id');
                                
                                // Set the value on the underlying select first
                                if (categoryValue) {
                                    // Check if option exists
                                    let optionExists = false;
                                    categorySelect.find('option').each(function() {
                                        if ($(this).val() === categoryValue) {
                                            optionExists = true;
                                            return false;
                                        }
                                    });
                                    if (optionExists) {
                                        categorySelect.val(categoryValue);
                                    } else {
                                        categorySelect.val(null);
                                    }
                                } else {
                                    categorySelect.val(null);
                                }
                                
                                // Small delay to ensure cleanup is complete before reinitializing
                                setTimeout(function() {
                                    // Reinitialize Select2 with the value already set (only if not already initialized)
                                    if (!categorySelect.hasClass('select2-hidden-accessible')) {
                                        categorySelect.select2({
                                            theme: "bootstrap-5",
                                            width: '100%',
                                            placeholder: categorySelect.data('placeholder') || 'Filter by category',
                                            allowClear: true,
                                            minimumResultsForSearch: 0,
                                        }).on('select2:open', function() {
                                            setTimeout(function() {
                                                $('.select2-search__field').focus();
                                            }, 80);
                                        });
                                    }
                                }, 30);
                                
                                // Restore type filter
                                const typeSelect = $('#type-filter');
                                
                                // Store current value before destroying
                                const typeValue = currentType || null;
                                
                                // Properly destroy and clean up Select2
                                if (typeSelect.hasClass('select2-hidden-accessible')) {
                                    try {
                                        typeSelect.select2('destroy');
                                    } catch(e) {
                                        // If destroy fails, force cleanup
                                        console.log('Select2 destroy had issues, forcing cleanup');
                                    }
                                }
                                
                                // Remove any leftover Select2 containers (safety cleanup)
                                typeSelect.nextAll('.select2-container').remove();
                                typeSelect.siblings('.select2-container').remove();
                                
                                // Remove select2 classes and attributes that might be left behind
                                typeSelect.removeClass('select2-hidden-accessible');
                                typeSelect.removeAttr('data-select2-id');
                                typeSelect.find('option').removeAttr('data-select2-id');
                                
                                // Set the value on the underlying select first
                                if (typeValue) {
                                    // Check if option exists
                                    let optionExists = false;
                                    typeSelect.find('option').each(function() {
                                        if ($(this).val() === typeValue) {
                                            optionExists = true;
                                            return false;
                                        }
                                    });
                                    if (optionExists) {
                                        typeSelect.val(typeValue);
                                    } else {
                                        typeSelect.val(null);
                                    }
                                } else {
                                    typeSelect.val(null);
                                }
                                
                                // Small delay to ensure cleanup is complete before reinitializing
                                setTimeout(function() {
                                    // Reinitialize Select2 with the value already set (only if not already initialized)
                                    if (!typeSelect.hasClass('select2-hidden-accessible')) {
                                        typeSelect.select2({
                                            theme: "bootstrap-5",
                                            width: '100%',
                                            placeholder: typeSelect.data('placeholder') || 'Filter by type',
                                            allowClear: true,
                                            minimumResultsForSearch: 0,
                                        });
                                    }
                                }, 30);
                                
                                // Restore search input value
                                $('#search-input').val(currentSearch);
                                
                                // Re-attach event handlers after reinitialization
                                attachFilterHandlers();
                                
                                // Reset flag after values are restored
                                setTimeout(function() {
                                    isRestoringValues = false;
                                }, 200);
                            }, 150);
                            
                            // Re-initialize Select2 in modals if needed
                            initSelect2();
                            
                            // Re-bind rating modal buttons
                            bindRatingButtons();
                            
                            // Modals are already extracted and moved to body above
                            // No additional action needed - Bootstrap will handle initialization via data attributes
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading suppliers:', xhr);
                        alert('Error loading suppliers. Please try again.');
                    },
                    complete: function() {
                        $('#loading-indicator').fadeOut(200);
                    }
                });
            }
            
            // Helper function to format numbers
            function formatNumber(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }
            
            // Flag to prevent infinite loop when restoring Select2 values
            let isRestoringValues = false;
            
            // Function to attach filter event handlers (needed after re-initialization)
            function attachFilterHandlers() {
                // Remove existing handlers to avoid duplicates
                $('#category-filter').off('change.select2-filter select2:select select2:clear');
                $('#type-filter').off('change.select2-filter');
                
                // Apply filters when category changes (including Select2 selection)
                $('#category-filter').on('change.select2-filter', function (e) {
                    // Skip if we're just restoring values after AJAX
                    if (!isRestoringValues) {
                        loadSuppliers(1); // Reset to page 1
                    }
                });

                // Also listen to Select2 select event to ensure it triggers when selecting from search
                $('#category-filter').on('select2:select', function (e) {
                    if (!isRestoringValues) {
                        // Wait a bit to ensure Select2 has finished updating its display
                        setTimeout(function() {
                            loadSuppliers(1); // Reset to page 1
                        }, 50);
                    }
                });

                // Handle clearing the filter
                $('#category-filter').on('select2:clear', function (e) {
                    if (!isRestoringValues) {
                        loadSuppliers(1); // Reset to page 1
                    }
                });

                // Apply filters when type changes
                $('#type-filter').on('change.select2-filter', function (e) {
                    // Skip if we're just restoring values after AJAX
                    if (!isRestoringValues) {
                        loadSuppliers(1); // Reset to page 1
                    }
                });
            }
            
            // Attach handlers initially
            attachFilterHandlers();

            // Apply filters when search input changes (with debounce and Enter key)
            let searchTimer;
            $('#search-input').on('keyup', function (e) {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimer);
                    loadSuppliers(1); // Reset to page 1
                    return;
                }
                
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    loadSuppliers(1); // Reset to page 1
                }, 800);
            });
            
            // Handle pagination clicks via AJAX
            $(document).on('click', '.pagination-wrapper .pagination a', function(e) {
                e.preventDefault();
                const url = $(this).attr('href');
                if (url) {
                    const urlObj = new URL(url);
                    const page = urlObj.searchParams.get('page') || 1;
                    loadSuppliers(page);
                    // Smooth scroll to top of table
                    $('html, body').animate({
                        scrollTop: $('#suppliers-table').offset().top - 100
                    }, 400, 'swing');
                }
            });
        });

        function openWhatsApp(number) {
            const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
            const baseURL = isMobile
                ? 'https://api.whatsapp.com/send?phone='
                : 'https://web.whatsapp.com/send?phone=';
            window.open(baseURL + number, '_blank');
        }

        // Function to bind rating modal buttons (needed after AJAX updates) - Global scope
        function bindRatingButtons() {
            $('.rate-btn').off('click').on('click', function () {
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
        }

        // Initial binding of rating modal buttons when document is ready
        $(document).ready(function() {
            bindRatingButtons();
            
            // Move initial page load modals to body (Bootstrap requirement)
            // Modals should not be inside table structure
            // Also remove data-bs-toggle from initial page load buttons
            setTimeout(function() {
                $('[id^="editSupplierModal"], [id^="viewSupplierModal"]').each(function() {
                    const $modal = $(this);
                    if ($modal.closest('tbody, table').length > 0) {
                        $modal.detach().appendTo('body');
                    }
                });
                
                // Remove data-bs-toggle from initial page load buttons
                $('[data-bs-toggle="modal"][data-bs-target^="#editSupplierModal"], [data-bs-toggle="modal"][data-bs-target^="#viewSupplierModal"]').each(function() {
                    $(this).removeAttr('data-bs-toggle');
                    $(this).addClass('manual-modal-trigger');
                });
            }, 50);
            
            // Handle modal button clicks manually (for buttons without data-bs-toggle)
            // This prevents Bootstrap auto-initialization errors
            $(document).on('click', '.manual-modal-trigger[data-bs-target^="#editSupplierModal"], .manual-modal-trigger[data-bs-target^="#viewSupplierModal"]', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const targetId = $(this).attr('data-bs-target');
                if (!targetId) return false;
                
                // Extract modal ID from target (e.g., "#editSupplierModal118" -> "editSupplierModal118")
                const modalId = targetId.replace('#', '');
                
                console.log('Looking for modal:', modalId, 'Target:', targetId);
                
                // Find the modal - search in body first (where they should be)
                let $modal = $('body').find('#' + modalId);
                console.log('Modal in body:', $modal.length);
                
                // If not found in body, search everywhere
                if ($modal.length === 0) {
                    $modal = $('#' + modalId);
                    console.log('Modal anywhere:', $modal.length);
                }
                
                // If still not found, try searching in table
                if ($modal.length === 0) {
                    $modal = $('#suppliers-table').find('#' + modalId);
                    console.log('Modal in table:', $modal.length);
                    if ($modal.length > 0) {
                        console.log('Found modal in table, moving to body:', targetId);
                    }
                }
                
                // If still not found, list all modals in body for debugging
                if ($modal.length === 0) {
                    const allModals = $('body').find('[id^="editSupplierModal"], [id^="viewSupplierModal"]');
                    console.warn('Modal not found:', modalId);
                    console.log('All modals in body:', allModals.length);
                    allModals.each(function() {
                        console.log('  - Modal ID:', $(this).attr('id'));
                    });
                    
                    // Wait a bit and try again (might be in process of being appended)
                    setTimeout(function() {
                        $modal = $('body').find('#' + modalId);
                        if ($modal.length === 0) {
                            $modal = $('#' + modalId);
                        }
                        if ($modal.length > 0) {
                            console.log('Modal found after retry, opening:', modalId);
                            // Retry opening modal by calling the handler again
                            const $btn = $('[data-bs-target="' + targetId + '"]').first();
                            if ($btn.length > 0) {
                                $btn.trigger('click');
                            }
                        } else {
                            console.error('Modal still not found after retry:', modalId);
                        }
                    }, 300);
                    return false;
                }
                
                // Ensure modal is in body (not in table)
                if ($modal.closest('tbody, table').length > 0) {
                    $modal.detach().appendTo('body');
                    // Re-query after moving
                    $modal = $(targetId);
                    if ($modal.length === 0) {
                        console.warn('Modal lost after moving:', targetId);
                        return false;
                    }
                }
                
                // Ensure modal has proper structure
                if ($modal.find('.modal-dialog').length === 0 || $modal.find('.modal-content').length === 0) {
                    console.warn('Modal structure incomplete:', targetId);
                    return false;
                }
                
                // Get the modal element
                const modalElement = $modal[0];
                if (!modalElement || !modalElement.parentNode) {
                    console.warn('Modal element not in DOM:', targetId);
                    return false;
                }
                
                // Initialize modal with proper error handling
                try {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        // Dispose any existing instance
                        const existingInstance = bootstrap.Modal.getInstance(modalElement);
                        if (existingInstance) {
                            try {
                                existingInstance.dispose();
                            } catch (disposeError) {
                                // Ignore dispose errors
                            }
                        }
                        
                        // Verify element is still valid
                        if (!modalElement || !modalElement.parentNode) {
                            console.warn('Modal element invalid before initialization:', targetId);
                            return false;
                        }
                        
                        // Create new instance with explicit options
                        const modalInstance = new bootstrap.Modal(modalElement, {
                            backdrop: true,
                            keyboard: true,
                            focus: true
                        });
                        
                        // Verify element is still valid before showing
                        if (!modalElement || !modalElement.parentNode) {
                            console.warn('Modal element invalid before showing:', targetId);
                            return false;
                        }
                        
                        // Show the modal
                        modalInstance.show();
                    } else {
                        // Fallback to jQuery
                        $modal.modal('show');
                    }
                } catch (error) {
                    console.error('Error initializing modal:', error, targetId, modalElement);
                    return false;
                }
                
                return false;
            });
            
        });

        document.body.style.zoom = '90%';

    </script>
@endsection
