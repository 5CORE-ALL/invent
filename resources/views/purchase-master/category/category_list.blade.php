@extends('layouts.vertical', ['title' => 'Categories', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Categories', 'sub_title' => 'Categories'])

    {{-- ✅ Flash Message --}}
    @if (Session::has('flash_message'))
        <div class="alert alert-primary bg-primary text-white alert-dismissible fade show" role="alert"
            style="background-color: #03a744 !important; color: #fff !important;">
            {{ Session::get('flash_message') }}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <style>
        .pagination-wrapper {
            width: auto;
            overflow-x: auto;
        }

        .pagination-wrapper .pagination {
            margin: 0;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
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
            box-shadow: 0 2px 4px rgba(114, 124, 245, 0.2);
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

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    {{-- ✅ Add Category Button --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="card-title mb-0">Categories</h4>
                        <div class="d-flex gap-2 align-items-center">
                            @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'categories'])
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="select-all">
                                <label class="form-check-label" for="select-all">Select All</label>
                            </div>
                            <button id="bulkDeleteBtn" class="btn btn-danger btn-sm" disabled>
                                <i class="mdi mdi-delete"></i> Delete Selected
                            </button>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                data-bs-target="#addCategoryModal">
                                <i class="mdi mdi-plus me-1"></i> Add Category
                            </button>
                        </div>
                    </div>

                    <!-- Add Category Modal -->
                    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel"
                        aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog modal-dialog-centered shadow-none modal-lg">
                            <div class="modal-content border-0 rounded-3">
                                <div class="modal-header bg-primary text-white rounded-top">
                                    <h5 class="modal-title" id="addCategoryModalLabel">
                                        <i class="mdi mdi-plus-circle me-1"></i> <span id="modalTitle">Add New
                                            Category</span>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>

                                <div class="modal-body p-4">
                                    {{-- Row: Category Name & Status --}}
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <label for="category_name" class="form-label fw-semibold">Category Name
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="category_name"
                                                name="category_name" placeholder="Enter category name">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="category_status" class="form-label fw-semibold">Status </label>
                                            <select class="form-select" id="category_status" name="status">
                                                <option value="active" selected>Active</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary px-4" id="submit-add">
                                        <i class="mdi mdi-content-save me-1"></i> Save Category
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 🔍 Search Bar --}}
                    {{-- Parent / SKU / MFR category filter (searches CP Master across all categories, not just this page) --}}
                    <form method="GET" action="{{ route('category.list') }}" class="row g-2 align-items-end mb-3"
                        id="cp-filter-form">
                        <div class="col-md-3">
                            <label for="filter-parent" class="form-label fw-semibold">Parent</label>
                            <input type="text" id="filter-parent" name="parent" class="form-control"
                                value="{{ $parentFilter ?? '' }}" placeholder="Filter by parent..." autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <label for="filter-sku" class="form-label fw-semibold">SKU</label>
                            <input type="text" id="filter-sku" name="sku" class="form-control"
                                value="{{ $skuFilter ?? '' }}" placeholder="Filter by SKU..." autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <label for="filter-mfr" class="form-label fw-semibold">MFR category</label>
                            <input type="text" id="filter-mfr" name="mfr" class="form-control"
                                value="{{ $mfrFilter ?? '' }}" placeholder="Filter by MFR category..." autocomplete="off">
                        </div>
                        <div class="col-md-auto d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-filter-outline me-1"></i> Filter
                            </button>
                            @if (($parentFilter ?? '') !== '' || ($skuFilter ?? '') !== '' || ($mfrFilter ?? '') !== '')
                                <a href="{{ route('category.list') }}" class="btn btn-light border">
                                    <i class="mdi mdi-close me-1"></i> Clear
                                </a>
                            @endif
                        </div>
                    </form>

                    <div class="row d-flex justify-content-end align-items-center mb-3">
                        <div class="col-md-4">
                            <label for="search-input" class="form-label fw-semibold">Search</label>
                            <div class="input-group">
                                <input type="text" id="search-input" class="form-control"
                                    placeholder="Search categories...">
                                <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-centered table-hover mb-0 border" id="category-table-body">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center align-middle" style="width: 5%">#</th>
                                    <th class="text-center align-middle" style="width: 12%">Parent</th>
                                    <th class="text-center align-middle" style="width: 14%">SKU</th>
                                    <th class="text-center align-middle" style="width: 20%">Link Sku Purchase</th>
                                    <th class="text-center align-middle" style="width: 18%">MFR category</th>
                                    <th class="text-center align-middle" style="width: 15%">Suppliers</th>
                                    <th class="text-center align-middle" style="width: 12%">Product</th>
                                    <th class="text-center align-middle" style="width: 12%">Status</th>
                                    <th class="text-center align-middle" style="width: 18%">Action</th>
                                </tr>
                            </thead>
                            @forelse($categories as $key => $category)
                                @php
                                    $cpSkus = $category->cp_master_skus ?? [];
                                    $rowspan = max(1, count($cpSkus));
                                    $firstSku = $cpSkus[0] ?? null;
                                @endphp
                                {{-- One <tbody> per category: one row per CP Master SKU (parent rows included);
                                     the category-level cells span all of its SKU rows. --}}
                                <tbody class="category-group">
                                    <tr>
                                        <td class="text-center align-middle" rowspan="{{ $rowspan }}">
                                            <div class="form-check d-flex justify-content-center align-items-center">
                                                <input type="checkbox" class="form-check-input category-checkbox"
                                                    id="category-{{ $category->id }}" value="{{ $category->id }}"
                                                    style="cursor: pointer; width: 1.2rem; height: 1.2rem;">
                                            </div>
                                        </td>
                                        <td class="text-center parent-cell {{ $firstSku && $firstSku['is_parent'] ? 'bg-warning-subtle fw-bold' : '' }}">
                                            @if ($firstSku && $firstSku['parent'] !== '')
                                                {{ $firstSku['parent'] }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center sku-cell {{ $firstSku && $firstSku['is_parent'] ? 'bg-warning-subtle fw-bold' : '' }}">
                                            @if ($firstSku)
                                                {{ $firstSku['sku'] }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center align-middle" rowspan="{{ $rowspan }}">
                                            @php $linkedSkus = $category->linked_skus_list ?? []; @endphp
                                            @if (count($linkedSkus) > 0)
                                                <div class="d-flex flex-wrap align-items-start justify-content-center py-1"
                                                    style="line-height:1.6;">
                                                    @foreach ($linkedSkus as $linkedSku)
                                                        <span
                                                            class="linked-sku-badge-wrap badge bg-info-subtle text-dark border me-1 mb-1">
                                                            <a href="{{ route('supplier.list', ['category' => $category->name, 'search' => $linkedSku]) }}"
                                                                target="_blank" rel="noopener noreferrer"
                                                                class="text-decoration-none text-dark linked-sku-badge"
                                                                title="Open {{ $category->name }} for {{ $linkedSku }}">{{ $linkedSku }}</a>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-muted fst-italic">No SKUs</span>
                                            @endif
                                        </td>
                                        <td class="text-center align-middle mfr-category-cell {{ $firstSku && $firstSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                            <div class="d-flex justify-content-center align-items-center">
                                                <div>
                                                    <h5 class="mb-0 fw-semibold">{{ $category->name }}</h5>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle supplier-cell {{ $firstSku && $firstSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                            <button type="button" class="btn btn-sm supplier-count-btn d-inline-flex align-items-center px-3 py-2 rounded-pill bg-info-subtle shadow-sm border-0"
                                                data-category-id="{{ $category->id }}" data-category-name="{{ $category->name }}"
                                                title="View / add / edit suppliers">
                                                <i class="mdi mdi-account-group text-info me-2"></i>
                                                <span class="fw-semibold text-info supplier-count" data-category-id="{{ $category->id }}">{{ $category->supplier_count }}</span>
                                                <span class="ms-1 text-muted fw-bold">Suppliers</span>
                                            </button>
                                        </td>
                                        <td class="text-center align-middle" rowspan="{{ $rowspan }}">
                                            @php $parents = $category->parents_list ?? []; @endphp
                                            @if(count($parents) > 0)
                                                <div class="dropdown d-inline-block">
                                                    <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        P ({{ count($parents) }})
                                                    </button>
                                                    <ul class="dropdown-menu" style="max-height: 240px; overflow-y: auto;">
                                                        @foreach ($parents as $parent)
                                                            <li><span class="dropdown-item">{{ $parent }}</span></li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center align-middle" rowspan="{{ $rowspan }}">
                                            <span
                                                class="badge {{ $category->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} px-3 py-2 rounded-pill">
                                                {{ ucfirst($category->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center align-middle" rowspan="{{ $rowspan }}">
                                            <div class="d-flex justify-content-center align-items-center gap-1">
                                                <a href="#" class="btn btn-soft-primary btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editCategoryModal{{ $category->id }}" title="Edit">
                                                    <i class="mdi mdi-square-edit-outline" data-bs-toggle="tooltip"
                                                        data-bs-placement="top" title="Edit"></i>
                                                </a>

                                                <form action="{{ route('category.delete', $category->id) }}"
                                                    method="POST" class="delete-category-form"
                                                    onsubmit="return confirm('Are you sure you want to delete this category?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-soft-danger btn-sm"
                                                        data-bs-toggle="tooltip" title="Delete">
                                                        <i class="mdi mdi-delete"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @foreach (array_slice($cpSkus, 1) as $cpSku)
                                        <tr>
                                            <td class="text-center parent-cell {{ $cpSku['is_parent'] ? 'bg-warning-subtle fw-bold' : '' }}">
                                                @if ($cpSku['parent'] !== '')
                                                    {{ $cpSku['parent'] }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center sku-cell {{ $cpSku['is_parent'] ? 'bg-warning-subtle fw-bold' : '' }}">
                                                {{ $cpSku['sku'] }}
                                            </td>
                                            <td class="text-center align-middle mfr-category-cell {{ $cpSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                                <h5 class="mb-0 fw-semibold">{{ $category->name }}</h5>
                                            </td>
                                            <td class="text-center align-middle supplier-cell {{ $cpSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                                <button type="button" class="btn btn-sm supplier-count-btn d-inline-flex align-items-center px-3 py-2 rounded-pill bg-info-subtle shadow-sm border-0"
                                                    data-category-id="{{ $category->id }}" data-category-name="{{ $category->name }}"
                                                    title="View / add / edit suppliers">
                                                    <i class="mdi mdi-account-group text-info me-2"></i>
                                                    <span class="fw-semibold text-info supplier-count" data-category-id="{{ $category->id }}">{{ $category->supplier_count }}</span>
                                                    <span class="ms-1 text-muted fw-bold">Suppliers</span>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @empty
                                <tbody>
                                    <tr>
                                        <td colspan="9" class="text-center">No categories found</td>
                                    </tr>
                                </tbody>
                            @endforelse
                        </table>
                    </div>
                    {{-- Suppliers modal (opened from the Suppliers count on any SKU / parent row) --}}
                    <div class="modal fade" id="categorySuppliersModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog modal-dialog-centered modal-xl shadow-none">
                            <div class="modal-content border-0">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">
                                        <i class="mdi mdi-account-group me-1"></i>
                                        Suppliers - <span id="csm-category-name"></span>
                                        <span class="badge bg-light text-dark ms-2" id="csm-count">0</span>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info py-2 small mb-3">
                                        Suppliers belong to the category, so any change here applies to <strong>all SKUs and parents</strong>
                                        of this category. <strong>Remove</strong> only unlinks the supplier from this category; the supplier stays in Supplier List.
                                    </div>
                                    <div id="csm-message" class="small mb-2"></div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="min-width:160px">Name</th>
                                                    <th style="min-width:140px">Company</th>
                                                    <th style="min-width:120px">Phone</th>
                                                    <th style="min-width:160px">Email</th>
                                                    <th style="min-width:120px">WhatsApp</th>
                                                    <th style="min-width:120px">WeChat</th>
                                                    <th style="min-width:110px">City</th>
                                                    <th style="width:150px" class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="csm-body"></tbody>
                                            <tfoot>
                                                <tr class="table-light">
                                                    <td><input type="text" class="form-control form-control-sm" id="csm-new-name" placeholder="New supplier name *"></td>
                                                    <td><input type="text" class="form-control form-control-sm" id="csm-new-company" placeholder="Company"></td>
                                                    <td><input type="text" class="form-control form-control-sm" id="csm-new-phone" placeholder="Phone"></td>
                                                    <td><input type="text" class="form-control form-control-sm" id="csm-new-email" placeholder="Email"></td>
                                                    <td><input type="text" class="form-control form-control-sm" id="csm-new-whatsapp" placeholder="WhatsApp"></td>
                                                    <td><input type="text" class="form-control form-control-sm" id="csm-new-wechat" placeholder="WeChat"></td>
                                                    <td><input type="text" class="form-control form-control-sm" id="csm-new-city" placeholder="City"></td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-success btn-sm" id="csm-add-new">
                                                            <i class="mdi mdi-plus"></i> Add
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center mt-3">
                                        <label for="csm-existing" class="form-label fw-semibold mb-0">Or link an existing supplier:</label>
                                        <select id="csm-existing" class="form-select form-select-sm" style="max-width:320px">
                                            <option value="">Select supplier...</option>
                                        </select>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="csm-link-existing">
                                            <i class="mdi mdi-link-variant"></i> Link
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @foreach ($categories as $category)
                                    <!-- Edit Category Modal -->
                                    <div id="editCategoryModal{{ $category->id }}" class="modal fade" tabindex="-1"
                                        aria-labelledby="editCategoryModal" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg shadow-none">
                                            <div class="modal-content border-0">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title">
                                                        <i class="mdi mdi-pencil me-1"></i> Edit Category
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white"
                                                        data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="edit-category-container"
                                                    id="edit-container-{{ $category->id }}"
                                                    data-category-id="{{ $category->id }}">
                                                    <div class="modal-body p-4">
                                                        <!-- Name & Status -->
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-md-6">
                                                                <label for="category_name_{{ $category->id }}"
                                                                    class="form-label fw-semibold">
                                                                    Category Name <span class="text-danger">*</span>
                                                                </label>
                                                                <input type="text" class="form-control"
                                                                    id="category_name_{{ $category->id }}"
                                                                    name="category_name" value="{{ $category->name }}">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="category_status_{{ $category->id }}"
                                                                    class="form-label fw-semibold">Status</label>
                                                                <select class="form-select"
                                                                    id="category_status_{{ $category->id }}"
                                                                    name="status">
                                                                    <option value="active"
                                                                        @if ($category->status == 'active') selected @endif>
                                                                        Active</option>
                                                                    <option value="inactive"
                                                                        @if ($category->status == 'inactive') selected @endif>
                                                                        Inactive</option>
                                                                </select>
                                                            </div>
                                                        </div>

                                                    </div>

                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-primary"
                                                            id="submit-edit-{{ $category->id }}">
                                                            <i class="mdi mdi-content-save me-1"></i> Update Category
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                    @endforeach
                    <div class="d-flex justify-content-end mt-4">
                        <div class="pagination-wrapper" id="pagination-wrapper">
                            {{ $categories->onEachSide(1)->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function() {
            let searchTimer;
            $('#search-input').on('keyup', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    const value = $(this).val().toLowerCase();
                    $("#category-table-body tbody.category-group").each(function() {
                        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                    });
                }, 300);
            });
            $('#submit-add').on('click', function(e) {
                e.preventDefault();
                
                // Validation
                const categoryName = $('#category_name').val().trim();
                if (!categoryName) {
                    alert('Please enter a category name.');
                    $('#category_name').focus();
                    return;
                }

                const data = {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    category_name: categoryName,
                    status: $('#category_status').val() || 'inactive'
                };

                // Disable button to prevent double submission
                const submitBtn = $('#submit-add');
                submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-1"></i> Saving...');

                $.ajax({
                    url: '{{ route('category.create') }}',
                    method: 'POST',
                    data: data,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#addCategoryModal').modal('hide');
                            // Reset form
                            $('#category_name').val('');
                            $('#category_status').val('active');
                            location.reload();
                        } else {
                            alert(response.message || 'Failed to save category.');
                            submitBtn.prop('disabled', false).html('<i class="mdi mdi-content-save me-1"></i> Save Category');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        let errorMessage = 'An error occurred while saving the category.';
                        
                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.errors) {
                                const errors = Object.values(xhr.responseJSON.errors).flat();
                                errorMessage = errors.join('\n');
                            } else if (xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                        }
                        
                        alert(errorMessage);
                        submitBtn.prop('disabled', false).html('<i class="mdi mdi-content-save me-1"></i> Save Category');
                    }
                });
            });

            // Handle edit submission
            $('body').on('click', '[id^="submit-edit-"]', function(e) {
                e.preventDefault();
                const categoryId = $(this).attr('id').replace('submit-edit-', '');
                const container = $('#edit-container-' + categoryId);
                const categoryIdAttr = container.data('category-id');

                // Validation
                const categoryName = $('#category_name_' + categoryId).val().trim();
                if (!categoryName) {
                    alert('Please enter a category name.');
                    $('#category_name_' + categoryId).focus();
                    return;
                }

                // Collect data
                const data = {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    category_id: categoryIdAttr,
                    category_name: categoryName,
                    status: $('#category_status_' + categoryId).val() || 'inactive'
                };

                // Disable button to prevent double submission
                const submitBtn = $('#submit-edit-' + categoryId);
                submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-1"></i> Updating...');

                // AJAX submission
                $.ajax({
                    url: '{{ route('category.create') }}',
                    method: 'POST',
                    data: data,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#editCategoryModal' + categoryId).modal('hide');
                            location.reload();
                        } else {
                            alert(response.message || 'Failed to update category.');
                            submitBtn.prop('disabled', false).html('<i class="mdi mdi-content-save me-1"></i> Update Category');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        let errorMessage = 'An error occurred while updating the category.';
                        
                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.errors) {
                                const errors = Object.values(xhr.responseJSON.errors).flat();
                                errorMessage = errors.join('\n');
                            } else if (xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                        }
                        
                        alert(errorMessage);
                        submitBtn.prop('disabled', false).html('<i class="mdi mdi-content-save me-1"></i> Update Category');
                    }
                });
            });
        });

        $(document).ready(function() {
            // Toggle All
            $('#select-all').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.category-checkbox').prop('checked', isChecked).trigger('change');
            });

            // Enable/disable delete button
            $(document).on('change', '.category-checkbox', function() {
                const anyChecked = $('.category-checkbox:checked').length > 0;
                $('#bulkDeleteBtn').prop('disabled', !anyChecked);
            });

            // Handle bulk delete
            $('#bulkDeleteBtn').click(function() {
                const ids = $('.category-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();

                if (!ids.length) return;

                $.ajax({
                    url: "{{ route('category.bulk-delete') }}",
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids
                    },
                    success: function(res) {
                        if (res.success) {
                            location.reload(); // or manually remove rows
                        } else {
                            alert('Error: ' + res.message);
                        }
                    },
                    error: function() {
                        alert('Server error occurred.');
                    }
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Reset form when modal is closed
            $('#addCategoryModal').on('hidden.bs.modal', function() {
                $('#category_name').val('');
                $('#category_status').val('active');
            });
        });
    </script>
    <script>
        (function() {
            const baseUrl = '{{ url('/category') }}';
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const fields = ['name', 'company', 'phone', 'email', 'whatsapp', 'wechat', 'city'];
            let currentCategoryId = null;

            const esc = (v) => $('<div>').text(v == null ? '' : v).html();
            const url = (suffix) => baseUrl + '/' + currentCategoryId + '/suppliers' + (suffix || '');

            function api(method, endpoint, data) {
                return $.ajax({
                    url: endpoint,
                    method: method,
                    data: data || {},
                    dataType: 'json',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                });
            }

            function errText(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.message) return xhr.responseJSON.message;
                return 'Something went wrong. Please try again.';
            }

            function showMessage(text, ok) {
                $('#csm-message').html(text ? '<span class="' + (ok ? 'text-success' : 'text-danger') + '">' + esc(text) + '</span>' : '');
            }

            function setCount(n) {
                $('#csm-count').text(n);
                $('.supplier-count[data-category-id="' + currentCategoryId + '"]').text(n);
            }

            function rowHtml(s) {
                let h = '<tr data-supplier-id="' + s.id + '">';
                fields.forEach(function(f) {
                    h += '<td><input type="text" class="form-control form-control-sm csm-field" data-field="' + f + '" value="' + esc(s[f]) + '"></td>';
                });
                h += '<td class="text-center text-nowrap">' +
                    '<button type="button" class="btn btn-primary btn-sm csm-save" title="Save"><i class="mdi mdi-content-save"></i></button> ' +
                    '<button type="button" class="btn btn-outline-danger btn-sm csm-remove" title="Remove from this category"><i class="mdi mdi-delete"></i></button>' +
                    '</td></tr>';
                return h;
            }

            function render(data) {
                const rows = (data.suppliers || []).map(rowHtml).join('');
                $('#csm-body').html(rows || '<tr class="csm-empty"><td colspan="8" class="text-center text-muted py-3">No suppliers for this category yet.</td></tr>');
                const opts = ['<option value="">Select supplier...</option>'].concat((data.available || []).map(function(a) {
                    return '<option value="' + a.id + '">' + esc(a.name) + '</option>';
                }));
                $('#csm-existing').html(opts.join(''));
                setCount((data.suppliers || []).length);
            }

            function load() {
                $('#csm-body').html('<tr><td colspan="8" class="text-center text-muted py-3">Loading...</td></tr>');
                api('GET', url()).done(render).fail(function(xhr) {
                    $('#csm-body').html('');
                    showMessage(errText(xhr), false);
                });
            }

            $(document).on('click', '.supplier-count-btn', function() {
                currentCategoryId = $(this).data('category-id');
                $('#csm-category-name').text($(this).data('category-name'));
                showMessage('', true);
                $('#categorySuppliersModal input').val('');
                new bootstrap.Modal(document.getElementById('categorySuppliersModal')).show();
                load();
            });

            $(document).on('click', '#csm-body .csm-save', function() {
                const tr = $(this).closest('tr');
                const data = {};
                tr.find('.csm-field').each(function() { data[$(this).data('field')] = $(this).val(); });
                if (!$.trim(data.name)) { showMessage('Supplier name is required.', false); return; }
                const btn = $(this).prop('disabled', true);
                api('PUT', url('/' + tr.data('supplier-id')), data)
                    .done(function(res) { showMessage(res.message + ' Applies to all SKUs of this category.', true); })
                    .fail(function(xhr) { showMessage(errText(xhr), false); })
                    .always(function() { btn.prop('disabled', false); });
            });

            $(document).on('click', '#csm-body .csm-remove', function() {
                const tr = $(this).closest('tr');
                const name = tr.find('[data-field="name"]').val();
                if (!confirm('Remove "' + name + '" from this category? It will disappear for all SKUs and parents of this category. The supplier stays in Supplier List.')) return;
                api('DELETE', url('/' + tr.data('supplier-id')))
                    .done(function(res) { showMessage(res.message, true); load(); })
                    .fail(function(xhr) { showMessage(errText(xhr), false); });
            });

            $('#csm-add-new').on('click', function() {
                const data = {};
                fields.forEach(function(f) { data[f] = $('#csm-new-' + f).val(); });
                if (!$.trim(data.name)) { showMessage('Enter a supplier name to add.', false); return; }
                const btn = $(this).prop('disabled', true);
                api('POST', url(), data)
                    .done(function(res) {
                        showMessage(res.message + ' It now applies to all SKUs of this category.', true);
                        fields.forEach(function(f) { $('#csm-new-' + f).val(''); });
                        load();
                    })
                    .fail(function(xhr) { showMessage(errText(xhr), false); })
                    .always(function() { btn.prop('disabled', false); });
            });

            $('#csm-link-existing').on('click', function() {
                const supplierId = $('#csm-existing').val();
                if (!supplierId) { showMessage('Select a supplier to link.', false); return; }
                api('POST', url(), { supplier_id: supplierId })
                    .done(function(res) { showMessage(res.message, true); load(); })
                    .fail(function(xhr) { showMessage(errText(xhr), false); });
            });
        })();
    </script>
@endsection
