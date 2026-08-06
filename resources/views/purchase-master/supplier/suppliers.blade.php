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

        .supplier-approval-toggle {
            cursor: pointer;
        }
        .supplier-approval-dropdown .supplier-approval-toggle .supplier-approval-dot {
            cursor: pointer;
        }
        .supplier-approval-dropdown .supplier-approval-toggle:hover .supplier-approval-dot {
            transform: scale(1.15);
        }
        .supplier-approval-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            padding: 0;
            border: 2px solid rgba(0, 0, 0, 0.12);
            flex-shrink: 0;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
            display: inline-block;
            vertical-align: middle;
        }
        .supplier-approval-menu .supplier-approval-pick {
            cursor: pointer;
        }
        .supplier-approval-menu .supplier-approval-pick:hover .supplier-approval-dot {
            transform: scale(1.1);
        }
        .supplier-approval-pick:disabled {
            opacity: 0.55;
            pointer-events: none;
        }
        .supplier-approval-dot--red { background-color: #dc3545; }
        .supplier-approval-dot--green { background-color: #198754; }
        .supplier-approval-dot--yellow { background-color: #ffc107; }

        .supplier-company-toggle {
            cursor: pointer;
            line-height: 1;
        }
        .supplier-company-toggle:hover .supplier-approval-dot {
            transform: scale(1.2);
        }

        #suppliers-table th.parents-col,
        #suppliers-table td.parents-col {
            display: none;
        }

        /* Uniform data font size + center headers and cell content */
        #suppliers-table {
            font-size: 0.875rem;
        }
        #suppliers-table thead th,
        #suppliers-table tbody td {
            font-size: 0.875rem !important;
            line-height: 1.35;
            text-align: center !important;
            vertical-align: middle !important;
        }
        #suppliers-table tbody td > .d-flex {
            justify-content: center !important;
        }
        #suppliers-table tbody td .dropdown,
        #suppliers-table tbody td .input-group {
            display: inline-flex !important;
            justify-content: center;
        }
        #suppliers-table tbody td,
        #suppliers-table tbody td .btn,
        #suppliers-table tbody td .badge,
        #suppliers-table tbody td .fw-bold,
        #suppliers-table tbody td .fw-semibold,
        #suppliers-table tbody td span,
        #suppliers-table tbody td a {
            font-size: 0.875rem !important;
        }
        #suppliers-table tbody td .rate-btn {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            text-decoration: none !important;
            line-height: 1;
        }
        #suppliers-table tbody td .rate-btn-icon {
            font-size: 0.9rem !important;
            margin: 0 !important;
            vertical-align: middle;
        }

        #suppliers-table .supplier-select-col {
            width: 36px;
        }
        #suppliers-table tr.supplier-row-selected {
            background-color: #fff8e1 !important;
        }
        .supplier-bulk-banner {
            display: none;
            background: #fff3cd;
            border: 1px solid #ffe69c;
            color: #664d03;
            border-radius: 6px;
            padding: 6px 12px;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }
        .supplier-bulk-banner .supplier-bulk-clear {
            color: #664d03;
            text-decoration: underline;
            cursor: pointer;
            margin-left: 8px;
        }
        .approval-form-dots input[type="radio"]:checked + span {
            box-shadow: 0 0 0 2px #495057;
            border-radius: 50%;
        }
        .approval-form-dots label:has(input[type="radio"]:checked) {
            font-weight: 600;
        }

        /* Ali / 1688 / QQ / Email / WhatsApp / Bank / WeChat — green if data, red if missing */
        .supplier-data-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            vertical-align: middle;
        }
        .supplier-data-dot--ok {
            background-color: #198754;
        }
        .supplier-data-dot--missing {
            background-color: #dc3545;
        }
        #suppliers-table tbody td .supplier-bank-open-btn {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            line-height: 1;
        }

        .rating-edit-dot {
            line-height: 1;
            min-width: 0;
            vertical-align: middle;
        }
        .rating-edit-dot-inner {
            width: 8px;
            height: 8px;
            background-color: #6c757d !important;
            transition: background-color 0.15s ease, transform 0.15s ease;
        }
        .rating-edit-dot:hover .rating-edit-dot-inner {
            background-color: #495057 !important;
            transform: scale(1.15);
        }

        /* Sortable column headers */
        #suppliers-table thead th.sortable {
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }
        #suppliers-table thead th.sortable:hover {
            background-color: #eef0f7;
        }
        #suppliers-table thead th.sortable .sort-icon {
            display: inline-block;
            margin-left: 4px;
            opacity: 0.45;
            font-size: 14px;
            vertical-align: middle;
        }
        #suppliers-table thead th.sortable.active {
            color: #4f46e5;
        }
        #suppliers-table thead th.sortable.active .sort-icon {
            opacity: 1;
            color: #4f46e5;
        }

        /* Remove black backdrop completely */
        .modal-backdrop {
            display: none !important;
        }

        .modal-backdrop.show {
            display: none !important;
        }

        .modal.show {
            background-color: transparent !important;
        }

        /* Actions column — no outline/border on soft edit/delete buttons */
        #suppliers-table .btn-soft-primary,
        #suppliers-table .btn-soft-danger {
            border: none !important;
            box-shadow: none !important;
            outline: none !important;
        }
        #suppliers-table .btn-soft-primary:focus,
        #suppliers-table .btn-soft-danger:focus,
        #suppliers-table .btn-soft-primary:active,
        #suppliers-table .btn-soft-danger:active {
            border: none !important;
            box-shadow: none !important;
            outline: none !important;
        }

        /* One-line toolbar: title + badge + filters + actions */
        .supplier-toolbar .select2-container {
            width: 100% !important;
        }
        .supplier-toolbar .select2-container .select2-selection--single {
            height: 31px !important;
            min-height: 31px !important;
        }
        .supplier-toolbar .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 29px !important;
            font-size: 0.85rem;
        }
        .supplier-toolbar .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 29px !important;
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
                    {{-- Title + badge + filters + actions — one line L→R --}}
                    <div class="supplier-toolbar d-flex flex-nowrap align-items-center gap-2 mb-3">
                        <h4 class="card-title mb-0 text-nowrap flex-shrink-0">Suppliers</h4>
                        <span class="badge bg-primary rounded-pill px-2 py-1 flex-shrink-0" id="supplier-count" style="font-size: 0.85rem; font-weight: 600;">
                            <strong>{{ number_format($filteredCount) }}</strong>
                            @if($filteredCount != $totalCount)
                                <span class="text-white-50">/ {{ number_format($totalCount) }}</span>
                            @endif
                        </span>

                        <div class="flex-shrink-0" style="min-width: 140px; max-width: 180px; width: 160px;">
                            <select class="form-select form-select-sm select2" id="category-filter" name="category" data-placeholder="Category" aria-label="Category">
                                <option value="">All Categories</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->name }}" {{ request('category') == $category->name ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex-shrink-0" style="min-width: 120px; max-width: 150px; width: 140px;">
                            @php
                                $types = ['Supplier','Forwarders', 'Photographer'];
                            @endphp
                            <select class="form-select form-select-sm select2" id="type-filter" name="type" data-placeholder="Type" aria-label="Type">
                                <option value="">All Types</option>
                                @foreach($types as $type)
                                    <option value="{{ $type }}" {{ request('type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="input-group input-group-sm flex-grow-1" style="min-width: 140px; max-width: 260px;">
                            <span class="input-group-text py-0"><i class="mdi mdi-magnify"></i></span>
                            <input type="text" id="search-input" name="search" class="form-control" placeholder="Search…" value="{{ request('search') }}" aria-label="Search suppliers">
                        </div>

                        <div class="d-flex gap-1 align-items-center flex-shrink-0 ms-auto">
                            @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'suppliers'])
                            <button type="button" class="btn btn-primary btn-sm text-nowrap" data-bs-toggle="modal"
                                data-bs-target="#addSupplierModal">
                                <i class="mdi mdi-plus me-1"></i>Add
                            </button>
                            <button type="button" class="btn btn-success btn-sm text-nowrap" data-bs-toggle="modal"
                                data-bs-target="#bulkImportModal">
                                <i class="mdi mdi-file-import me-1"></i>Import
                            </button>
                            <a href="{{ route('supplier.export') }}" id="export-suppliers-btn" class="btn btn-outline-success btn-sm text-nowrap">
                                <i class="mdi mdi-file-export me-1"></i>Export
                            </a>
                        </div>
                    </div>
                </form>

                <div class="supplier-bulk-banner" id="supplier-bulk-banner" role="status" aria-live="polite">
                    <i class="mdi mdi-information-outline"></i>
                    <span id="supplier-bulk-banner-text"></span>
                    <span class="supplier-bulk-clear" id="supplier-bulk-clear">Clear selection</span>
                </div>
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
                                <th class="text-center supplier-select-col" style="width: 36px;" title="Select rows to bulk-apply edits">
                                    <input type="checkbox" class="form-check-input supplier-select-all" aria-label="Select all suppliers on this page">
                                </th>
                                <th class="sortable" data-sort-key="category">Category <span class="sort-icon"><i class="mdi mdi-unfold-more-horizontal"></i></span></th>
                                <th class="text-center" title="Total suppliers in each of this row's categories (category-wide count)">Suppliers</th>
                                <th class="sortable" data-sort-key="name">Name <span class="sort-icon"><i class="mdi mdi-unfold-more-horizontal"></i></span></th>
                                <th class="sortable text-center" data-sort-key="approval" title="Approved">Appr <span class="sort-icon"><i class="mdi mdi-unfold-more-horizontal"></i></span></th>
                                <th class="sortable" data-sort-key="company">Company <span class="sort-icon"><i class="mdi mdi-unfold-more-horizontal"></i></span></th>
                                <th class="sortable parents-col" data-sort-key="parent">Product <span class="sort-icon"><i class="mdi mdi-unfold-more-horizontal"></i></span></th>
                                <th class="sortable" data-sort-key="zone">Zone <span class="sort-icon"><i class="mdi mdi-unfold-more-horizontal"></i></span></th>
                                <th class="sortable" data-sort-key="phone">Phone <span class="sort-icon"><i class="mdi mdi-unfold-more-horizontal"></i></span></th>
                                <th class="sortable" data-sort-key="rating">Rating <span class="sort-icon"><i class="mdi mdi-unfold-more-horizontal"></i></span></th>
                                <th class="sortable" data-sort-key="alibaba" title="Alibaba">Ali <span class="sort-icon"><i class="mdi mdi-unfold-more-horizontal"></i></span></th>
                                <th class="sortable" data-sort-key="link_1688">1688 <span class="sort-icon"><i class="mdi mdi-unfold-more-horizontal"></i></span></th>
                                <th class="sortable" data-sort-key="qq">QQ <span class="sort-icon"><i class="mdi mdi-unfold-more-horizontal"></i></span></th>
                                <th class="sortable" data-sort-key="email">Email <span class="sort-icon"><i class="mdi mdi-unfold-more-horizontal"></i></span></th>
                                <th class="sortable" data-sort-key="whatsapp">WhatsApp <span class="sort-icon"><i class="mdi mdi-unfold-more-horizontal"></i></span></th>
                                <th class="text-center">Bank</th>
                                <th class="text-center" title="Latest Advance % from purchase contracts">Advance</th>
                                <th class="sortable" data-sort-key="wechat">WeChat <span class="sort-icon"><i class="mdi mdi-unfold-more-horizontal"></i></span></th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @include('purchase-master.supplier.partials.rows', [
                                'suppliers' => $suppliers,
                                'categories' => $categories,
                            ])
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
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable shadow-none">
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
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Alias</label>
                            <input type="text" name="alias" class="form-control" placeholder="Alias">
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
                                    <span class="d-inline-block supplier-approval-dot supplier-approval-dot--red border-0" title="disqualified"></span>
                                </label>
                                <label class="mb-0 cursor-pointer d-inline-flex align-items-center" title="Qualified">
                                    <input type="radio" name="approval_status" value="green" class="d-none">
                                    <span class="d-inline-block supplier-approval-dot supplier-approval-dot--green border-0" title="Qualified"></span>
                                </label>
                                <label class="mb-0 cursor-pointer d-inline-flex align-items-center" title="Explore">
                                    <input type="radio" name="approval_status" value="yellow" class="d-none">
                                    <span class="d-inline-block supplier-approval-dot supplier-approval-dot--yellow border-0" title="Explore"></span>
                                </label>
                            </div>
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
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">1688</label>
                            <input type="text" name="link_1688" class="form-control" placeholder="1688 Profile / URL">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">QQ</label>
                            <input type="text" name="qq" class="form-control" placeholder="QQ ID">
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
            <form method="POST" action="{{ route('supplier.rating.save') }}" class="needs-validation" novalidate id="rating-modal-form">
                @csrf
                <input type="hidden" id="modal-rating-id" name="rating_id" value="">
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
                                    <input type="number" id="score_{{ $i }}" name="criteria[{{ $i }}][score]" class="form-control form-control-sm text-center" min="0" max="10" step="1" placeholder="0-10">
                                    <input type="hidden" name="criteria[{{ $i }}][label]" value="{{ $item['label'] }}">
                                    <input type="hidden" name="criteria[{{ $i }}][weight]" value="{{ $item['weight'] }}">
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <!-- Submit Button -->
                    <div class="d-flex justify-content-end mt-4">
                        <button class="btn btn-primary" type="submit" id="rating-submit-btn">
                            <i class="mdi mdi-content-save me-1"></i> Submit Rating
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="supplierCompanyModal" tabindex="-1" aria-labelledby="supplierCompanyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supplierCompanyModalLabel">
                    <span class="supplier-approval-dot supplier-approval-dot--green me-2" style="vertical-align: middle;"></span>
                    Company
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2 text-muted small" id="supplierCompanyModalSupplier"></div>
                <div class="fs-5 fw-semibold text-break" id="supplierCompanyModalBody"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Supplier Bank Details modal — multiple accounts; edit gated by email --}}
<div class="modal fade" id="supplierBankModal" tabindex="-1" aria-labelledby="supplierBankModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="supplierBankModalLabel">
                    <i class="mdi mdi-bank me-1"></i>Bank Details — <span id="supplierBankModalSupplierName">—</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="supplierBankSupplierId" value="">
                <input type="hidden" id="supplierBankAccountId" value="">

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="supplierBankHistoryBtn">
                        <i class="mdi mdi-history me-1"></i>History
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary d-none" id="supplierBankEditBtn">
                        <i class="mdi mdi-pencil me-1"></i>Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success d-none" id="supplierBankAddBtn">
                        <i class="mdi mdi-plus me-1"></i>Add account
                    </button>
                    <span class="text-muted small align-self-center" id="supplierBankEditHint"></span>
                </div>

                <div id="supplierBankAccountsPanel">
                    <div class="mb-3" id="supplierBankAccountsList">
                        <div class="text-muted small">Loading…</div>
                    </div>

                    <form id="supplierBankForm" class="row g-2 border rounded-3 p-3 bg-light">
                        <div class="col-12">
                            <div class="fw-semibold mb-1" id="supplierBankFormTitle">Account details</div>
                            <small class="text-muted">Max 30 characters per field.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Supplier name</label>
                            <input type="text" name="supplier_name" maxlength="30" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Nick name</label>
                            <input type="text" name="nick_name" maxlength="30" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Benificiary</label>
                            <input type="text" name="company_name" maxlength="30" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Swift</label>
                            <input type="text" name="swift" maxlength="30" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Address</label>
                            <input type="text" name="address" maxlength="30" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">City</label>
                            <input type="text" name="city" maxlength="30" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Province</label>
                            <input type="text" name="province" maxlength="30" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Country</label>
                            <input type="text" name="country" maxlength="30" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-0">Account number</label>
                            <input type="text" name="account_number" maxlength="30" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-12 d-flex gap-2 mt-2 d-none" id="supplierBankFormActions">
                            <button type="submit" class="btn btn-primary btn-sm" id="supplierBankSaveBtn">
                                <i class="mdi mdi-content-save me-1"></i>Save
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm d-none" id="supplierBankDeleteBtn">
                                <i class="mdi mdi-delete me-1"></i>Delete
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" id="supplierBankCancelEditBtn">Cancel</button>
                        </div>
                    </form>
                </div>

                <div id="supplierBankHistoryPanel" class="d-none">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Change history</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="supplierBankBackFromHistoryBtn">Back</button>
                    </div>
                    <div id="supplierBankHistoryBody" class="table-responsive">
                        <div class="text-muted small">Loading…</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
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
                    
                    var select2Options = {
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: function () {
                            return $select.data('placeholder') || 'Select...';
                        },
                        dropdownParent: $modal.length ? $modal : $(document.body),
                        allowClear: false
                    };

                    // Category select: allow typing a brand-new category and creating it on the fly.
                    // The actual server-side creation is handled by the global "select2:select" handler below.
                    if (isCategorySelect) {
                        select2Options.tags = true;
                        select2Options.createTag = function (params) {
                            var term = $.trim(params.term);
                            if (term === '') {
                                return null;
                            }
                            // Don't offer "new" if a real option with this exact name already exists.
                            var exists = false;
                            $select.find('option').each(function () {
                                if ($.trim($(this).text()).toLowerCase() === term.toLowerCase()) {
                                    exists = true;
                                    return false;
                                }
                            });
                            if (exists) {
                                return null;
                            }
                            return {
                                id: '__new__:' + term,
                                text: term,
                                newTag: true,
                                newName: term
                            };
                        };
                        select2Options.insertTag = function (data, tag) {
                            data.push(tag);
                        };
                    }

                    $select.select2(select2Options);
                    
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
            var supplierApprovalBaseUrl = @json(rtrim(url('/supplier'), '/'));

            var approvalTitles = { red: 'disqualified', yellow: 'Explore', green: 'Qualified' };

            $(document).on('click', '.supplier-approval-pick', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $item = $(this);
                var $dropdown = $item.closest('.supplier-approval-dropdown');
                var supplierId = $dropdown.data('supplier-id');
                var status = $item.data('status');
                if (!supplierId || !status) {
                    return;
                }
                var url = supplierApprovalBaseUrl + '/' + supplierId + '/approval-status';
                $dropdown.find('.supplier-approval-pick').prop('disabled', true);
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        approval_status: status
                    },
                    success: function () {
                        var $toggle = $dropdown.find('.supplier-approval-toggle');
                        var $dot = $toggle.find('.supplier-approval-dot');
                        $dot.removeClass('supplier-approval-dot--red supplier-approval-dot--green supplier-approval-dot--yellow');
                        $dot.addClass('supplier-approval-dot--' + status);
                        $toggle.attr('data-current-status', status);
                        $toggle.attr('title', approvalTitles[status] || '');
                    },
                    error: function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Could not save approval status.';
                        alert(msg);
                    },
                    complete: function () {
                        $dropdown.find('.supplier-approval-pick').prop('disabled', false);
                    }
                });
            });

            // Company green-dot -> show full company name in a modal.
            // The Company cell renders a compact green dot; clicking it pops the
            // full (untruncated) company name so long names stay readable.
            $(document).on('click', '.supplier-company-toggle', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $btn = $(this);
                var company = $btn.data('company') || '';
                var supplierName = $btn.data('supplier-name') || '';
                $('#supplierCompanyModalBody').text(company);
                $('#supplierCompanyModalSupplier').text(supplierName ? 'Supplier: ' + supplierName : '');
                var modalEl = document.getElementById('supplierCompanyModal');
                if (modalEl) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });

            // -----------------------------------------------------------------
            // Bulk-select + bulk-edit
            //
            // The first column renders a checkbox per row; the header has a
            // "select all" checkbox. When the user opens any row's Edit modal
            // and submits it, the fields they actually changed are propagated
            // to every other checked row as well (one PUT per supplier).
            // Untouched fields are left alone on the other suppliers, so
            // bulk-applying a single field (e.g. Zone) is non-destructive.
            // -----------------------------------------------------------------
            var $bulkBanner = $('#supplier-bulk-banner');
            var $bulkBannerText = $('#supplier-bulk-banner-text');

            function getSelectedSupplierIds() {
                return $('#suppliers-table tbody .supplier-row-select:checked').map(function () {
                    return String($(this).val());
                }).get();
            }

            function refreshBulkBanner() {
                var ids = getSelectedSupplierIds();
                if (ids.length === 0) {
                    $bulkBanner.hide();
                } else {
                    $bulkBannerText.text(ids.length + ' supplier' + (ids.length === 1 ? '' : 's') + ' selected. Edit any one to bulk-apply your changes.');
                    $bulkBanner.show();
                }
                // Sync the "select all" indeterminate / checked state.
                var $rowBoxes = $('#suppliers-table tbody .supplier-row-select');
                var total = $rowBoxes.length;
                var checked = ids.length;
                var $all = $('.supplier-select-all');
                if (total === 0) {
                    $all.prop('checked', false).prop('indeterminate', false);
                } else if (checked === 0) {
                    $all.prop('checked', false).prop('indeterminate', false);
                } else if (checked === total) {
                    $all.prop('checked', true).prop('indeterminate', false);
                } else {
                    $all.prop('checked', false).prop('indeterminate', true);
                }
            }

            $(document).on('change', '.supplier-row-select', function () {
                var checked = $(this).prop('checked');
                $(this).closest('tr').toggleClass('supplier-row-selected', checked);
                refreshBulkBanner();
            });

            $(document).on('change', '.supplier-select-all', function () {
                var check = $(this).prop('checked');
                $('#suppliers-table tbody .supplier-row-select').each(function () {
                    $(this).prop('checked', check);
                    $(this).closest('tr').toggleClass('supplier-row-selected', check);
                });
                refreshBulkBanner();
            });

            $(document).on('click', '#supplier-bulk-clear', function () {
                $('#suppliers-table tbody .supplier-row-select').prop('checked', false)
                    .closest('tr').removeClass('supplier-row-selected');
                refreshBulkBanner();
            });

            // Snapshot each Edit Supplier modal's form values when it opens, so
            // we can compute exactly which fields the user touched.
            function serializeFormToDict($form) {
                var dict = {};
                $form.serializeArray().forEach(function (item) {
                    if (Object.prototype.hasOwnProperty.call(dict, item.name)) {
                        if (!Array.isArray(dict[item.name])) {
                            dict[item.name] = [dict[item.name]];
                        }
                        dict[item.name].push(item.value);
                    } else {
                        dict[item.name] = item.value;
                    }
                });
                return dict;
            }

            $(document).on('show.bs.modal', '.modal[id^="editSupplierModal"]', function () {
                var $form = $(this).find('form').first();
                if (!$form.length) return;
                $form.data('initialFormSnapshot', serializeFormToDict($form));
            });

            // Field names that should never be bulk-overwritten on other
            // suppliers (these are per-row identity fields).
            var BULK_EDIT_BLOCKED_FIELDS = ['_token', 'supplier_id', 'name'];

            // Pretty labels for the confirmation dialog so users don't see
            // raw form field names like "category_id[]" / "bank_details".
            var BULK_EDIT_FIELD_LABELS = {
                'type': 'Type',
                'category_id[]': 'Category',
                'company': 'Company',
                'alias': 'Alias',
                'country_code': 'Country Code',
                'phone': 'Phone',
                'city': 'City',
                'zone': 'Zone',
                'approval_status': 'Approved',
                'email': 'Email',
                'whatsapp': 'WhatsApp',
                'wechat': 'WeChat',
                'alibaba': 'Alibaba',
                'link_1688': '1688',
                'qq': 'QQ',
                'website': 'Website',
                'others': 'Others',
                'address': 'Address',
                'bank_details': 'Bank Details',
                'parent': 'Product / Parent'
            };

            function computeDiff(initial, current) {
                var diff = {};
                var keys = new Set();
                Object.keys(initial || {}).forEach(function (k) { keys.add(k); });
                Object.keys(current || {}).forEach(function (k) { keys.add(k); });
                keys.forEach(function (k) {
                    if (BULK_EDIT_BLOCKED_FIELDS.indexOf(k) !== -1) return;
                    var a = (initial || {})[k];
                    var b = (current || {})[k];
                    if (JSON.stringify(a === undefined ? null : a) !== JSON.stringify(b === undefined ? null : b)) {
                        diff[k] = b;
                    }
                });
                return diff;
            }

            // Build a fresh FormData for `$targetForm` containing all of its
            // existing values, with the bulk diff applied as overrides. We
            // build the payload ourselves (instead of mutating the target
            // modal's DOM) so Select2 / radio quirks can't drop fields.
            function buildPayloadForTarget($targetForm, diff) {
                var fd = new FormData();
                var overrideKeys = new Set(Object.keys(diff));
                $targetForm.serializeArray().forEach(function (item) {
                    if (!overrideKeys.has(item.name)) {
                        fd.append(item.name, item.value);
                    }
                });
                Object.keys(diff).forEach(function (name) {
                    var value = diff[name];
                    if (Array.isArray(value)) {
                        value.forEach(function (v) { fd.append(name, v == null ? '' : v); });
                    } else {
                        fd.append(name, value == null ? '' : value);
                    }
                });
                return fd;
            }

            function postSupplierForm($form) {
                return $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: $form.serialize(),
                    dataType: 'json',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
            }

            function postSupplierBulkPayload($targetForm, diff) {
                return $.ajax({
                    url: $targetForm.attr('action'),
                    method: 'POST',
                    data: buildPayloadForTarget($targetForm, diff),
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
            }

            $(document).on('submit', 'form[id^="editSupplierForm"]', function (e) {
                var $form = $(this);
                var sourceId = String($form.find('input[name="supplier_id"]').val() || '');
                var selectedIds = getSelectedSupplierIds();

                // No checkboxes ticked -> behave exactly like single edit.
                if (selectedIds.length === 0) {
                    return;
                }

                e.preventDefault();

                var initial = $form.data('initialFormSnapshot') || {};
                var current = serializeFormToDict($form);
                var diff = computeDiff(initial, current);
                var diffKeys = Object.keys(diff);

                if (diffKeys.length === 0) {
                    alert('No fields were changed — nothing to bulk-apply.');
                    return;
                }

                // Targets = the checked rows. The supplier whose modal is open
                // is NOT automatically a target unless it's also checked; this
                // matches the row-selection metaphor users expect.
                var targetIds = selectedIds.slice();

                var prettyKeys = diffKeys.map(function (k) {
                    return BULK_EDIT_FIELD_LABELS[k] || k;
                });
                var confirmMsg = 'Apply the following changed field(s) to ' + targetIds.length + ' selected supplier(s)?\n\n' +
                    '• ' + prettyKeys.join('\n• ');
                if (!window.confirm(confirmMsg)) {
                    return;
                }

                var $submitBtn = $form.find('button[type="submit"]');
                var origLabel = $submitBtn.html();
                $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving ' + targetIds.length + '...');

                var jobs = targetIds.map(function (id) {
                    if (id === sourceId) {
                        // Source modal already has the user's edits applied;
                        // post it as-is so all fields they touched persist.
                        return postSupplierForm($form);
                    }
                    var $targetForm = $('#editSupplierForm' + id);
                    if (!$targetForm.length) {
                        // Row was deselected client-side but form not present.
                        return $.Deferred().resolve().promise();
                    }
                    return postSupplierBulkPayload($targetForm, diff);
                });

                // Use $.when with always() so we wait for ALL requests to settle
                // (including failures) before deciding what to show.
                var failures = [];
                var wrapped = jobs.map(function (job, idx) {
                    var d = $.Deferred();
                    job.done(function () { d.resolve(); })
                       .fail(function (xhr) {
                           failures.push({ id: targetIds[idx], xhr: xhr });
                           d.resolve();
                       });
                    return d.promise();
                });

                $.when.apply($, wrapped).then(function () {
                    if (failures.length === 0) {
                        window.location.reload();
                        return;
                    }
                    $submitBtn.prop('disabled', false).html(origLabel);
                    var failIds = failures.map(function (f) { return '#' + f.id; }).join(', ');
                    var first = failures[0].xhr;
                    var firstMsg = (first && first.responseJSON && first.responseJSON.message)
                        ? first.responseJSON.message
                        : 'Update failed.';
                    alert('Failed to update ' + failures.length + ' supplier(s) (' + failIds + ').\n\n' + firstMsg);
                });
            });

            // Reflect any pre-checked rows on page load.
            refreshBulkBanner();

            // Initialize Select2 on page load
            initSelect2();

            // ---------------------------------------------------------------------
            // Inline category creation from the Supplier Add / Edit modals.
            // When the user types a category that doesn't exist and selects the
            // newly offered entry, we POST to category.create, then swap the
            // temporary "__new__:<name>" tag for the real DB ID so the supplier
            // form submits with a valid integer category_id.
            // ---------------------------------------------------------------------
            window.pendingCategoryCreations = window.pendingCategoryCreations || 0;

            function setCategorySubmitButtonsDisabled(disabled) {
                $('form#addSupplierForm, form[id^="editSupplierForm"]').each(function () {
                    $(this).find('button[type="submit"]').prop('disabled', disabled);
                });
            }

            $(document).on('select2:select', 'select[name="category_id[]"]', function (e) {
                var data = e.params && e.params.data ? e.params.data : null;
                if (!data || !data.newTag) {
                    return;
                }

                var $select = $(this);
                var tempId = data.id;
                var newCategoryName = (data.newName || '').trim();
                if (!newCategoryName) {
                    return;
                }

                window.pendingCategoryCreations++;
                setCategorySubmitButtonsDisabled(true);

                $.ajax({
                    url: '{{ route('category.create') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        category_name: newCategoryName,
                        status: 'active'
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response && response.success && response.category && response.category.id) {
                            var newId = String(response.category.id);
                            var newName = response.category.name || newCategoryName;

                            // Remove the temporary "__new__:..." option / value from this select.
                            var selected = ($select.val() || []).map(String).filter(function (v) {
                                return v !== tempId;
                            });
                            $select.find('option[value="' + tempId + '"]').remove();

                            // Append the real option (if not already present) and select it.
                            if ($select.find('option[value="' + newId + '"]').length === 0) {
                                $select.append(new Option(newName, newId, false, false));
                            }
                            if (selected.indexOf(newId) === -1) {
                                selected.push(newId);
                            }
                            $select.val(selected).trigger('change');

                            $('select[name="category_id[]"]').not($select).each(function () {
                                var $other = $(this);
                                if ($other.find('option[value="' + newId + '"]').length === 0) {
                                    $other.append(new Option(newName, newId, false, false));
                                }
                            });

                            // Add it to the page's category filter (which uses name as the value).
                            var $filter = $('#category-filter');
                            if ($filter.length && $filter.find('option[value="' + newName + '"]').length === 0) {
                                $filter.append(new Option(newName, newName, false, false));
                            }
                        } else {
                            removeTempCategory($select, tempId);
                            alert((response && response.message) || 'Failed to create category.');
                        }
                    },
                    error: function (xhr) {
                        removeTempCategory($select, tempId);
                        var msg = 'Failed to create category.';
                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.errors) {
                                msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                            } else if (xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            }
                        }
                        alert(msg);
                    },
                    complete: function () {
                        window.pendingCategoryCreations = Math.max(0, window.pendingCategoryCreations - 1);
                        if (window.pendingCategoryCreations === 0) {
                            setCategorySubmitButtonsDisabled(false);
                        }
                    }
                });
            });

            function removeTempCategory($select, tempId) {
                var selected = ($select.val() || []).filter(function (v) {
                    return String(v) !== String(tempId);
                });
                $select.find('option[value="' + tempId + '"]').remove();
                $select.val(selected).trigger('change');
            }
            
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

                // If a new category is still being created on the server, hold the submit
                // until it finishes and the temporary value has been swapped for a real ID.
                if (window.pendingCategoryCreations && window.pendingCategoryCreations > 0) {
                    e.preventDefault();
                    alert('A new category is still being created. Please wait a moment and try again.');
                    return false;
                }

                // Safety net: never submit unresolved "__new__:..." placeholders.
                var unresolved = finalCategories.filter(function (v) {
                    return String(v).indexOf('__new__:') === 0;
                });
                if (unresolved.length > 0) {
                    e.preventDefault();
                    alert('A new category was not created yet. Please wait a moment and try again.');
                    return false;
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

            // Column sorting state (server-side; round-tripped through loadSuppliers).
            // Initialised from the request that rendered this page so deep-links / refreshes work.
            var currentSort = {
                key: @json($sortKey ?? ''),
                direction: @json(($direction ?? 'asc') === 'desc' ? 'desc' : 'asc')
            };

            function updateSortIcons() {
                $('#suppliers-table thead th.sortable').each(function () {
                    var $th = $(this);
                    var key = $th.attr('data-sort-key');
                    var $icon = $th.find('.sort-icon i');
                    $th.removeClass('active');
                    if (key && key === currentSort.key) {
                        $th.addClass('active');
                        $icon.attr('class', currentSort.direction === 'desc' ? 'mdi mdi-arrow-down' : 'mdi mdi-arrow-up');
                    } else {
                        $icon.attr('class', 'mdi mdi-unfold-more-horizontal');
                    }
                });
            }
            updateSortIcons();

            // Cycle: unsorted -> asc -> desc -> unsorted (per column)
            $(document).on('click', '#suppliers-table thead th.sortable', function () {
                var key = $(this).attr('data-sort-key');
                if (!key) return;
                if (currentSort.key === key) {
                    if (currentSort.direction === 'asc') {
                        currentSort.direction = 'desc';
                    } else {
                        currentSort.key = '';
                        currentSort.direction = 'asc';
                    }
                } else {
                    currentSort.key = key;
                    currentSort.direction = 'asc';
                }
                updateSortIcons();
                loadSuppliers(1);
            });

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
                if (currentSort.key) {
                    params.set('sort', currentSort.key);
                    params.set('direction', currentSort.direction);
                }
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
                            let countHtml = '<strong>' + formatNumber(response.filteredCount) + '</strong>';
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

            // Keep the Export button in sync with the active filters
            $('#export-suppliers-btn').on('click', function (e) {
                const params = new URLSearchParams();
                const category = $('#category-filter').val() || '';
                const type = $('#type-filter').val() || '';
                const search = ($('#search-input').val() || '').trim();
                if (category) params.set('category', category);
                if (type) params.set('type', type);
                if (search) params.set('search', search);
                const base = '{{ route('supplier.export') }}';
                $(this).attr('href', base + (params.toString() ? '?' + params.toString() : ''));
            });

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

        var RATING_CRITERIA_LABELS = [
            'Product Quality', 'Timely Delivery', 'Document Accuracy', 'Pricing',
            'Packaging & Labeling', 'Item Match (PO)', 'Commercial Terms', 'Responsiveness',
            'Issue Resolution', 'Reliability'
        ];

        function clearRatingModalForm() {
            $('#modal-rating-id').val('');
            RATING_CRITERIA_LABELS.forEach(function (_, i) {
                $('#score_' + i).val('');
            });
            $('#evaluation_date').val(new Date().toISOString().slice(0, 10));
            $('#ratingModalLabel').text('🌟 Rate Supplier Performance');
            $('#rating-submit-btn').html('<i class="mdi mdi-content-save me-1"></i> Submit Rating');
        }

        function fillRatingModalForm(payload) {
            payload = payload || {};
            $('#modal-rating-id').val(payload.id ? String(payload.id) : '');
            $('#evaluation_date').val(payload.evaluation_date || new Date().toISOString().slice(0, 10));
            var criteria = payload.criteria || [];
            RATING_CRITERIA_LABELS.forEach(function (label, i) {
                var row = criteria.find(function (c) { return c && c.label === label; });
                var v = row && row.score !== null && row.score !== undefined && row.score !== '' ? row.score : '';
                $('#score_' + i).val(v);
            });
            if (payload.id) {
                $('#ratingModalLabel').text('✏️ Edit supplier rating');
                $('#rating-submit-btn').html('<i class="mdi mdi-content-save me-1"></i> Update rating');
            } else {
                $('#ratingModalLabel').text('🌟 Rate Supplier Performance');
                $('#rating-submit-btn').html('<i class="mdi mdi-content-save me-1"></i> Submit Rating');
            }
        }

        // Function to bind rating modal buttons (needed after AJAX updates) - Global scope
        function bindRatingButtons() {
            $('.rate-btn').off('click.rating').on('click.rating', function () {
                clearRatingModalForm();
                var supplierId = $(this).data('supplier-id');
                var supplierName = $(this).data('supplier-name');
                var parent = $(this).data('parent');
                var skus = $(this).data('skus');

                $('#modal-supplier-id').val(supplierId);
                $('#modal-parent').val(parent || '');
                $('#modal-supplier-name').val(supplierName);

                var skuSelect = $('#modal-skus');
                if (skuSelect.length) {
                    skuSelect.empty();
                    if (Array.isArray(skus)) {
                        skus.forEach(function (sku) {
                            skuSelect.append(new Option((parent || '') + ' → ' + sku, sku, true, true));
                        });
                    }
                    skuSelect.trigger('change');
                }
            });

            $('.rate-edit-btn').off('click.rating').on('click.rating', function () {
                var supplierId = $(this).data('supplier-id');
                var supplierName = $(this).data('supplier-name');
                var payload = $(this).data('rating-payload');
                if (typeof payload === 'string') {
                    try { payload = JSON.parse(payload); } catch (e) { payload = {}; }
                }
                $('#modal-supplier-id').val(supplierId);
                $('#modal-parent').val('');
                $('#modal-supplier-name').val(supplierName);
                fillRatingModalForm(payload);
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

        /* ── Supplier Bank Details ─────────────────────────────────────── */
        const SUPPLIER_BANK_CAN_EDIT = @json($canEditSupplierBank ?? false);
        const SUPPLIER_BANK_CSRF = '{{ csrf_token() }}';
        let supplierBankEditMode = false;
        let supplierBankAccountsCache = [];
        let supplierBankDefaultName = '';

        function supplierBankSetFormEnabled(enabled) {
            $('#supplierBankForm').find('input').prop('disabled', !enabled);
            $('#supplierBankFormActions').toggleClass('d-none', !enabled);
        }

        function supplierBankClearForm(prefillName) {
            $('#supplierBankAccountId').val('');
            const form = document.getElementById('supplierBankForm');
            form.reset();
            if (prefillName) {
                form.querySelector('[name="supplier_name"]').value = prefillName;
            }
            $('#supplierBankFormTitle').text('New bank account');
            $('#supplierBankDeleteBtn').addClass('d-none');
        }

        function supplierBankFillForm(account) {
            $('#supplierBankAccountId').val(account.id || '');
            const form = document.getElementById('supplierBankForm');
            ['supplier_name','nick_name','company_name','swift','address','city','province','country','account_number'].forEach(function (f) {
                const el = form.querySelector('[name="' + f + '"]');
                if (el) el.value = account[f] || '';
            });
            $('#supplierBankFormTitle').text(account.id ? ('Edit account #' + account.id) : 'New bank account');
            $('#supplierBankDeleteBtn').toggleClass('d-none', !account.id || !SUPPLIER_BANK_CAN_EDIT);
        }

        function supplierBankRenderList(accounts) {
            supplierBankAccountsCache = accounts || [];
            const $list = $('#supplierBankAccountsList');
            if (!supplierBankAccountsCache.length) {
                $list.html('<div class="alert alert-light border mb-0 py-2 small">No bank accounts yet.</div>');
                return;
            }
            let html = '<div class="list-group mb-0">';
            supplierBankAccountsCache.forEach(function (a) {
                const title = a.nick_name || a.company_name || a.account_number || ('Account #' + a.id);
                const sub = [a.supplier_name, a.swift, a.country].filter(Boolean).join(' · ');
                html += `
                    <button type="button" class="list-group-item list-group-item-action supplier-bank-account-item py-2"
                            data-account-id="${a.id}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">${$('<div>').text(title).html()}</div>
                                <small class="text-muted">${$('<div>').text(sub || '—').html()}</small>
                            </div>
                            <i class="mdi mdi-chevron-right"></i>
                        </div>
                    </button>`;
            });
            html += '</div>';
            $list.html(html);
        }

        function supplierBankLoadAccounts(supplierId) {
            $('#supplierBankAccountsList').html('<div class="text-muted small">Loading…</div>');
            return fetch('/supplier/' + supplierId + '/bank-accounts', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': SUPPLIER_BANK_CSRF }
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.message || 'Failed to load');
                supplierBankRenderList(data.accounts || []);
                return data;
            });
        }

        function supplierBankOpenModal(supplierId, supplierName) {
            supplierBankEditMode = false;
            supplierBankDefaultName = supplierName || '';
            $('#supplierBankSupplierId').val(supplierId);
            $('#supplierBankModalSupplierName').text(supplierName || '—');
            $('#supplierBankAccountsPanel').removeClass('d-none');
            $('#supplierBankHistoryPanel').addClass('d-none');
            $('#supplierBankEditBtn').toggleClass('d-none', !SUPPLIER_BANK_CAN_EDIT);
            $('#supplierBankAddBtn').toggleClass('d-none', !SUPPLIER_BANK_CAN_EDIT);
            $('#supplierBankEditHint').text(SUPPLIER_BANK_CAN_EDIT ? '' : 'View only — edit restricted');
            supplierBankClearForm(supplierBankDefaultName);
            supplierBankSetFormEnabled(false);
            supplierBankLoadAccounts(supplierId).catch(() => {
                $('#supplierBankAccountsList').html('<div class="alert alert-danger mb-0 py-2 small">Failed to load bank accounts.</div>');
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('supplierBankModal')).show();
        }

        $(document).on('click', '.supplier-bank-open-btn', function () {
            supplierBankOpenModal($(this).data('supplier-id'), $(this).data('supplier-name'));
        });

        $('#supplierBankEditBtn').on('click', function () {
            if (!SUPPLIER_BANK_CAN_EDIT) return;
            supplierBankEditMode = true;
            supplierBankSetFormEnabled(true);
            if (!$('#supplierBankAccountId').val()) {
                supplierBankClearForm(supplierBankDefaultName);
                supplierBankSetFormEnabled(true);
            }
        });

        $('#supplierBankAddBtn').on('click', function () {
            if (!SUPPLIER_BANK_CAN_EDIT) return;
            supplierBankEditMode = true;
            supplierBankClearForm(supplierBankDefaultName);
            supplierBankSetFormEnabled(true);
        });

        $('#supplierBankCancelEditBtn').on('click', function () {
            supplierBankEditMode = false;
            supplierBankClearForm(supplierBankDefaultName);
            supplierBankSetFormEnabled(false);
        });

        $(document).on('click', '.supplier-bank-account-item', function () {
            const id = $(this).data('account-id');
            const account = supplierBankAccountsCache.find(a => String(a.id) === String(id));
            if (!account) return;
            supplierBankFillForm(account);
            if (SUPPLIER_BANK_CAN_EDIT && supplierBankEditMode) {
                supplierBankSetFormEnabled(true);
            } else {
                supplierBankSetFormEnabled(false);
            }
        });

        $('#supplierBankForm').on('submit', function (e) {
            e.preventDefault();
            if (!SUPPLIER_BANK_CAN_EDIT) return;
            const supplierId = $('#supplierBankSupplierId').val();
            const accountId = $('#supplierBankAccountId').val();
            const formData = new FormData(this);
            const payload = {};
            formData.forEach((v, k) => { payload[k] = String(v).slice(0, 30); });

            const url = accountId
                ? '/supplier/' + supplierId + '/bank-accounts/' + accountId
                : '/supplier/' + supplierId + '/bank-accounts';
            const method = accountId ? 'PUT' : 'POST';

            fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': SUPPLIER_BANK_CSRF
                },
                body: JSON.stringify(payload)
            })
            .then(r => r.json().then(j => ({ ok: r.ok, j })))
            .then(({ ok, j }) => {
                if (!ok || !j.success) {
                    alert(j.message || (j.errors ? JSON.stringify(j.errors) : 'Save failed'));
                    return;
                }
                supplierBankEditMode = false;
                supplierBankSetFormEnabled(false);
                supplierBankLoadAccounts(supplierId).then(() => {
                    if (j.account) supplierBankFillForm(j.account);
                });
                // Refresh table badge count
                if (typeof loadSuppliers === 'function') {
                    const page = new URLSearchParams(window.location.search).get('page') || 1;
                    loadSuppliers(page);
                }
            })
            .catch(() => alert('Save failed'));
        });

        $('#supplierBankDeleteBtn').on('click', function () {
            if (!SUPPLIER_BANK_CAN_EDIT) return;
            const supplierId = $('#supplierBankSupplierId').val();
            const accountId = $('#supplierBankAccountId').val();
            if (!accountId || !confirm('Delete this bank account?')) return;
            fetch('/supplier/' + supplierId + '/bank-accounts/' + accountId, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': SUPPLIER_BANK_CSRF }
            })
            .then(r => r.json())
            .then(j => {
                if (!j.success) {
                    alert(j.message || 'Delete failed');
                    return;
                }
                supplierBankClearForm(supplierBankDefaultName);
                supplierBankSetFormEnabled(false);
                supplierBankEditMode = false;
                supplierBankLoadAccounts(supplierId);
                if (typeof loadSuppliers === 'function') {
                    const page = new URLSearchParams(window.location.search).get('page') || 1;
                    loadSuppliers(page);
                }
            })
            .catch(() => alert('Delete failed'));
        });

        $('#supplierBankHistoryBtn').on('click', function () {
            const supplierId = $('#supplierBankSupplierId').val();
            $('#supplierBankAccountsPanel').addClass('d-none');
            $('#supplierBankHistoryPanel').removeClass('d-none');
            $('#supplierBankHistoryBody').html('<div class="text-muted small">Loading…</div>');
            fetch('/supplier/' + supplierId + '/bank-history', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': SUPPLIER_BANK_CSRF }
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.history || !data.history.length) {
                    $('#supplierBankHistoryBody').html('<div class="alert alert-info mb-0 py-2 small">No history yet.</div>');
                    return;
                }
                let html = '<table class="table table-sm table-bordered table-striped mb-0"><thead class="table-light"><tr><th>Date</th><th>User</th><th>Action</th><th>Changes</th></tr></thead><tbody>';
                data.history.forEach(function (h) {
                    let changeText = '';
                    if (h.action === 'created' && h.changes && h.changes.new) {
                        changeText = Object.keys(h.changes.new).filter(k => h.changes.new[k]).map(k => k + ': ' + h.changes.new[k]).join('; ');
                    } else if (h.action === 'deleted' && h.changes && h.changes.old) {
                        changeText = 'Deleted ' + (h.changes.old.account_number || h.changes.old.nick_name || 'account');
                    } else if (h.changes) {
                        changeText = Object.keys(h.changes).map(k => {
                            const c = h.changes[k];
                            if (!c || typeof c !== 'object' || !('old' in c || 'new' in c)) return '';
                            return k + ': ' + (c.old || 'empty') + ' → ' + (c.new || 'empty');
                        }).filter(Boolean).join('; ');
                    }
                    html += `<tr>
                        <td class="fw-semibold text-nowrap">${$('<div>').text(h.date_label || '').html()}</td>
                        <td>${$('<div>').text(h.user_name || 'Unknown').html()}</td>
                        <td>${$('<div>').text(h.action || '').html()}</td>
                        <td class="small" style="word-break:break-word;">${$('<div>').text(changeText || '—').html()}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
                $('#supplierBankHistoryBody').html(html);
            })
            .catch(() => {
                $('#supplierBankHistoryBody').html('<div class="alert alert-danger mb-0 py-2 small">Failed to load history.</div>');
            });
        });

        $('#supplierBankBackFromHistoryBtn').on('click', function () {
            $('#supplierBankHistoryPanel').addClass('d-none');
            $('#supplierBankAccountsPanel').removeClass('d-none');
        });

    </script>
@endsection
