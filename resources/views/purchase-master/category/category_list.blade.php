@extends('layouts.vertical', ['title' => 'Categories', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    #category-tabulator { font-size: 13px; }
    .tabulator .tabulator-header { background: linear-gradient(90deg, #e0e7ff 0%, #f4f7fa 100%); border-bottom: 2px solid #2563eb; font-weight: 600; }
    .tabulator .tabulator-header .tabulator-col { border-right: 1px solid #e5e7eb; }
    .tabulator .tabulator-header .tabulator-col .tabulator-header-filter input { width: 100%; }
    .tabulator-row:hover { background-color: #f8f9fa !important; }
    .tabulator-row.cat-parent-row, .tabulator-row.cat-parent-row .tabulator-cell { background-color: #fff3cd !important; }
    .tabulator-cell { padding: 6px 8px !important; }
</style>
@endsection

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
                    {{-- Page header --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <h4 class="card-title mb-0 me-2">Categories</h4>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 fs-6"
                                title="Parents listed on this page">
                                <i class="mdi mdi-sitemap-outline me-1"></i> Parents: <span id="badge-parents">0</span>
                            </span>
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-3 py-2 fs-6"
                                title="SKUs listed on this page (PARENT rows not counted)">
                                <i class="mdi mdi-barcode me-1"></i> SKUs: <span id="badge-skus">0</span>
                            </span>
                            <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle px-3 py-2 fs-6"
                                id="badge-categories-btn" role="button" tabindex="0"
                                style="cursor:pointer;user-select:none;"
                                title="Click to show one category at a time. Click again for the next category; after the last one all categories are shown again.">
                                <i class="mdi mdi-shape-outline me-1"></i> Categories: <span id="badge-categories">0</span><span id="badge-category-pos" class="ms-1"></span>
                            </span>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'categories'])
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-muted small">
                            Type in the <strong>Parent</strong>, <strong>SKU</strong> or <strong>MFR category</strong> header boxes to filter.
                            While searching a Parent / SKU, rows that are in no category are listed too.
                        </div>
                        <button type="button" class="btn btn-light border btn-sm" id="category-clear-filters">
                            <i class="mdi mdi-close me-1"></i> Clear filters
                        </button>
                    </div>
                    <div id="category-tabulator"></div>
                    {{-- Suppliers modal (opened from the Suppliers count on any SKU / parent row) --}}
                    <div class="modal fade" id="categorySuppliersModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog modal-dialog-centered shadow-none" style="max-width: 98vw; width: 98vw;">
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
                    @foreach ($allCategories as $category)
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
                                                        <!-- Category Name: search the list and pick, or add a new one -->
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-12">
                                                                <label for="category_name_{{ $category->id }}"
                                                                    class="form-label fw-semibold">
                                                                    Category Name <span class="text-danger">*</span>
                                                                </label>
                                                                @include('purchase-master.category.partials.category-name-combobox', ['inputId' => 'category_name_'.$category->id, 'value' => $category->name, 'currentId' => $category->id])
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
                    {{-- Add products (SKUs) to a category: opened from a row, or in bulk for the selected rows --}}
                    <div class="modal fade" id="addProductsModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog modal-dialog-centered modal-lg shadow-none">
                            <div class="modal-content border-0 rounded-3">
                                <div class="modal-header bg-primary text-white rounded-top">
                                    <h5 class="modal-title">
                                        <i class="mdi mdi-plus-box-outline me-1"></i>
                                        Add products to <span id="apm-kind">category</span> <span id="apm-target"></span>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="small text-muted mb-2" id="apm-note"></div>
                                    <label class="form-label fw-semibold" for="apm-search">SKU (from CP Master)</label>
                                    <input type="text" class="form-control" id="apm-search" autocomplete="off"
                                        placeholder="Quick search by SKU or parent...">

                                    <div class="d-flex justify-content-between align-items-center border rounded-top bg-light px-3 py-2 mt-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" id="apm-select-all">
                                            <label class="form-check-label fw-semibold" for="apm-select-all">Select all</label>
                                        </div>
                                        <div class="small text-muted">
                                            <span id="apm-shown">0</span> shown &middot;
                                            <span class="fw-semibold text-primary"><span id="apm-selected">0</span> selected</span>
                                            <a href="#" id="apm-clear" class="ms-2">Clear</a>
                                        </div>
                                    </div>
                                    <div id="apm-list" class="list-group list-group-flush border border-top-0 rounded-bottom"
                                        style="max-height:320px; overflow-y:auto;"></div>
                                    <div class="form-text">Selections are kept while you search. SKUs already in the category are greyed out.</div>
                                    <div class="text-danger small mt-2" id="apm-error" style="display:none;"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="apm-submit" disabled>
                                        <i class="mdi mdi-plus me-1"></i> Add <span id="apm-submit-count">0</span> SKUs
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Add a SKU that is in no category to one (opened from the Action column of uncategorised rows) --}}
                    <div class="modal fade" id="assignCategoryModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog modal-dialog-centered shadow-none">
                            <div class="modal-content border-0 rounded-3">
                                <div class="modal-header bg-primary text-white rounded-top">
                                    <h5 class="modal-title"><i class="mdi mdi-plus-box-outline me-1"></i> Add SKU to a category</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3"><span id="acm-sku-label">SKU</span>: <span class="fw-semibold" id="acm-sku"></span></div>
                                    <label class="form-label fw-semibold" for="acm-search">Category</label>
                                    <input type="text" id="acm-search" class="form-control" autocomplete="off"
                                        placeholder="Quick search a category...">
                                    <div id="acm-list" class="list-group mt-2" style="max-height:260px; overflow-y:auto;"></div>
                                    <div class="small mt-2" id="acm-chosen" style="display:none;">
                                        Selected: <span class="fw-semibold text-primary" id="acm-chosen-name"></span>
                                    </div>
                                    <div class="text-danger small mt-2" id="acm-error" style="display:none;"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="acm-submit" disabled>
                                        <i class="mdi mdi-plus me-1"></i> Add to category
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Parents (Product column) of a category --}}
                    <div class="modal fade" id="productsModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-sm shadow-none">
                            <div class="modal-content border-0">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">Products - <span id="pm-category"></span></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="small text-muted mb-2" id="pm-note"></div>
                                    <ul class="list-group list-group-flush" id="pm-list" style="max-height:320px; overflow-y:auto;"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3 text-muted small" id="category-footer"></div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        // Edit modal opened from a SKU row: remember the SKU (picking another existing category moves it there).
        // Done on the button click itself, so it does not depend on Bootstrap's modal events reaching jQuery.
        $(document).on('click', '[data-bs-target^="#editCategoryModal"]', function() {
            const $modal = $($(this).attr('data-bs-target'));
            // The clicked row's SKU - or every ticked row's SKU when the clicked row is one of the ticked rows.
            const clicked = $(this).attr('data-sku') || '';
            const key = String($(this).attr('data-key') || '');
            let skus = clicked ? [clicked] : [];
            if (clicked && window.categoryTable) {
                const ticked = window.categoryTable.getSelectedData();
                if (ticked.some(function(r) { return r.key === key; })) {
                    const all = ticked.map(function(r) { return r.sku; }).filter(Boolean);
                    skus = all.filter(function(v, i) { return all.indexOf(v) === i; });
                }
            }
            $modal.attr('data-move-sku', clicked).attr('data-move-skus', JSON.stringify(skus));
            const $input = $modal.find('.cat-combo-input');
            $input.val($input.prop('defaultValue'));
            $modal.find('.cat-combo-hint').text('');
            $modal.find('.cat-combo-menu').hide();
        });

        $(document).ready(function() {
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

                // Another existing category was picked: move this row's SKU into it (no rename).
                const owners = window.categoryNameOwners || {};
                const owner = owners[categoryName.toLowerCase()];
                if (owner !== undefined && Number(owner) !== Number(categoryId)) {
                    let moveSkus = [];
                    try { moveSkus = JSON.parse($('#editCategoryModal' + categoryId).attr('data-move-skus') || '[]'); } catch (err) { moveSkus = []; }
                    if (!moveSkus.length) {
                        alert('A category with this name already exists. Use the Edit button on one of its SKU rows to move that SKU there.');
                        return;
                    }
                    const moveBtn = $('#submit-edit-' + categoryId);
                    moveBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-1"></i> Moving...');
                    $.ajax({
                        url: '{{ url('/category/rows/add') }}',
                        method: 'POST',
                        dataType: 'json',
                        data: { _token: $('meta[name="csrf-token"]').attr('content'), category_ids: [owner], skus: moveSkus }
                    }).done(function() {
                        $('#editCategoryModal' + categoryId).modal('hide');
                        window.reloadCategoryTable();
                    }).fail(function(xhr) {
                        alert('Error: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error occurred.'));
                    }).always(function() {
                        moveBtn.prop('disabled', false).html('<i class="mdi mdi-content-save me-1"></i> Update Category');
                    });
                    return;
                }

                // Collect data
                const data = {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    category_id: categoryIdAttr,
                    category_name: categoryName
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
            // Rows (SKU / PARENT lines): delete one or several. Deleting a row removes that SKU from this
            // MFR category (the SKU stays in CP Master). The category itself is never deleted from here.
            const rowDeleteUrl = (categoryId) => '{{ url('/category') }}/' + categoryId + '/rows/delete';

            function deleteRows(selection, confirmText) {
                // selection: { categoryId: [sku, ...] }
                const categoryIds = Object.keys(selection);
                if (!categoryIds.length) return;
                if (!confirm(confirmText)) return;

                const requests = categoryIds.map(function(categoryId) {
                    return $.ajax({
                        url: rowDeleteUrl(categoryId),
                        method: 'POST',
                        dataType: 'json',
                        data: { _token: $('meta[name="csrf-token"]').attr('content'), skus: selection[categoryId] }
                    });
                });

                $.when.apply($, requests).done(function() {
                    window.reloadCategoryTable();
                }).fail(function(xhr) {
                    const msg = xhr && xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error occurred.';
                    alert('Error: ' + msg);
                    window.reloadCategoryTable();
                });
            }

            // Delete a row that has no SKU: the row is just the (empty) category, so this deletes the category.
            $(document).on('click', '.delete-category-btn', function() {
                const id = $(this).data('category-id');
                const name = String($(this).data('category-name'));
                if (!confirm('This row has no SKUs, so deleting it deletes the category "' + name + '". Continue?')) return;
                $.ajax({
                    url: '{{ url('/category/delete') }}/' + id,
                    method: 'POST',
                    data: { _method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content') }
                }).always(function() {
                    location.reload();
                });
            });

            // Delete a single row
            $(document).on('click', '.delete-row-btn', function() {
                const categoryId = $(this).data('category-id');
                const sku = String($(this).data('sku'));
                const selection = {};
                selection[categoryId] = [sku];
                deleteRows(selection, 'Delete the row "' + sku + '"? It will be removed from this MFR category (the SKU stays in CP Master).');
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
                if (window.setCategorySupplierCount) window.setCategorySupplierCount(currentCategoryId, n);
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
    <script>
        // Searchable Category Name dropdown: filter the known categories as you type and pick one; if nothing
        // matches, "+ Add new category" keeps what you typed as a new category name.
        (function() {
            const mfrNames = @json($mfrCategoryNames ?? []);
            const used = @json($usedCategoryNames ?? (object) []); // lower-case name => category id
            window.categoryNameOwners = used;

            // useFilter=false (on focus / when opened) lists every existing category; the list is only
            // narrowed once the user types.
            function render($combo, useFilter) {
                const $input = $combo.find('.cat-combo-input');
                const $menu = $combo.find('.cat-combo-menu').empty();
                const $hint = $combo.find('.cat-combo-hint');
                const typed = $.trim($input.val());
                const typedLower = typed.toLowerCase();
                const filterLower = useFilter ? typedLower : '';
                const currentId = parseInt($combo.data('current-id'), 10) || 0;
                const ownerOf = (nameLower) => (used[nameLower] !== undefined ? used[nameLower] : null);
                let exactInList = false;
                let shown = 0;

                mfrNames.forEach(function(name) {
                    const nameLower = name.toLowerCase();
                    if (filterLower && nameLower.indexOf(filterLower) === -1) return;
                    if (shown >= 100) return;
                    if (nameLower === typedLower) exactInList = true;
                    const owner = ownerOf(nameLower);
                    // Another category's name can be picked when the modal was opened from a SKU row: Update then
                    // moves that SKU into the picked category. Without a SKU (empty category row) it stays blocked.
                    const canMove = !!$combo.closest('.modal').attr('data-move-sku');
                    const existing = owner !== null && owner !== currentId;
                    const taken = existing && !canMove;
                    const $item = $('<button type="button" class="list-group-item list-group-item-action py-1 cat-combo-item"></button>')
                        .text(name).attr('data-name', name);
                    if (taken) {
                        $item.addClass('disabled text-muted').attr('data-taken', '1')
                            .append($('<span class="badge bg-secondary-subtle text-dark ms-2"></span>').text('already added'));
                    } else if (existing) {
                        $item.append($('<span class="badge bg-info-subtle text-dark ms-2"></span>').text('existing - moves the SKU(s) here'));
                    }
                    $menu.append($item);
                    shown++;
                });

                const ownerOfTyped = ownerOf(typedLower);
                const typedExisting = typed !== '' && ownerOfTyped !== null && ownerOfTyped !== currentId;
                const typedTaken = typedExisting && !$combo.closest('.modal').attr('data-move-sku');
                // The "new category" option is always the first row of the dropdown.
                if (typed !== '' && useFilter && !exactInList && !typedTaken) {
                    $menu.prepend($('<button type="button" class="list-group-item list-group-item-action py-1 text-primary fw-semibold cat-combo-new"></button>')
                        .text('+ Add new category "' + typed + '"').attr('data-name', typed));
                } else {
                    $menu.prepend($('<button type="button" class="list-group-item list-group-item-action py-1 text-primary fw-semibold cat-combo-new" data-clear="1"></button>')
                        .text('+ Add new category...'));
                }
                if (!shown) {
                    $menu.append('<div class="list-group-item text-muted py-1">No existing category matches.</div>');
                }

                const isOwn = ownerOfTyped !== null && ownerOfTyped === currentId;
                if (typed === '') $hint.text('');
                else if (typedTaken) $hint.addClass('text-danger').text('A category with this name already exists.');
                else if (typedExisting) $hint.removeClass('text-danger').text('Existing category: Update moves ' + (function() { try { const n = JSON.parse($combo.closest('.modal').attr('data-move-skus') || '[]').length; return n > 1 ? 'the ' + n + ' ticked SKUs' : 'this SKU'; } catch (e) { return 'this SKU'; } })() + ' into it.');
                else if (isOwn || exactInList) $hint.removeClass('text-danger').text('');
                else $hint.removeClass('text-danger').text('New category (not in the CP Master MFR category list).');
                return $menu;
            }

            function open($combo, useFilter) { render($combo, !!useFilter).show(); }
            function close($combo) { $combo.find('.cat-combo-menu').hide(); }

            $(document).on('focus click', '.cat-combo-input', function() { open($(this).closest('.cat-combo'), false); });
            $(document).on('input', '.cat-combo-input', function() { open($(this).closest('.cat-combo'), true); });

            $(document).on('mousedown', '.cat-combo-item, .cat-combo-new', function(e) {
                e.preventDefault(); // keep focus; avoids closing before the click is handled
                const $item = $(this);
                if ($item.attr('data-taken')) return;
                const $combo = $item.closest('.cat-combo');
                const $input = $combo.find('.cat-combo-input');
                if ($item.attr('data-clear')) {
                    // "+ Add new category...": empty the field so a new name can be typed
                    $input.val('').trigger('focus');
                    open($combo, true);
                    return;
                }
                $input.val($item.attr('data-name'));
                render($combo, true);
                close($combo);
            });

            $(document).on('click', function(e) {
                $('.cat-combo').each(function() {
                    if (!$.contains(this, e.target)) close($(this));
                });
            });
            $(document).on('keydown', '.cat-combo-input', function(e) {
                if (e.key === 'Escape') close($(this).closest('.cat-combo'));
            });
        })();
    </script>
    <script>
        (function() {
            const searchUrl = '{{ url('/category/sku-search') }}';
            const addUrl = '{{ url('/category/rows/add') }}';
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const $modal = $('#addProductsModal');
            let targets = [];           // [{id, name}]
            let selected = new Map();   // sku -> true (kept across searches)
            let shown = [];             // items currently listed
            let timer = null;
            let reqSeq = 0;

            function esc(v) { return $('<div>').text(v == null ? '' : v).html(); }

            function selectableShown() { return shown.filter(function(i) { return !i.already; }); }

            function refreshCounts() {
                const n = selected.size;
                const sel = selectableShown();
                const selShown = sel.filter(function(i) { return selected.has(i.sku); }).length;
                $('#apm-shown').text(shown.length);
                $('#apm-selected').text(n);
                $('#apm-submit-count').text(n);
                $('#apm-submit').prop('disabled', n === 0);
                const all = $('#apm-select-all');
                all.prop('disabled', sel.length === 0);
                all.prop('checked', sel.length > 0 && selShown === sel.length);
                all.prop('indeterminate', selShown > 0 && selShown < sel.length);
            }

            function render() {
                const $list = $('#apm-list').empty();
                if (!shown.length) {
                    $list.append('<div class="list-group-item text-muted small">No SKUs found.</div>');
                }
                shown.forEach(function(item, idx) {
                    const id = 'apm-item-' + idx;
                    const $row = $('<label class="list-group-item d-flex align-items-center gap-2 mb-0"></label>')
                        .attr('for', id).css('cursor', item.already ? 'not-allowed' : 'pointer');
                    const $cb = $('<input type="checkbox" class="form-check-input apm-item mt-0">')
                        .attr('id', id).attr('data-sku', item.sku)
                        .prop('checked', selected.has(item.sku) || false).prop('disabled', !!item.already);
                    const $txt = $('<span class="flex-grow-1"></span>').html(
                        '<span class="fw-semibold">' + esc(item.sku) + '</span>' +
                        (item.parent ? ' <span class="text-muted small">&middot; ' + esc(item.parent) + '</span>' : '') +
                        (item.already ? ' <span class="badge bg-secondary-subtle text-secondary ms-1">already in category</span>' : ''));
                    if (item.already) $row.addClass('text-muted bg-light');
                    $row.append($cb, $txt);
                    $list.append($row);
                });
                refreshCounts();
            }

            function search() {
                const seq = ++reqSeq;
                const params = { q: $('#apm-search').val() };
                if (targets.length === 1) params.category_id = targets[0].id;
                $('#apm-list').html('<div class="list-group-item text-muted small">Searching...</div>');
                $.getJSON(searchUrl, params).done(function(res) {
                    if (seq !== reqSeq) return;
                    shown = res.items || [];
                    render();
                }).fail(function() {
                    if (seq !== reqSeq) return;
                    shown = [];
                    $('#apm-list').html('<div class="list-group-item text-danger small">Could not load SKUs.</div>');
                    refreshCounts();
                });
            }

            function openModal(list) {
                targets = list;
                selected = new Map();
                shown = [];
                $('#apm-search').val('');
                $('#apm-error').hide();
                if (list.length === 1) {
                    $('#apm-kind').text('category');
                    $('#apm-target').text(list[0].name);
                    $('#apm-note').text('The selected SKUs will be linked to this category.');
                } else {
                    $('#apm-kind').text('');
                    $('#apm-target').text(list.length + ' categories');
                    $('#apm-note').text('The selected SKUs will be added to: ' + list.map(function(c) { return c.name; }).join(', '));
                }
                refreshCounts();
                bootstrap.Modal.getOrCreateInstance($modal[0]).show();
                search();
                setTimeout(function() { $('#apm-search').trigger('focus'); }, 300);
            }

            // Per-row button
            $(document).on('click', '.add-products-btn', function() {
                openModal([{ id: $(this).data('category-id'), name: String($(this).data('category-name')) }]);
            });

            $('#apm-search').on('input', function() {
                clearTimeout(timer);
                timer = setTimeout(search, 250);
            });

            $(document).on('change', '.apm-item', function() {
                const sku = String($(this).data('sku'));
                if (this.checked) selected.set(sku, true); else selected.delete(sku);
                refreshCounts();
            });

            $('#apm-select-all').on('change', function() {
                const on = this.checked;
                selectableShown().forEach(function(i) { if (on) selected.set(i.sku, true); else selected.delete(i.sku); });
                $('.apm-item:not(:disabled)').prop('checked', on);
                refreshCounts();
            });

            $('#apm-clear').on('click', function(e) {
                e.preventDefault();
                selected.clear();
                $('.apm-item').prop('checked', false);
                refreshCounts();
            });

            $('#apm-submit').on('click', function() {
                if (!selected.size) return;
                const $btn = $(this).prop('disabled', true);
                $('#apm-error').hide();
                $.ajax({
                    url: addUrl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        _token: csrf,
                        category_ids: targets.map(function(c) { return c.id; }),
                        skus: Array.from(selected.keys())
                    }
                }).done(function() {
                    $('#addProductsModal').modal('hide');
                    window.reloadCategoryTable();
                }).fail(function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error occurred.';
                    $('#apm-error').text(msg).show();
                    $btn.prop('disabled', false);
                });
            });
        })();
    </script>
    <script>
        // Uncategorised rows: the trash button only removes the row from this list (no data is deleted).
        $(document).on('click', '.hide-uncategorized-row-btn', function() {
            window.categoryTable.deleteRow(String($(this).data('key')));
        });

        // Uncategorised rows (Parent / SKU search): add the SKU to a category found with the quick search.
        // If the typed name is not an existing category, the same button creates it and adds the SKU.
        (function() {
            const modalEl = document.getElementById('assignCategoryModal');
            if (!modalEl) return;
            const categories = @json(collect($allCategories ?? [])->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name])->values());
            let skus = [];
            let chosen = null; // {id, name} or {id: null, name, isNew: true}

            const typed = function() { return $.trim($('#acm-search').val()); };
            const exactMatch = function(name) {
                const n = name.toLowerCase();
                return categories.find(function(c) { return c.name.toLowerCase() === n; }) || null;
            };
            // What the button will use: the picked item, else what is typed (existing name, or a new one)
            const target = function() {
                if (chosen) return chosen;
                const t = typed();
                if (!t) return null;
                return exactMatch(t) || { id: null, name: t, isNew: true };
            };

            function refresh() {
                const q = typed().toLowerCase();
                const $list = $('#acm-list').empty();
                const hits = categories.filter(function(c) { return !q || c.name.toLowerCase().indexOf(q) !== -1; });
                if (q && !exactMatch(typed())) {
                    $list.append($('<button type="button" class="list-group-item list-group-item-action py-2 text-primary fw-semibold acm-new"></button>')
                        .text('+ Create new category "' + typed() + '"'));
                }
                hits.forEach(function(c) {
                    const $item = $('<button type="button" class="list-group-item list-group-item-action py-2 acm-item"></button>')
                        .text(c.name).attr('data-id', c.id);
                    if (chosen && chosen.id === c.id) $item.addClass('active');
                    $list.append($item);
                });
                if (!hits.length && !q) $list.append('<div class="list-group-item text-muted small">No categories yet - type a name to create one.</div>');

                const t = target();
                $('#acm-chosen-name').text(t ? t.name + (t.isNew ? ' (new category)' : '') : '');
                $('#acm-chosen').toggle(!!t);
                $('#acm-submit').prop('disabled', !t)
                    .html('<i class="mdi mdi-plus me-1"></i> ' + (t && t.isNew ? 'Create category and add ' : 'Add to category: ') + (skus.length > 1 ? skus.length + ' SKUs' : 'SKU'));
            }

            $(document).on('click', '.assign-category-btn', function() {
                // Ticked rows (rows with no category) are added together when the clicked row is one of
                // them; otherwise just the clicked row's SKU.
                const clicked = String($(this).data('sku'));
                const key = String($(this).data('key'));
                skus = [clicked];
                const selected = window.categoryTable.getSelectedData().filter(function(r) { return r.kind === 'uncat'; });
                if (selected.some(function(r) { return r.key === key; })) {
                    const ticked = selected.map(function(r) { return r.sku; });
                    skus = ticked.filter(function(s, i) { return ticked.indexOf(s) === i; });
                }
                $('#acm-sku-label').text(skus.length > 1 ? skus.length + ' SKUs' : 'SKU');
                $('#acm-sku').text(skus.length > 1 ? skus.slice(0, 8).join(', ') + (skus.length > 8 ? ', ...' : '') : skus[0]);
                $('#acm-search').val('');
                $('#acm-error').hide();
                chosen = null;
                refresh();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
                setTimeout(function() { $('#acm-search').trigger('focus'); }, 300);
            });
            $('#acm-search').on('input', function() { chosen = null; refresh(); });
            $('#acm-search').on('keydown', function(e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                const exact = exactMatch(typed());
                const first = $('#acm-list .acm-item').first();
                if (exact) chosen = exact;
                else if (first.length) chosen = categories.find(function(c) { return c.id === parseInt(first.attr('data-id'), 10); });
                else if (typed()) chosen = { id: null, name: typed(), isNew: true };
                refresh();
            });
            $(document).on('click', '.acm-item', function() {
                const id = parseInt($(this).attr('data-id'), 10);
                chosen = categories.find(function(c) { return c.id === id; });
                refresh();
            });
            $(document).on('click', '.acm-new', function() {
                chosen = { id: null, name: typed(), isNew: true };
                refresh();
            });

            function showError(xhr) {
                let msg = 'Server error occurred.';
                if (xhr && xhr.responseJSON) {
                    if (xhr.responseJSON.errors) msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                    else if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
                }
                $('#acm-error').text(msg).show();
                $('#acm-submit').prop('disabled', false);
            }

            function addSku(categoryId) {
                $.ajax({
                    url: '{{ url('/category/rows/add') }}',
                    method: 'POST',
                    dataType: 'json',
                    data: { _token: $('meta[name="csrf-token"]').attr('content'), category_ids: [categoryId], skus: skus }
                }).done(function() {
                    $('#assignCategoryModal').modal('hide');
                    window.reloadCategoryTable();
                }).fail(showError);
            }

            $('#acm-submit').on('click', function() {
                const t = target();
                if (!t || !skus.length) return;
                $(this).prop('disabled', true);
                $('#acm-error').hide();
                if (t.id) {
                    addSku(t.id);
                    return;
                }
                // New category: create it first, then add the SKU to it
                $.ajax({
                    url: '{{ route('category.create') }}',
                    method: 'POST',
                    dataType: 'json',
                    data: { _token: $('meta[name="csrf-token"]').attr('content'), category_name: t.name }
                }).done(function(res) {
                    if (res && res.success && res.category && res.category.id) addSku(res.category.id);
                    else showError({ responseJSON: { message: (res && res.message) || 'Could not create the category.' } });
                }).fail(showError);
            });
        })();
    </script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        // ---- /category.list as a Tabulator -------------------------------------------------------------
        // One row per CP Master SKU / PARENT row (category columns repeated), one row for a category with no
        // SKUs, and - while the Parent / SKU filters are used - the matching rows that are in no category.
        // Filtering is done by the server (the header boxes below); sorting and selection are in the browser.
        (function() {
            const dataUrl = '{{ route('category.list.data') }}';
            const esc = function(s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            };
            const dash = '<span class="text-muted">-</span>';
            // Sentence case for display only ("CAR SPK" -> "Car spk"); the saved name is not changed.
            const sentenceCase = function(s) {
                const t = String(s == null ? '' : s).trim().toLowerCase();
                return t.charAt(0).toUpperCase() + t.slice(1);
            };

            let filters = { parent: '', sku: '', mfr: '' };
            let filterTimer = null;
            let loadSeq = 0;
            let table = null;

            // ---- columns ----
            const checkboxFormatter = function(cell) {
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.className = 'form-check-input';
                input.style.cssText = 'cursor:pointer;width:1.1rem;height:1.1rem;margin:0;';
                input.checked = cell.getRow().isSelected();
                input.addEventListener('click', function(e) { e.stopPropagation(); });
                input.addEventListener('change', function() {
                    if (input.checked) cell.getRow().select(); else cell.getRow().deselect();
                });
                return input;
            };

            const imageFormatter = function(cell) {
                const url = cell.getValue();
                if (!url) return dash;
                return '<a href="' + esc(url) + '" target="_blank" rel="noopener noreferrer">' +
                    '<img src="' + esc(url) + '" alt="" loading="lazy" ' +
                    'style="height:36px;width:36px;object-fit:cover;border-radius:4px;border:1px solid #ccc;" ' +
                    'onerror="this.replaceWith(document.createTextNode(\'-\'))"></a>';
            };

            const skuFormatter = function(cell) {
                const d = cell.getRow().getData();
                if (d.kind === 'empty') return dash;
                return d.is_parent ? '<strong>' + esc(d.sku) + '</strong>' : esc(d.sku);
            };

            const categoryFormatter = function(cell) {
                const d = cell.getRow().getData();
                if (d.kind === 'uncat') return '<span class="text-muted">No category</span>';
                return '<span class="fw-semibold">' + esc(sentenceCase(d.category_name)) + '</span>';
            };

            const supplierFormatter = function(cell) {
                const d = cell.getRow().getData();
                if (d.kind === 'uncat') return dash;
                return '<button type="button" class="btn btn-sm supplier-count-btn d-inline-flex align-items-center px-3 py-1 rounded-pill bg-info-subtle shadow-sm border-0" ' +
                    'data-category-id="' + d.category_id + '" data-category-name="' + esc(d.category_name) + '" title="View / add / edit suppliers">' +
                    '<i class="mdi mdi-account-group text-info me-2"></i>' +
                    '<span class="fw-semibold text-info supplier-count" data-category-id="' + d.category_id + '">' + d.supplier_count + '</span>' +
                    '<span class="ms-1 text-muted fw-bold">Suppliers</span></button>';
            };

            const productFormatter = function(cell) {
                const d = cell.getRow().getData();
                if (!d.parents || !d.parents.length) return dash;
                return '<button type="button" class="btn btn-sm ' + (d.parents_autofilled ? 'btn-outline-secondary' : 'btn-light') + ' products-btn" ' +
                    'data-key="' + esc(d.key) + '"' + (d.parents_autofilled ? ' title="Auto-filled from CP Master (same MFR category)"' : '') + '>' +
                    'P (' + d.parents.length + ')' + (d.parents_autofilled ? ' <i class="mdi mdi-auto-fix"></i>' : '') + '</button>';
            };

            const actionFormatter = function(cell) {
                const d = cell.getRow().getData();
                const key = esc(d.key);
                const sku = esc(d.sku);
                const cid = d.category_id;
                const cname = esc(d.category_name);
                const btn = function(cls, title, icon, attrs) {
                    return '<button type="button" class="btn btn-sm ' + cls + '" title="' + title + '" ' + (attrs || '') + '><i class="mdi ' + icon + '"></i></button>';
                };
                let html = '<div class="d-flex justify-content-center align-items-center gap-1">';
                if (d.kind === 'uncat') {
                    html += btn('btn-soft-primary assign-category-btn', 'Edit: pick a category for this SKU', 'mdi-square-edit-outline', 'data-sku="' + sku + '" data-key="' + key + '"');
                    html += btn('btn-soft-success assign-category-btn', 'Add this SKU to a category', 'mdi-plus-box-outline', 'data-sku="' + sku + '" data-key="' + key + '"');
                    html += btn('btn-soft-danger hide-uncategorized-row-btn', 'Remove this row from the list (nothing is deleted; it returns on the next search)', 'mdi-delete', 'data-key="' + key + '"');
                } else {
                    html += btn('btn-soft-primary', 'Edit category', 'mdi-square-edit-outline', 'data-bs-toggle="modal" data-bs-target="#editCategoryModal' + cid + '" data-sku="' + (d.kind === 'sku' ? sku : '') + '" data-key="' + key + '"');
                    html += btn('btn-soft-success add-products-btn', 'Add products (SKUs) to this category', 'mdi-plus-box-outline', 'data-category-id="' + cid + '" data-category-name="' + cname + '"');
                    if (d.kind === 'sku') {
                        html += btn('btn-soft-danger delete-row-btn', 'Delete this row (removes the SKU from this MFR category)', 'mdi-delete', 'data-category-id="' + cid + '" data-sku="' + sku + '"');
                    } else {
                        html += btn('btn-soft-danger delete-category-btn', 'Delete this row (it has no SKUs, so this deletes the category)', 'mdi-delete', 'data-category-id="' + cid + '" data-category-name="' + cname + '"');
                    }
                }
                return html + '</div>';
            };

            // Header boxes only collect the text; the server does the filtering (see dataFiltering below).
            const serverFilter = function() { return true; };
            const filterCol = { hozAlign: 'center', headerHozAlign: 'center', headerFilter: 'input', headerFilterFunc: serverFilter, headerFilterPlaceholder: 'Filter...' };

            table = new Tabulator('#category-tabulator', {
                index: 'key',
                columnDefaults: { hozAlign: 'center', headerHozAlign: 'center', vertAlign: 'middle' },
                layout: 'fitColumns',
                height: '72vh',
                selectableRows: 'highlight',
                placeholder: function() {
                    return (filters.parent || filters.sku) ? 'No SKUs found' : 'No categories found';
                },
                rowFormatter: function(row) {
                    row.getElement().classList.toggle('cat-parent-row', !!row.getData().is_parent);
                },
                columns: [
                    { title: '', field: 'sel', width: 46, hozAlign: 'center', headerSort: false, frozen: true,
                      titleFormatter: function() { return '<input type="checkbox" class="form-check-input" id="category-select-all" title="Select all listed rows" style="cursor:pointer;width:1.1rem;height:1.1rem;margin:0;">'; },
                      formatter: checkboxFormatter },
                    Object.assign({ title: 'Parent', field: 'parent', width: 150, frozen: true, formatter: function(c) { return c.getValue() ? esc(c.getValue()) : dash; } }, filterCol),
                    { title: 'Image', field: 'image', width: 76, hozAlign: 'center', headerSort: false, frozen: true, formatter: imageFormatter },
                    Object.assign({ title: 'SKU', field: 'sku', width: 190, frozen: true, formatter: skuFormatter }, filterCol),
                    Object.assign({ title: 'MFR category', field: 'category_name', minWidth: 180, formatter: categoryFormatter }, filterCol),
                    { title: 'Suppliers', field: 'supplier_count', width: 150, hozAlign: 'center', sorter: 'number', formatter: supplierFormatter },
                    { title: 'Product', field: 'parents', width: 100, hozAlign: 'center', headerSort: false, formatter: productFormatter },
                    { title: 'Action', field: 'key', width: 150, hozAlign: 'center', headerSort: false, frozen: true, formatter: actionFormatter },
                ],
            });
            window.categoryTable = table;

            // ---- data ----
            function load() {
                const seq = ++loadSeq;
                const qs = new URLSearchParams();
                if (filters.parent) qs.set('parent', filters.parent);
                if (filters.sku) qs.set('sku', filters.sku);
                if (filters.mfr) qs.set('mfr', filters.mfr);
                const url = dataUrl + (qs.toString() ? '?' + qs.toString() : '');
                return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function(res) {
                        if (!res.ok) throw new Error('Request failed (' + res.status + ')');
                        return res.json();
                    })
                    .then(function(rows) {
                        if (seq !== loadSeq) return;
                        return table.setData(rows);
                    })
                    .catch(function(e) { alert('Could not load the categories: ' + e.message); });
            }
            window.reloadCategoryTable = load;

            table.on('tableBuilt', load);

            // typing in a header box: wait a moment, then ask the server for the matching rows
            table.on('dataFiltering', function(list) {
                const next = { parent: '', sku: '', mfr: '' };
                (list || []).forEach(function(f) {
                    const value = String(f.value == null ? '' : f.value).trim();
                    if (f.field === 'parent') next.parent = value;
                    else if (f.field === 'sku') next.sku = value;
                    else if (f.field === 'category_name') next.mfr = value;
                });
                if (next.parent === filters.parent && next.sku === filters.sku && next.mfr === filters.mfr) return;
                filters = next;
                clearTimeout(filterTimer);
                filterTimer = setTimeout(load, 500);
            });

            document.getElementById('category-clear-filters').addEventListener('click', function() {
                table.clearHeaderFilter();
            });

            // ---- badges + footer ----
            function updateBadges() {
                const rows = table.getData();
                const parents = {}, skus = {}, categories = {};
                let uncategorized = 0;
                rows.forEach(function(r) {
                    if (r.parent) parents[r.parent.toLowerCase()] = true;
                    if (r.sku && !r.is_parent) skus[r.sku.toLowerCase()] = true;
                    if (r.category_id > 0) categories[r.category_id] = true;
                    if (r.kind === 'uncat') uncategorized++;
                });
                const nCategories = Object.keys(categories).length;
                document.getElementById('badge-parents').textContent = Object.keys(parents).length;
                document.getElementById('badge-skus').textContent = Object.keys(skus).length;
                document.getElementById('badge-categories').textContent = nCategories;
                document.getElementById('category-footer').textContent =
                    'Showing ' + nCategories + (nCategories === 1 ? ' category' : ' categories') +
                    (uncategorized ? ' and ' + uncategorized + (uncategorized === 1 ? ' SKU' : ' SKUs') + ' not in any category' : '');
                renderCategoryCycle();
            }
            ['dataLoaded', 'rowDeleted', 'rowUpdated'].forEach(function(name) {
                table.on(name, function() { setTimeout(updateBadges, 0); });
            });

            // ---- "Categories: N" badge: click = show one category at a time, click again = next category ----
            // The filter is done in the browser on the loaded rows; after the last category the next click
            // shows every category again. (A function filter is used so the header-box filters are untouched.)
            let cycleCategoryId = null;

            function categoryOrder() {
                const seen = {}, list = [];
                table.getData().forEach(function(r) {
                    if (r.category_id > 0 && !seen[r.category_id]) {
                        seen[r.category_id] = true;
                        list.push({ id: r.category_id, name: r.category_name });
                    }
                });
                return list;
            }

            function cycleFilter(data) { return data.category_id === cycleCategoryId; }

            function applyCategoryCycle() {
                table.removeFilter(cycleFilter);
                if (cycleCategoryId !== null) table.addFilter(cycleFilter);
                setTimeout(updateBadges, 0);
            }

            function renderCategoryCycle() {
                const pos = document.getElementById('badge-category-pos');
                const btn = document.getElementById('badge-categories-btn');
                const list = categoryOrder();
                let idx = -1;
                if (cycleCategoryId !== null) {
                    idx = list.findIndex(function(c) { return c.id === cycleCategoryId; });
                    if (idx < 0) { // the category is gone after a reload / delete
                        cycleCategoryId = null;
                        table.removeFilter(cycleFilter);
                    }
                }
                if (idx >= 0) {
                    pos.textContent = '\u00b7 ' + (idx + 1) + '/' + list.length + ' ' + sentenceCase(list[idx].name);
                    btn.classList.add('border-primary');
                    document.getElementById('category-footer').textContent =
                        'Category ' + (idx + 1) + ' of ' + list.length + ': ' + sentenceCase(list[idx].name) +
                        ' - click the Categories badge for the next one';
                } else {
                    pos.textContent = '';
                    btn.classList.remove('border-primary');
                }
            }

            function nextCategory() {
                const list = categoryOrder();
                if (!list.length) return;
                const idx = cycleCategoryId === null ? -1 : list.findIndex(function(c) { return c.id === cycleCategoryId; });
                const next = idx + 1;
                cycleCategoryId = next < list.length ? list[next].id : null; // past the last one = show all again
                applyCategoryCycle();
            }

            (function() {
                const btn = document.getElementById('badge-categories-btn');
                btn.addEventListener('click', nextCategory);
                btn.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); nextCategory(); }
                });
            })();

            // Clear filters also leaves the one-category view
            document.getElementById('category-clear-filters').addEventListener('click', function() {
                if (cycleCategoryId === null) return;
                cycleCategoryId = null;
                applyCategoryCycle();
            });

            // ---- selection (header checkbox selects every listed row) ----
            function syncChecks() {
                table.getRows().forEach(function(row) {
                    const cell = row.getCell('sel');
                    const input = cell && cell.getElement().querySelector('input');
                    if (input) input.checked = row.isSelected();
                });
                const all = document.getElementById('category-select-all');
                if (!all) return;
                const total = table.getRows('active').length;
                const picked = table.getSelectedRows().length;
                all.checked = total > 0 && picked === total;
                all.indeterminate = picked > 0 && picked < total;
                all.title = picked ? picked + ' of ' + total + ' rows selected' : 'Select all listed rows';
            }
            table.on('rowSelectionChanged', syncChecks);
            document.addEventListener('change', function(e) {
                if (!e.target || e.target.id !== 'category-select-all') return;
                if (e.target.checked) table.selectRow('active'); else table.deselectRow();
            });

            // ---- the suppliers modal keeps the Suppliers pills in step ----
            window.setCategorySupplierCount = function(categoryId, count) {
                table.getRows().forEach(function(row) {
                    if (row.getData().category_id === Number(categoryId)) row.update({ supplier_count: count });
                });
            };

            // ---- Product (parents) list ----
            document.addEventListener('click', function(e) {
                const btn = e.target.closest ? e.target.closest('.products-btn') : null;
                if (!btn) return;
                const row = table.getRow(btn.getAttribute('data-key'));
                if (!row) return;
                const d = row.getData();
                document.getElementById('pm-category').textContent = d.category_name;
                document.getElementById('pm-note').textContent = d.parents_autofilled ? 'Auto-filled from CP Master (same MFR category).' : '';
                document.getElementById('pm-list').innerHTML = d.parents.map(function(p) {
                    return '<li class="list-group-item py-1">' + esc(p) + '</li>';
                }).join('');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('productsModal')).show();
            });
        })();
    </script>
@endsection