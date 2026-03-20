@extends('layouts.vertical', ['title' => 'Supplier Ledger'])
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

        .image-hover-container:hover .hover-preview {
            display: block !important;
        }

        .hover-thumbnail {
            transition: transform 0.2s;
        }

        .hover-thumbnail:hover {
            transform: scale(1.05);
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Supplier Ledger',
        'sub_title' => 'Supplier Ledger',
    ])
    @if (Session::has('flash_message'))
        <div class="alert alert-primary bg-primary text-white alert-dismissible fade show" role="alert"
            style="background-color: #169e28 !important; color: #fff !important;">
            {{ Session::get('flash_message') }}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <div class="input-group" style="max-width: 320px;">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" id="supplier-ledger-search" class="form-control border-start-0"
                                placeholder="Search by Supplier name...">
                        </div>
                        <button class="btn btn-sm btn-danger d-none" id="delete-selected-btn">
                            <i class="fas fa-trash-alt me-1"></i> Delete Selected
                        </button>
                        <div class="d-flex flex-wrap gap-2">
                            <button id="add-new-row" class="btn btn-sm btn-success" data-bs-toggle="modal"
                                data-bs-target="#createSupplierLedgerModal">
                                <i class="fas fa-plus-circle me-1"></i> Create Supplier Ledger
                            </button>
                        </div>
                    </div>

                    <input type="file" id="imageUploader" style="display:none;" accept="image/*">

                    <div id="supplier-ledger-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createSupplierLedgerModal" tabindex="-1" aria-labelledby="createSupplierLedgerModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="createSupplierLedgerModalLabel">
                        <i class="fas fa-file-invoice me-2"></i> Create Supplier Ledger
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <form method="POST" action="{{ route('supplier.ledger.save') }}" enctype="multipart/form-data"
                    autocomplete="off">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            {{-- Supplier --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Supplier <span class="text-danger">*</span></label>
                                <select class="form-select" name="supplier" required>
                                    <option value="" disabled selected>Select Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- PM Image --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Payment Image</label>
                                <input type="file" name="pm_image" class="form-control" id="pmImageInput"
                                    accept="image/*">
                            </div>

                            {{-- Purchase Link --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Purchase Link</label>
                                <input type="url" name="purchase_link" class="form-control" id="purchaseLinkInput"
                                    placeholder="https://example.com">
                            </div>

                            {{-- Dr Amount --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Dr</label>
                                <input type="number" name="dr" id="drInput" class="form-control" step="0.01">
                            </div>

                            {{-- Cr Amount --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Cr</label>
                                <input type="number" name="cr" id="crInput" class="form-control" step="0.01">
                            </div>

                            {{-- Balance --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Balance</label>
                                <input type="number" name="balance" id="balanceInput" class="form-control"
                                    step="0.01" readonly>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer bg-white">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Close
                        </button>
                        <button type="submit" class="btn btn-primary" id="submit-btn">
                            <i class="fas fa-save me-1"></i> Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="image-preview-popup"
        style="display:none; position:absolute; z-index:99999; border:1px solid #ccc; background:#fff; padding:5px; box-shadow: 0 0 10px rgba(0,0,0,0.2);">
        <img src="" style="height:150px;" id="preview-popup-img">
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const table = new Tabulator("#supplier-ledger-table", {
                ajaxURL: "/supplier-ledger/list",
                ajaxConfig: "GET",
                layout: "fitColumns",
                pagination: true,
                paginationSize: 50,
                paginationMode: "local",
                resizableColumns: true,
                height: "500px",
                columns: [{
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        headerSort: false,
                        width: 50
                    },
                    {
                        title: "S.No",
                        formatter: "rownum",
                        hozAlign: "center",
                        width: 80,
                        headerHozAlign: "center"
                    },
                    {
                        title: "Supplier Name",
                        field: "supplier_id",
                        formatter: function(cell) {
                            const value = cell.getValue(); 
                            const supplier = suppliersList.find(s => s.id == value);
                            const displayName = supplier ? supplier.name : "";
                            return `
                                <select class="form-select form-select-sm auto-save" style="min-width: 90px;">
                                    <option value="">Select</option>
                                    ${suppliersList.map(supplier => `
                                        <option value="${supplier.id}" ${supplier.id == value ? 'selected' : ''}>
                                            ${supplier.name}
                                        </option>
                                    `).join('')}
                                </select>
                            `;
                        },
                        cellClick: function(e, cell) {
                            const select = cell.getElement().querySelector("select");
                            if (select && !select.dataset.listenerAttached) {
                                select.dataset.listenerAttached = true;
                                select.addEventListener("change", function() {
                                    const selectedId = select.value; 
                                    const selectedName = select.options[select.selectedIndex].text;

                                    cell.setValue(selectedId);

                                    let rowData = cell.getRow().getData();
                                    fetch("/supplier-ledger/update", {
                                        method: "POST",
                                        headers: {
                                            "Content-Type": "application/json",
                                            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
                                        },
                                        body: JSON.stringify({
                                            id: rowData.id,
                                            field: cell.getField(),
                                            value: selectedId
                                        })
                                    }).then(res => res.json())
                                    .then(resp => {
                                        if (!resp.success) alert("Update failed!");
                                    });
                                });
                            }
                        },
                        hozAlign: "center",
                        headerHozAlign: "center"
                    },
                    {
                        title: "Payment Image",
                        field: "pm_image",
                        formatter: function(cell) {
                            const url = cell.getValue();
                            return url ?
                                `<img src="${url}" class="hover-thumbnail" style="height:40px;cursor:pointer;" data-preview-url="${url}">` :
                                `<button class="upload-btn">Upload</button>`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains("upload-btn") || e.target.tagName ===
                                "IMG") {
                                let row = cell.getRow().getData();
                                let input = document.getElementById("imageUploader");
                                input.value = "";

                                input.onchange = function(event) {
                                    let file = event.target.files[0];
                                    if (file) {
                                        let formData = new FormData();
                                        formData.append("id", row.id);
                                        formData.append("field", "pm_image");
                                        formData.append("file", file);

                                        fetch("/supplier-ledger/update", {
                                                method: "POST",
                                                body: formData,
                                                headers: {
                                                    "X-CSRF-TOKEN": document.querySelector(
                                                            'meta[name="csrf-token"]')
                                                        .getAttribute("content")
                                                }
                                            })
                                            .then(res => res.json())
                                            .then(data => {
                                                if (data.success) {
                                                    cell.setValue(data.url);
                                                } else {
                                                    alert("Upload failed!");
                                                }
                                            })
                                            .catch(err => {
                                                alert("Upload error!");
                                                console.error(err);
                                            });
                                    }
                                };
                                input.click();
                            }
                        }
                    },
                    {
                        title: 'Purchase <i class="fa-solid fa-link"></i>',
                        field: "purchase_link",
                        editor: "input",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const url = cell.getValue();
                            if (!url) return "";

                            return `
                                <a href="${url}" class="btn btn-sm btn-primary" target="_blank" title="Open Packing List">
                                    <i class="fa fa-link"></i> Open
                                </a>
                            `;
                        }
                    },
                    {
                        title: "Dr",
                        field: "dr",
                        editor: "number"
                    },
                    {
                        title: "Cr",
                        field: "cr",
                        editor: "number"
                    },
                    {
                        title: "Balance",
                        field: "balance",
                        editor: "number"
                    },
                ],
                ajaxResponse: function(url, params, response) {
                    suppliersList = response.suppliers;
                    return response.ledgers;
                },
            });

            table.on("rowSelectionChanged", function(data, rows) {
                if (data.length > 0) {
                    $('#delete-selected-btn').removeClass('d-none');
                } else {
                    $('#delete-selected-btn').addClass('d-none');
                }
            });

            document.getElementById("supplier-ledger-search").addEventListener("input", function(e) {
                const keyword = e.target.value.toLowerCase();

                table.setFilter([
                    [{
                        field: "supplier_name",
                        type: "like",
                        value: keyword
                    }, ]
                ]);
            });

            table.on("cellEdited", function(cell) {
                let data = cell.getRow().getData();
                let field = cell.getField();
                let value = cell.getValue();

                fetch("/supplier-ledger/update", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": "{{ csrf_token() }}"
                        },
                        body: JSON.stringify({
                            id: data.id,
                            field: field,
                            value: value
                        })
                    })
                    .then(res => res.json())
                    .then(resp => {
                        if (!resp.success) alert("Update failed!");
                    });
            });

            $('#delete-selected-btn').on('click', function() {
                const selectedData = table.getSelectedData();

                if (selectedData.length === 0) {
                    alert('Please select at least one record to delete.');
                    return;
                }

                if (!confirm(`Are you sure you want to delete ${selectedData.length} selected records?`)) {
                    return;
                }

                const ids = selectedData.map(row => row.id);

                $.ajax({
                    url: '/supplier-ledger/delete',
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        ids: ids
                    },
                    success: function(response) {
                        table.deleteRow(ids);
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                    }
                });
            });


            let previousBalance = 0;

            const supplierSelect = document.querySelector('select[name="supplier"]');
            const drInput = document.getElementById('drInput');
            const crInput = document.getElementById('crInput');
            const balanceInput = document.getElementById('balanceInput');

            supplierSelect.addEventListener('change', function() {
                const supplierId = this.value;

                if (supplierId) {
                    fetch(`{{ route('supplier.ledger.get-balance') }}?supplier_id=${supplierId}`)
                        .then(response => response.json())
                        .then(data => {
                            previousBalance = parseFloat(data.balance) || 0;
                            calculateBalance();
                        })
                        .catch(() => {
                            previousBalance = 0;
                            calculateBalance();
                        });
                } else {
                    previousBalance = 0;
                    calculateBalance();
                }
            });

            [drInput, crInput].forEach(input => {
                input.addEventListener('input', calculateBalance);
            });

            function calculateBalance() {
                const dr = parseFloat(drInput.value) || 0;
                const cr = parseFloat(crInput.value) || 0;

                const newBalance = dr - cr;
                balanceInput.value = newBalance.toFixed(2);
            }

            const imagePreviewPopup = document.createElement('div');
            imagePreviewPopup.id = 'image-preview-popup';
            imagePreviewPopup.style.display = 'none';
            imagePreviewPopup.style.position = 'absolute';
            imagePreviewPopup.style.zIndex = '99999';
            imagePreviewPopup.style.border = '1px solid #ccc';
            imagePreviewPopup.style.background = '#fff';
            imagePreviewPopup.style.padding = '5px';
            imagePreviewPopup.style.boxShadow = '0 0 10px rgba(0,0,0,0.2)';

            const previewImg = document.createElement('img');
            previewImg.style.height = '250px';
            previewImg.id = 'preview-popup-img';

            imagePreviewPopup.appendChild(previewImg);
            document.body.appendChild(imagePreviewPopup);

            document.addEventListener('mouseover', function(e) {
                if (e.target.classList.contains('hover-thumbnail')) {
                    const imgUrl = e.target.getAttribute('data-preview-url');
                    previewImg.src = imgUrl;
                    imagePreviewPopup.style.top = (e.pageY + 10) + 'px';
                    imagePreviewPopup.style.left = (e.pageX + 10) + 'px';
                    imagePreviewPopup.style.display = 'block';
                }
            });

            document.addEventListener('mousemove', function(e) {
                if (e.target.classList.contains('hover-thumbnail')) {
                    imagePreviewPopup.style.top = (e.pageY + 10) + 'px';
                    imagePreviewPopup.style.left = (e.pageX + 10) + 'px';
                }
            });

            document.addEventListener('mouseout', function(e) {
                if (e.target.classList.contains('hover-thumbnail')) {
                    imagePreviewPopup.style.display = 'none';
                }
            });

        });
    </script>
@endsection
