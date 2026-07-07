@extends('layouts.vertical', ['title' => 'Ads Categories', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .ads-categories .tabulator .tabulator-header .tabulator-col {
            font-size: 0.8rem;
        }
        .ads-categories .tabulator-row .tabulator-cell {
            font-size: 0.82rem;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Amazon Ads', 'page_title' => 'Ads Categories'])

    <div class="row ads-categories">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-primary" id="adsCategoryAddBtn">
                            <i class="fa fa-plus me-1"></i> Ads Category
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="adsCategoryBulkBtn">
                            <i class="fa fa-plus me-1"></i> Bulk ads category
                        </button>
                    </div>
                    <div id="adsCategoriesTable"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bulk upload modal --}}
    <div class="modal fade" id="adsCategoryBulkModal" tabindex="-1" aria-labelledby="adsCategoryBulkModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="adsCategoryBulkModalLabel">Bulk Ads Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Step 1: download the template. Step 2: fill the <code>ads_category</code> column (one per row). Step 3: upload it.
                    </p>
                    <a href="{{ route('amazon.ads.categories.template') }}" class="btn btn-sm btn-outline-secondary mb-3">
                        <i class="fa fa-download me-1"></i> Download template
                    </a>
                    <label class="form-label fw-semibold mb-1" for="adsCategoryFile">Upload filled template (CSV) <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" id="adsCategoryFile" accept=".csv,text/csv">
                    <p class="small mb-0 mt-2 d-none" id="adsCategoryBulkMsg"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="adsCategoryUploadBtn">Upload</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Add category modal --}}
    <div class="modal fade" id="adsCategoryModal" tabindex="-1" aria-labelledby="adsCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="adsCategoryModalLabel">Add Ads Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold mb-1" for="adsCategoryInput">Ads Category <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="adsCategoryInput" placeholder="Enter category name">
                    <p class="small text-danger mb-0 mt-2 d-none" id="adsCategoryError"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="adsCategorySaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            var categoriesDataUrl = @json(route('amazon.ads.categories.data'));
            var categoriesStoreUrl = @json(route('amazon.ads.categories.store'));
            var categoriesBulkUrl = @json(route('amazon.ads.categories.bulk'));
            var categoriesUpdateTpl = @json(route('amazon.ads.categories.update', ['id' => '__ID__']));
            var categoriesDeleteTpl = @json(route('amazon.ads.categories.destroy', ['id' => '__ID__']));
            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            var editId = null;

            function esc(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            var table = new Tabulator('#adsCategoriesTable', {
                ajaxURL: categoriesDataUrl,
                ajaxResponse: function (url, params, response) {
                    return (response && Array.isArray(response.data)) ? response.data : (response || []);
                },
                layout: 'fitColumns',
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                paginationCounter: 'rows',
                placeholder: 'No categories yet',
                columns: [
                    {
                        title: 'Ads Category', field: 'ads_category', headerFilter: 'input',
                        hozAlign: 'center', headerHozAlign: 'center', widthGrow: 1,
                        formatter: function (cell) { return esc(cell.getValue()); }
                    },
                    {
                        title: 'Actions', field: 'id', headerSort: false,
                        hozAlign: 'center', headerHozAlign: 'center', width: 130,
                        formatter: function () {
                            return '<button type="button" class="btn btn-sm btn-outline-primary cat-edit-btn me-1" title="Edit"><i class="fa fa-pen"></i></button>'
                                + '<button type="button" class="btn btn-sm btn-outline-danger cat-del-btn" title="Delete"><i class="fa fa-trash"></i></button>';
                        },
                        cellClick: function (e, cell) {
                            var row = cell.getData();
                            if (e.target.closest('.cat-edit-btn')) {
                                openEditModal(row);
                            } else if (e.target.closest('.cat-del-btn')) {
                                deleteCategory(row);
                            }
                        }
                    }
                ]
            });

            var modalEl = document.getElementById('adsCategoryModal');
            var input = document.getElementById('adsCategoryInput');
            var errEl = document.getElementById('adsCategoryError');

            var modalTitle = document.getElementById('adsCategoryModalLabel');

            document.getElementById('adsCategoryAddBtn').addEventListener('click', function () {
                editId = null;
                if (modalTitle) { modalTitle.textContent = 'Add Ads Category'; }
                input.value = '';
                errEl.classList.add('d-none');
                errEl.textContent = '';
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });

            function openEditModal(row) {
                editId = row.id;
                if (modalTitle) { modalTitle.textContent = 'Edit Ads Category'; }
                input.value = row.ads_category || '';
                errEl.classList.add('d-none');
                errEl.textContent = '';
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            }

            function deleteCategory(row) {
                if (!window.confirm('Delete category "' + (row.ads_category || '') + '"?')) {
                    return;
                }
                fetch(categoriesDeleteTpl.replace('__ID__', encodeURIComponent(row.id)), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (res) { return res.json().then(function (b) { return { ok: res.ok, body: b }; }); })
                    .then(function (out) {
                        if (out.ok && out.body && out.body.ok) {
                            table.setData(categoriesDataUrl);
                        } else {
                            window.alert('Failed to delete.');
                        }
                    }).catch(function () { window.alert('Failed to delete.'); });
            }

            document.getElementById('adsCategorySaveBtn').addEventListener('click', function () {
                var btn = this;
                var name = (input.value || '').trim();
                if (!name) {
                    errEl.textContent = 'Category name is required.';
                    errEl.classList.remove('d-none');
                    return;
                }
                errEl.classList.add('d-none');
                btn.disabled = true;

                var saveUrl = editId ? categoriesUpdateTpl.replace('__ID__', encodeURIComponent(editId)) : categoriesStoreUrl;

                fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ name: name })
                }).then(function (res) {
                    return res.json().then(function (b) { return { ok: res.ok, body: b }; });
                }).then(function (out) {
                    btn.disabled = false;
                    if (out.ok && out.body && out.body.ok) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                        table.setData(categoriesDataUrl);
                    } else {
                        var msg = 'Failed to save.';
                        if (out.body && out.body.errors) {
                            msg = Object.values(out.body.errors).map(function (a) { return a.join(' '); }).join(' ');
                        } else if (out.body && out.body.message) {
                            msg = out.body.message;
                        }
                        errEl.textContent = msg;
                        errEl.classList.remove('d-none');
                    }
                }).catch(function () {
                    btn.disabled = false;
                    errEl.textContent = 'Failed to save.';
                    errEl.classList.remove('d-none');
                });
            });

            // Bulk upload
            var bulkModalEl = document.getElementById('adsCategoryBulkModal');
            var fileInput = document.getElementById('adsCategoryFile');
            var bulkMsg = document.getElementById('adsCategoryBulkMsg');

            document.getElementById('adsCategoryBulkBtn').addEventListener('click', function () {
                fileInput.value = '';
                bulkMsg.classList.add('d-none');
                bulkMsg.textContent = '';
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(bulkModalEl).show();
                }
            });

            document.getElementById('adsCategoryUploadBtn').addEventListener('click', function () {
                var btn = this;
                if (!fileInput.files || !fileInput.files.length) {
                    bulkMsg.className = 'small text-danger mb-0 mt-2';
                    bulkMsg.textContent = 'Please choose a CSV file.';
                    return;
                }
                var fd = new FormData();
                fd.append('file', fileInput.files[0]);
                btn.disabled = true;
                bulkMsg.className = 'small text-muted mb-0 mt-2';
                bulkMsg.textContent = 'Uploading…';

                fetch(categoriesBulkUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: fd
                }).then(function (res) {
                    return res.json().then(function (b) { return { ok: res.ok, body: b }; });
                }).then(function (out) {
                    btn.disabled = false;
                    if (out.ok && out.body && out.body.ok) {
                        bulkMsg.className = 'small text-success mb-0 mt-2';
                        bulkMsg.textContent = 'Added ' + out.body.added + ', skipped ' + out.body.skipped + ' (duplicates).';
                        table.setData(categoriesDataUrl);
                    } else {
                        var msg = 'Upload failed.';
                        if (out.body && out.body.errors) {
                            msg = Object.values(out.body.errors).map(function (a) { return a.join(' '); }).join(' ');
                        } else if (out.body && out.body.message) {
                            msg = out.body.message;
                        }
                        bulkMsg.className = 'small text-danger mb-0 mt-2';
                        bulkMsg.textContent = msg;
                    }
                }).catch(function () {
                    btn.disabled = false;
                    bulkMsg.className = 'small text-danger mb-0 mt-2';
                    bulkMsg.textContent = 'Upload failed.';
                });
            });
        })();
    </script>
@endsection
