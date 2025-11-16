@extends('layouts.vertical', ['title' => 'Amazon Product Review'])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Resizer styling */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-resize-handle {
            width: 5px;
            background-color: #dee2e6;
            cursor: ew-resize;
        }

        /* Header styling */
        .tabulator .tabulator-header {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #1a2942;
            border-right: 1px solid #ffffff;
            color: #fff;
            font-weight: bold;
            padding: 12px 8px;
        }

        .tabulator-tableholder {
            height: calc(100% - 100px) !important;
        }

        .tabulator-row {
            background-color: #ffffff !important;
            /* default black for all rows */
        }

        /* Cell styling */
        .tabulator .tabulator-cell {
            text-align: center;
            padding: 12px 8px;
            border-right: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
            font-weight: bolder;
            color: #000000;
        }

        .tabulator .tabulator-cell input,
        .tabulator .tabulator-cell select,
        .tabulator .tabulator-cell .form-select,
        .tabulator .tabulator-cell .form-control {
            font-weight: bold !important;
            color: #000000 !important;
        }

        /* Row hover effect */
        .tabulator-row:hover {
            background-color: rgba(0, 0, 0, .075) !important;
        }

        /* Parent row styling */
        .parent-row {
            background-color: #DFF0FF !important;
            font-weight: 600;
        }

        /* Pagination styling */
        .tabulator .tabulator-footer {
            background: #f4f7fa;
            border-top: 1px solid #e5e7eb;
            font-size: 1rem;
            color: #4b5563;
            padding: 5px;
            height: 90px;
        }

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
    @include('layouts.shared.page-title', [
        'page_title' => 'Amazon Product Review',
        'sub_title' => 'Amazon Product Review',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-3">
                        <!-- Rating Filter -->
                        <div class="d-flex align-items-center gap-2">
                            <label for="rating-filter" class="fw-bold mb-0">Rating Filter:</label>
                            <select id="rating-filter" class="form-select form-select-sm" style="width: 160px;">
                                <option value="">All</option>
                                <option value="lt3_5">Less than 3.5</option>
                                <option value="3_5to4">3.5 to 4</option>
                                <option value="4to4_5">4 to 4.5</option>
                                <option value="gt4_5">4.5 above</option>
                            </select>

                            <!-- SKU Count -->
                            <div class="ms-2 d-flex align-items-center gap-2" id="skuCountDiv">
                                <span class="fw-bold">SKU Count:</span>
                                <span id="skuCount" class="badge bg-primary fs-5">0</span>
                            </div>
                        </div>

                        <!-- Import Button -->
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="fas fa-file-import me-1"></i> Import Excel/CSV
                        </button>
                    </div>

                    <div id="amazon-product-review"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('amazon.product.review.import') }}" method="POST" enctype="multipart/form-data"
                class="modal-content" id="importForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Reviews</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Drag & Drop Area -->
                    <div id="drop-area" class="border border-2 border-primary rounded p-4 text-center mb-3"
                        style="cursor:pointer; position:relative;">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                        <p class="mb-1">Drag & drop your file here, or click to select</p>
                        <input type="file" name="excel_file" id="importFileInput"
                            style="opacity:0;position:absolute;top:0;left:0;width:100%;height:100%;cursor:pointer;"
                            required>
                        <div id="fileName" class="small text-muted"></div>
                    </div>
                    <div id="selectedFilePreview" class="mt-2"></div>
                    <a href="{{ asset('sample_excel/amazon_product_reviews_sample.xlsx') }}" class="btn btn-link">
                        <i class="fas fa-download me-1"></i> Download Sample File
                    </a>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-import me-1"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            const table = new Tabulator("#amazon-product-review", {
                index: "Sku",
                ajaxURL: "/amazon-product-review-data",
                ajaxConfig: "GET",
                layout: "fitData",
                pagination: true,
                paginationSize: 100,
                paginationMode: "local",
                movableColumns: false,
                resizableColumns: true,
                height: "680px",

                // Data filter to hide SKUs with 0 inventory but keep parent rows
                dataFilter: function(data) {
                    return data.filter(function(row) {
                        const sku = row.Sku || '';
                        const inv = parseFloat(row.INV) || 0;
                        const isParent = sku.toUpperCase().includes("PARENT");
                        return isParent || (!isNaN(inv) && inv > 0);
                    });
                },

                // Row formatter to calculate and display Parent row summaries
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data.Sku || '';
                    const isParent = sku.toUpperCase().includes("PARENT");

                    if (isParent) {
                        row.getElement().classList.add("parent-row");

                        // Get all child SKUs for this parent
                        const parent = data.Parent;
                        const tableData = table.getData();
                        const childSkus = tableData.filter(item =>
                            item.Parent === parent && !item.Sku.toUpperCase().includes("PARENT")
                        );

                        // Calculate totals for INV and L30
                        const totalInv = childSkus.reduce((sum, item) => sum + (parseFloat(item.INV) ||
                            0), 0);
                        const totalL30 = childSkus.reduce((sum, item) => sum + (parseFloat(item.L30) ||
                            0), 0);
                        const parentDilPercent = totalInv > 0 ? (totalL30 / totalInv) * 100 : 0;
                        const dilColor = getDilColor(parentDilPercent / 100);

                        // Update Parent row data with calculated totals
                        row.getData().INV = totalInv;
                        row.getData().L30 = totalL30;
                        row.getData().Dil = parentDilPercent;

                        // Remove existing summary if present
                        if (row.getElement().querySelector('.parent-inventory-summary')) {
                            row.getElement().querySelector('.parent-inventory-summary').remove();
                        }

                        // Add summary to Parent row
                        const summaryEl = document.createElement('div');
                        summaryEl.className = 'parent-inventory-summary';
                        summaryEl.style.cssText =
                            'font-size: 12px; color: #666; margin-top: 5px; padding: 2px; background: #f5f5f5;';
                        summaryEl.innerHTML =
                            `Group: ${childSkus.length} SKUs | INV: ${totalInv} | L30: ${totalL30} | Dil%: <span class="dil-percent-value ${dilColor}">${Math.round(parentDilPercent)}%</span>`;

                        row.getElement().querySelector('.tabulator-cell').appendChild(summaryEl);
                    }
                },

                columns: [{
                        title: "Parent",
                        field: "Parent",
                        minWidth: 130,
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search parent.",
                        headerFilterFunc: "like",
                    },
                    {
                        title: "SKU",
                        field: "Sku",
                        minWidth: 130,
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search sku.",
                        headerFilterFunc: "like",
                    },
                    {
                        title: "INV",
                        field: "INV",
                        headerSort: true,
                        titleFormatter: function() {
                            return `<div>
                    INV<br>
                    <span id="total-inv-header" style="font-size:13px;color:white;font-weight:600;"></span>
                </div>`;
                        },
                        formatter: function(cell) {
                            const data = cell.getData();
                            const sku = data.Sku || '';
                            const isParent = sku.toUpperCase().includes("PARENT");
                            const inv = parseFloat(data.INV) || 0;

                            if (isParent) {
                                return `<div class="text-center" style="font-weight: bold;">${inv}</div>`;
                            }
                            return `<div class="text-center">${inv}</div>`;
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        headerSort: true,
                        titleFormatter: function() {
                            return `<div>
                    OV L30<br>
                    <span id="total-l30-header" style="font-size:13px;color:white;font-weight:600;"></span>
                </div>`;
                        },
                        formatter: function(cell) {
                            const data = cell.getData();
                            const sku = data.Sku || '';
                            const isParent = sku.toUpperCase().includes("PARENT");
                            const l30 = parseFloat(data.L30) || 0;

                            if (isParent) {
                                return `<div class="text-center" style="font-weight: bold;">${l30}</div>`;
                            }
                            return `<div class="text-center">${l30}</div>`;
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Dil%",
                        field: "Dil",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const sku = data.Sku || '';
                            const isParent = sku.toUpperCase().includes("PARENT");
                            const inv = parseFloat(data.INV) || 0;
                            const l30 = parseFloat(data.L30) || 0;
                            const dilPercent = inv > 0 ? (l30 / inv) * 100 : 0;
                            const color = getDilColor(dilPercent / 100);

                            return `<div class="text-center"><span class="dil-percent-value ${color}" style="font-weight: ${isParent ? 'bold' : 'normal'}">${Math.round(dilPercent)}%</span></div>`;
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Rating",
                        field: "product_rating",
                        editor: "input",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const sku = data.Sku || '';
                            const isParent = sku.toUpperCase().includes("PARENT");

                            if (isParent) {
                                return `<div class="text-center" style="font-weight: bold;">-</div>`;
                            }

                            const bg = data.ratingBg || "";
                            return `<div style="background-color:${bg}; padding:3px;">${cell.getValue() || ''}</div>`;
                        }
                    },
                    {
                        title: "Reviews count",
                        field: "review_count",
                        editor: "input",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const sku = data.Sku || '';
                            const isParent = sku.toUpperCase().includes("PARENT");

                            if (isParent) {
                                return `<div class="text-center" style="font-weight: bold;">-</div>`;
                            }
                            return cell.getValue() || '';
                        }
                    },
                    {
                        title: "Link",
                        field: "link",
                        width: 150,
                        formatter: function(cell) {
                            const data = cell.getData();
                            const sku = data.Sku || '';
                            const isParent = sku.toUpperCase().includes("PARENT");

                            if (isParent) {
                                return `<div class="text-center" style="font-weight: bold;">-</div>`;
                            }

                            const value = cell.getValue();
                            if (value && value.trim() !== "") {
                                return `<a href="${value}" target="_blank" style="text-decoration:none;">
                        <i class="fa fa-link"></i> Open
                    </a>`;
                            }
                            return "";
                        },
                        editor: "input",
                        editable: true,
                    },
                    {
                        title: "Remarks",
                        field: "remarks",
                        editor: "input",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const sku = data.Sku || '';
                            const isParent = sku.toUpperCase().includes("PARENT");

                            if (isParent) {
                                return `<div class="text-center" style="font-weight: bold;">-</div>`;
                            }
                            return cell.getValue() || '';
                        }
                    },
                    {
                        title: "Competitor Link/ASIN",
                        field: "comp_link",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const sku = data.Sku || '';
                            const isParent = sku.toUpperCase().includes("PARENT");

                            if (isParent) {
                                return `<div class="text-center" style="font-weight: bold;">-</div>`;
                            }

                            const value = cell.getValue();
                            if (value && value.trim() !== "") {
                                return `<a href="${value}" target="_blank" style="text-decoration:none;">
                        <i class="fa fa-link"></i> Open
                    </a>`;
                            }
                            return "";
                        },
                        editor: "input",
                        editable: true,
                    },
                    {
                        title: "Comp Rating",
                        field: "comp_rating",
                        editor: "input",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const sku = data.Sku || '';
                            const isParent = sku.toUpperCase().includes("PARENT");

                            if (isParent) {
                                return `<div class="text-center" style="font-weight: bold;">-</div>`;
                            }
                            return cell.getValue() || '';
                        }
                    },
                    {
                        title: "Comp Reviews count",
                        field: "comp_review_count",
                        editor: "input",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const sku = data.Sku || '';
                            const isParent = sku.toUpperCase().includes("PARENT");

                            if (isParent) {
                                return `<div class="text-center" style="font-weight: bold;">-</div>`;
                            }
                            return cell.getValue() || '';
                        }
                    },
                    {
                        title: "Remarks",
                        field: "comp_remarks",
                        editor: "input",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const sku = data.Sku || '';
                            const isParent = sku.toUpperCase().includes("PARENT");

                            if (isParent) {
                                return `<div class="text-center" style="font-weight: bold;">-</div>`;
                            }
                            return cell.getValue() || '';
                        }
                    },
                    {
                        title: "Negative L90",
                        field: "negation_l90",
                        editor: "input",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const sku = data.Sku || '';
                            const isParent = sku.toUpperCase().includes("PARENT");

                            if (isParent) {
                                return `<div class="text-center" style="font-weight: bold;">-</div>`;
                            }
                            return cell.getValue() || '';
                        }
                    },
                    {
                        title: "Action",
                        field: "action",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const sku = data.Sku || '';
                            const isParent = sku.toUpperCase().includes("PARENT");

                            if (isParent) {
                                return `<div class="text-center" style="font-weight: bold;">-</div>`;
                            }

                            const row = cell.getRow();
                            const skuValue = data.Sku;
                            return `
                    <select class="form-select form-select-sm editable-select" 
                            data-row-id="${skuValue}" 
                            data-type="action"
                            style="width: 90px;">
                        <option value="Pending" ${cell.getValue() === 'Pending' ? 'selected' : ''}>Pending</option>
                        <option value="Resolved" ${cell.getValue() === 'Resolved' ? 'selected' : ''}>Resolved</option>
                    </select>
                `;
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Corrective Action",
                        field: "corrective_action",
                        editor: "input",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const sku = data.Sku || '';
                            const isParent = sku.toUpperCase().includes("PARENT");

                            if (isParent) {
                                return `<div class="text-center" style="font-weight: bold;">-</div>`;
                            }
                            return cell.getValue() || '';
                        }
                    }
                ],

                // Add Parent rows dynamically and process data
                ajaxResponse: function(url, params, response) {
                    let rows = response.data || [];

                    // Step 1: Identify unique Parent values
                    const parentSet = new Set(rows.map(row => row.Parent).filter(parent => parent));
                    const existingParents = new Set(rows.filter(row => row.Sku.toUpperCase().includes(
                        "PARENT")).map(row => row.Parent));

                    // Step 2: Add missing Parent rows
                    parentSet.forEach(parent => {
                        if (!existingParents.has(parent)) {
                            rows.push({
                                Parent: parent,
                                Sku: `${parent} PARENT`,
                                INV: 0,
                                L30: 0,
                                Dil: 0,
                                product_rating: '-',
                                review_count: '-',
                                link: '-',
                                remarks: '-',
                                comp_link: '-',
                                comp_rating: '-',
                                comp_review_count: '-',
                                comp_remarks: '-',
                                negation_l90: '-',
                                action: '-',
                                corrective_action: '-'
                            });
                        }
                    });

                    // Step 3: Sort rows to place Parent rows after their child SKUs
                    rows.sort((a, b) => {
                        const aIsParent = a.Sku.toUpperCase().includes("PARENT");
                        const bIsParent = b.Sku.toUpperCase().includes("PARENT");
                        if (a.Parent === b.Parent) {
                            return aIsParent ? 1 : bIsParent ? -1 : a.Sku.localeCompare(b.Sku);
                        }
                        return a.Parent.localeCompare(b.Parent);
                    });

                    // Step 4: Apply Dil% and rating background logic for non-Parent rows
                    rows.forEach(row => {
                        const sku = row.Sku || '';
                        const isParent = sku.toUpperCase().includes("PARENT");

                        if (!isParent) {
                            const inv = parseFloat(row.INV) || 0;
                            const l30 = parseFloat(row.L30) || 0;
                            if (!isNaN(inv) && inv !== 0 && !isNaN(l30)) {
                                row.dilColor = getDilColor(l30 / inv);
                                row.Dil = (l30 / inv) * 100;
                            } else {
                                row.dilColor = "red";
                                row.Dil = 0;
                            }

                            const rating = parseFloat(row.product_rating);
                            const compRating = parseFloat(row.comp_rating);
                            if (!isNaN(rating) && !isNaN(compRating)) {
                                if (rating < compRating) {
                                    row.ratingBg = "red";
                                } else if (rating > compRating) {
                                    row.ratingBg = "green";
                                } else {
                                    row.ratingBg = "";
                                }
                            } else {
                                row.ratingBg = "";
                            }
                        }
                    });

                    return rows;
                },

                // Event handlers
                dataProcessed: function() {
                    setTimeout(() => updateTotalInvAndL30(table), 100);
                },

                dataLoaded: function() {
                    updateSkuCount();
                },

                cellEdited: function(cell) {
                    const rowData = cell.getRow().getData();
                    const field = cell.getField();
                    const value = cell.getValue();

                    if (!rowData.Sku) return;

                    $.ajax({
                        url: '/amazon-product-reviews/save',
                        type: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            sku: rowData.Sku,
                            field: field,
                            value: value
                        },
                        success: function(response) {
                            console.log(response);
                        },
                        error: function(xhr) {
                            console.error(xhr.responseText);
                        }
                    });
                },

                // Handle changes to editable select fields
                dataChanged: function(data) {
                    // Reapply row formatting to update parent summaries
                    table.getRows().forEach(row => {
                        if (row.getData().Sku.toUpperCase().includes("PARENT")) {
                            row.reformat();
                        }
                    });
                }
            });

            // Handle changes to action select dropdown
            $(document).on('change', '.editable-select', function() {
                const sku = $(this).data('row-id');
                const field = $(this).data('type');
                const value = $(this).val();

                if (!sku) return;

                $.ajax({
                    url: '/amazon-product-reviews/save',
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sku: sku,
                        field: field,
                        value: value
                    },
                    success: function(response) {
                        console.log(response);
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                    }
                });
            });

            // ADDED: Function to refresh parent row summaries when data changes
            table.on("dataChanged", function(data) {
                // Reapply row formatting to update parent summaries
                table.getRows().forEach(row => {
                    if (row.getData().Sku.toUpperCase().includes("PARENT")) {
                        row.reformat();
                    }
                });
            });

            function applyRatingFilter(value) {
                table.clearFilter();

                if (value) {
                    table.setFilter(function(data) {
                        let rating = parseFloat(data.product_rating);
                        if (isNaN(rating)) return false;

                        if (value === "lt3_5") return rating < 3.5;
                        if (value === "3_5to4") return rating >= 3.5 && rating < 4;
                        if (value === "4to4_5") return rating >= 4 && rating < 4.5;
                        if (value === "gt4_5") return rating >= 4.5;
                        return true;
                    });
                }

                updateSkuCount();
            }

            function updateSkuCount() {
                let count = table.getData("active").length;
                document.getElementById("skuCount").textContent = count;
            }

            document.getElementById("rating-filter").addEventListener("change", function() {
                applyRatingFilter(this.value);
            });


            function updateTotalInvAndL30(table) {
                const data = table.getData("active");

                const totalINV = data.reduce((sum, row) => sum + (parseFloat(row["INV"]) || 0), 0);
                const totalL30 = data.reduce((sum, row) => sum + (parseFloat(row["L30"]) || 0), 0);

                document.getElementById("total-inv-header").textContent = totalINV.toLocaleString();
                document.getElementById("total-l30-header").textContent = totalL30.toLocaleString();

            }

            openImportModal();

            function openImportModal() {
                const dropArea = document.getElementById('drop-area');
                const fileInput = document.getElementById('importFileInput');
                const fileName = document.getElementById('fileName');
                const filePreview = document.getElementById('selectedFilePreview');

                dropArea.addEventListener('click', function(e) {
                    if (e.target !== fileInput) {
                        fileInput.click();
                    }
                });

                dropArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    dropArea.classList.add('bg-light');
                });

                dropArea.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    dropArea.classList.remove('bg-light');
                });

                dropArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    dropArea.classList.remove('bg-light');
                    if (e.dataTransfer.files.length) {
                        fileInput.files = e.dataTransfer.files;
                        showFileInfo(e.dataTransfer.files[0]);
                    }
                });

                fileInput.addEventListener('change', () => {
                    if (fileInput.files.length) {
                        showFileInfo(fileInput.files[0]);
                    } else {
                        fileName.textContent = '';
                        filePreview.innerHTML = '';
                    }
                });

                function showFileInfo(file) {
                    fileName.textContent = file.name;
                    filePreview.innerHTML = `
                <div class="alert alert-info py-2 px-3 mb-0 text-start">
                    <strong>File:</strong> ${file.name} <br>
                    <strong>Size:</strong> ${(file.size/1024).toFixed(2)} KB <br>
                    <strong>Type:</strong> ${file.type || 'Unknown'}
                </div>
            `;
                }

                if (fileInput.files.length) {
                    showFileInfo(fileInput.files[0]);
                }
            }

            document.body.style.zoom = "85%";
        });
    </script>
@endsection
