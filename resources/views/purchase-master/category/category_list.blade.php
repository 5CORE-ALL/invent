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
                        aria-hidden="true">
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
                                                <option value="" disabled selected>Select Status</option>
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>
                                    </div>

                                    <hr class="my-4">

                                    {{-- Custom Fields --}}
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fw-semibold mb-0">Custom Fields for this
                                                Category</label>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                id="add-field-btn">
                                                <i class="mdi mdi-plus"></i> Add Field
                                            </button>
                                        </div>

                                        <div id="custom-fields-container" class="bg-light p-3 rounded border"></div>
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
                                    <th class="text-center align-middle" style="width: 30%">Category Name</th>
                                    <th class="text-center align-middle" style="width: 30%">Suppliers</th>
                                    <th class="text-center align-middle" style="width: 15%">Status</th>
                                    <th class="text-center align-middle" style="width: 20%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $key => $category)
                                    <tr>
                                        <td class="text-center">
                                            <div class="form-check d-flex justify-content-center align-items-center">
                                                <input type="checkbox" class="form-check-input category-checkbox"
                                                    id="category-{{ $category->id }}" value="{{ $category->id }}"
                                                    style="cursor: pointer; width: 1.2rem; height: 1.2rem;">
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center align-items-center">
                                                <div>
                                                    <h5 class="mb-0 fw-semibold">{{ $category->name }}</h5>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center align-items-center">
                                                <div
                                                    class="d-inline-flex align-items-center px-3 py-2 rounded-pill bg-info-subtle shadow-sm">
                                                    <i class="mdi mdi-account-group text-info me-2"></i>
                                                    <span
                                                        class="fw-semibold text-info">{{ $category->supplier_count }}</span>
                                                    <span class="ms-1 text-muted medium fw-bold">Suppliers</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge {{ $category->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} px-3 py-2 rounded-pill">
                                                {{ ucfirst($category->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
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

                                                        <!-- Custom Fields -->
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Custom Fields</label>
                                                            <div id="fields-container-{{ $category->id }}">
                                                                @php
                                                                    // Ensure $category->fields is decoded properly
                                                                    $decoded_fields = [];

                                                                    if (!empty($category->fields)) {
                                                                        $decoded = json_decode($category->fields, true);
                                                                        if (
                                                                            json_last_error() === JSON_ERROR_NONE &&
                                                                            is_array($decoded)
                                                                        ) {
                                                                            $decoded_fields = $decoded;
                                                                        }
                                                                    }
                                                                @endphp

                                                                @foreach ($decoded_fields as $index => $field)
                                                                    <div class="row g-2 align-items-center mb-2 field-row">
                                                                        <div class="col-md-4">
                                                                            <input type="text"
                                                                                name="field_label[{{ $category->id }}][]"
                                                                                class="form-control field-label"
                                                                                value="{{ $field['label'] ?? '' }}"
                                                                                required>
                                                                        </div>
                                                                        <div class="col-md-4">
                                                                            <input type="text"
                                                                                name="field_name[{{ $category->id }}][]"
                                                                                class="form-control field-name"
                                                                                value="{{ $field['name'] ?? '' }}"
                                                                                required>
                                                                        </div>
                                                                        <div class="col-md-3">
                                                                            <select
                                                                                name="field_type[{{ $category->id }}][]"
                                                                                class="form-select">
                                                                                <option value="text"
                                                                                    {{ ($field['type'] ?? '') == 'text' ? 'selected' : '' }}>
                                                                                    Text</option>
                                                                                <option value="number"
                                                                                    {{ ($field['type'] ?? '') == 'number' ? 'selected' : '' }}>
                                                                                    Number</option>
                                                                                <option value="textarea"
                                                                                    {{ ($field['type'] ?? '') == 'textarea' ? 'selected' : '' }}>
                                                                                    Textarea</option>
                                                                                <option value="select"
                                                                                    {{ ($field['type'] ?? '') == 'select' ? 'selected' : '' }}>
                                                                                    Select</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-md-1 text-center">
                                                                            <button type="button"
                                                                                class="btn btn-danger btn-sm remove-field">&times;</button>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                            <button type="button" class="btn btn-sm btn-secondary mt-2"
                                                                onclick="addField('{{ $category->id }}', this)">
                                                                + Add Field
                                                            </button>
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
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">No categories found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
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
                    $("#category-table-body tbody tr").each(function() {
                        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                    });
                }, 300);
            });
            $('#submit-add').on('click', function(e) {
                e.preventDefault();
                const data = {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    category_name: $('#category_name').val(),
                    status: $('#category_status').val(),
                    field_label: [],
                    field_name: [],
                    field_type: []
                };

                $('#custom-fields-container .field-row').each(function() {
                    data.field_label.push($(this).find('.field-label').val());
                    data.field_name.push($(this).find('.field-name').val());
                    data.field_type.push($(this).find('select').val());
                });

                $.ajax({
                    url: '{{ route('category.create') }}',
                    method: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {

                            location.reload();
                        } else {
                            location.reload();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        alert('An error occurred while saving the category. Please try again.');
                        location.reload();
                    }
                });
            });

            // Handle edit submission
            $('body').on('click', '[id^="submit-edit-"]', function(e) {
                e.preventDefault();
                const categoryId = $(this).attr('id').replace('submit-edit-', '');
                const container = $('#edit-container-' + categoryId);
                const categoryIdAttr = container.data('category-id');

                // Collect data
                const data = {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    category_id: categoryIdAttr,
                    category_name: $('#category_name_' + categoryId).val(),
                    status: $('#category_status_' + categoryId).val(),
                    field_label: [],
                    field_name: [],
                    field_type: [],
                    flag: 'update'
                };

                // Collect custom fields
                $('#fields-container-' + categoryId + ' .field-row').each(function() {
                    data.field_label.push($(this).find('.field-label').val());
                    data.field_name.push($(this).find('.field-name').val());
                    data.field_type.push($(this).find('select').val());
                });

                console.log('Submitting Edit Data:', data);

                // AJAX submission
                $.ajax({
                    url: '{{ route('category.create') }}',
                    method: 'post',
                    data: data,
                    success: function(response) {

                        location.reload();

                    },
                    error: function(xhr, status, error) {
                        location.reload();
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
            // Add new field on button click for Add Category Modal
            document.getElementById('add-field-btn')?.addEventListener('click', function(e) {
                e.preventDefault();
                const container = document.getElementById('custom-fields-container');
                if (container) {
                    container.insertAdjacentHTML('beforeend', `
                        <div class="field-row row g-2 align-items-center mb-2">
                            <div class="col-md-4">
                                <input type="text" name="field_label[new][]" class="form-control field-label" placeholder="Field Label (e.g. Product Width)" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="field_name[new][]" class="form-control field-name" placeholder="Auto-generated" readonly required>
                            </div>
                            <div class="col-md-3">
                                <select name="field_type[new][]" class="form-select">
                                    <option value="text" selected>Text</option>
                                    <option value="number">Number</option>
                                    <option value="textarea">Textarea</option>
                                    <option value="select">Select</option>
                                </select>
                            </div>
                            <div class="col-md-1 text-center">
                                <button type="button" class="btn btn-danger btn-sm remove-field" title="Remove Field">&times;</button>
                            </div>
                        </div>
                    `);
                }
            });

            // Add new field for Edit Category Modal
            window.addField = function(categoryId, btnElement) {
                const container = document.querySelector(`#fields-container-${categoryId}`);
                if (container) {
                    container.insertAdjacentHTML('beforeend', `
                        <div class="field-row row g-2 align-items-center mb-2">
                            <div class="col-md-4">
                                <input type="text" name="field_label[${categoryId}][]" class="form-control field-label" placeholder="Field Label" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="field_name[${categoryId}][]" class="form-control field-name" placeholder="Auto-generated" readonly required>
                            </div>
                            <div class="col-md-3">
                                <select name="field_type[${categoryId}][]" class="form-select">
                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="textarea">Textarea</option>
                                    <option value="select">Select</option>
                                </select>
                            </div>
                            <div class="col-md-1 text-center">
                                <button type="button" class="btn btn-danger btn-sm remove-field">&times;</button>
                            </div>
                        </div>
                    `);
                    console.log(`Added new field to #fields-container-${categoryId}`);
                } else {
                    console.error(`Container #fields-container-${categoryId} not found`);
                }
            };

            // Remove field row on clicking remove button (delegated)
            document.body.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-field')) {
                    e.target.closest('.field-row').remove();
                }
            });

            // Auto-generate field_name from field_label input (delegated)
            document.body.addEventListener('input', function(e) {
                if (e.target.classList.contains('field-label')) {
                    const labelValue = e.target.value.trim();
                    const fieldNameInput = e.target.closest('.field-row').querySelector('.field-name');
                    const snakeCaseName = labelValue
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '_')
                        .replace(/^_|_$/g, '');
                    if (fieldNameInput) {
                        fieldNameInput.value = snakeCaseName;
                    }
                }
            });
        });
    </script>
@endsection
