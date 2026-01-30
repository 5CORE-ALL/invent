@extends('layouts.vertical', ['title' => 'To Order Analysis'])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-header {
            background: linear-gradient(90deg, #D8F3F3 0%, #D8F3F3 100%);
            border-bottom: 1px solid #403f3f;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
        }

        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #D8F3F3;
            border-right: 1px solid #262626;
            padding: 16px 10px;
            font-weight: 700;
            color: #1e293b;
            font-size: 1.08rem;
            letter-spacing: 0.02em;
            transition: background 0.2s;
        }

        .tabulator .tabulator-header .tabulator-col:hover {
            background: #D8F3F3;
            color: #2563eb;
        }

        .tabulator-row {
            background-color: #fff !important;
            transition: background 0.18s;
        }

        .tabulator-row:nth-child(even) {
            background-color: #f8fafc !important;
        }

        .tabulator .tabulator-cell {
            text-align: center;
            padding: 14px 10px;
            border-right: 1px solid #262626;
            border-bottom: 1px solid #262626;
            font-size: 1rem;
            color: #22223b;
            vertical-align: middle;
            transition: background 0.18s, color 0.18s;
        }

        .tabulator .tabulator-cell:focus {
            outline: 1px solid #262626;
            background: #e0eaff;
        }

        .tabulator-row:hover {
            background-color: #dbeafe !important;
        }

        .parent-row {
            background-color: #e0eaff !important;
            font-weight: 700;
        }

        #account-health-master .tabulator {
            border-radius: 18px;
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .tabulator .tabulator-row .tabulator-cell:last-child,
        .tabulator .tabulator-header .tabulator-col:last-child {
            border-right: none;
        }

        .tabulator .tabulator-footer {
            background: #f4f7fa;
            border-top: 1px solid #262626;
            font-size: 1rem;
            color: #4b5563;
            padding: 5px;
            height: 100px;
        }

        .tabulator .tabulator-footer:hover {
            background: #e0eaff;
        }

        @media (max-width: 768px) {

            .tabulator .tabulator-header .tabulator-col,
            .tabulator .tabulator-cell {
                padding: 8px 2px;
                font-size: 0.95rem;
            }
        }

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

        .green-bg {
            color: #05bd30 !important;
        }

        .pink-bg {
            color: #ff01d0 !important;
        }

        .red-bg {
            color: #ff2727 !important;
        }
        .to-order-filter-row { gap: 1.25rem; padding: 1rem 0; }
        .filter-item .form-label { white-space: nowrap; font-size: 0.95rem !important; margin-bottom: 0.4rem !important; }
        .filter-item .form-select,
        .filter-item .form-control { min-height: 38px; font-size: 0.95rem; padding: 0.4rem 0.65rem; }
        .filter-item .form-select { min-width: 145px; }
        .to-order-counts-box { padding: 0.75rem 1.25rem; min-width: 200px; }
        .to-order-counts-box .count-label { font-size: 0.85rem; letter-spacing: 0.02em; }
        .to-order-counts-box .count-value { font-size: 2rem; line-height: 1.2; font-weight: 700; }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'To Order Analysis', 'sub_title' => 'To Order Analysis'])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0">To Order Analysis</h4>
                    </div>

                    {{-- Single row: all filters left, counts right --}}
                    <div class="d-flex flex-wrap align-items-end to-order-filter-row mb-4">
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">▶️ Navigation</label>
                            <div class="btn-group" role="group">
                                <button id="play-backward" class="btn btn-light rounded-circle shadow-sm me-2" style="width: 38px; height: 38px;" title="Previous parent"><i class="fas fa-step-backward"></i></button>
                                <button id="play-pause" class="btn btn-light rounded-circle shadow-sm me-2" style="width: 38px; height: 38px; display: none;" title="Pause"><i class="fas fa-pause"></i></button>
                                <button id="play-auto" class="btn btn-primary rounded-circle shadow-sm me-2" style="width: 38px; height: 38px;" title="Play"><i class="fas fa-play"></i></button>
                                <button id="play-forward" class="btn btn-light rounded-circle shadow-sm" style="width: 38px; height: 38px;" title="Next parent"><i class="fas fa-step-forward"></i></button>
                            </div>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">Parent / Sku</label>
                            <select id="row-data-type" class="form-select border border-primary">
                                <option value="all">🔁 Show All</option>
                                <option value="sku">🔹 SKU</option>
                                <option value="parent">🔸 Parent</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">Pending Status</label>
                            <select id="row-data-pending-status" class="form-select border border-primary">
                                <option value="">Color</option>
                                <option value="green">Green <span id="greenCount"></span></option>
                                <option value="yellow">Yellow <span id="yellowCount"></span></option>
                                <option value="red">Red <span id="redCount"></span></option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">🎯 Stage</label>
                            <select id="stage-filter" class="form-select">
                                <option value="">All</option>
                                <option value="to_order_analysis" selected>2 Order</option>
                                <option value="mip">MIP</option>
                                <option value="r2s">R2S</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">🔍 NRP</label>
                            <select id="nrp-filter" class="form-select">
                                <option value="all" selected>All</option>
                                <option value="show_nr">2BDC</option>
                                <option value="show_later">LATER</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">🏢 Supplier</label>
                            <select id="supplier-filter" class="form-select">
                                <option value="">All Suppliers</option>
                                @foreach($allSuppliers ?? [] as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <label for="search-input" class="form-label fw-semibold d-block">🔍 Search</label>
                            <input type="text" id="search-input" class="form-control" placeholder="Search..." style="width: 160px;">
                        </div>

                        <div class="ms-auto d-flex align-items-end">
                            <div class="d-flex align-items-center to-order-counts-box gap-4 rounded border bg-light">
                                <div class="text-center">
                                    <div class="text-muted count-label fw-semibold mb-1">🕒 Pending</div>
                                    <div id="pendingItemsCount" class="count-value text-primary">00</div>
                                </div>
                                <div class="vr" style="height: 40px;"></div>
                                <div class="text-center">
                                    <div class="text-muted count-label fw-semibold mb-1">📦 CBM</div>
                                    <div id="totalCBM" class="count-value text-success">00</div>
                                </div>
                            </div>
                            <div class="filter-item d-none" id="totalApprovedQty-wrap">
                                <label class="form-label fw-semibold mb-1 d-block small">Approved Qty</label>
                                <div id="totalApprovedQty" class="fw-bold text-primary small">00</div>
                            </div>
                            <button class="btn btn-sm btn-danger d-none align-self-end" id="delete-selected-btn">
                                <i class="fas fa-trash-alt me-1"></i> Delete
                            </button>
                        </div>
                    </div>
                    <div id="toOrderAnalysis-table"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form id="reviewForm">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="reviewModalLabel">📝 To Order Review</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">🏭 Parent</label>
                                <input type="text" class="form-control" id="review_parent" name="parent"
                                    readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">🔢 SKU</label>
                                <input type="text" class="form-control" id="review_sku" name="sku" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">🏢 Supplier</label>
                                <input type="text" class="form-control" id="review_supplier" name="supplier"
                                    readonly>
                            </div>

                            <div class="col-md-12">
                                <label for="positive_review" class="form-label">✨ Positive Review <span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control" id="positive_review" name="positive_review" rows="3"
                                    placeholder="Enter positive aspects..." required></textarea>
                            </div>
                            <div class="col-md-12">
                                <label for="negative_review" class="form-label">⚠️ Negative Review <span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control" id="negative_review" name="negative_review" rows="3"
                                    placeholder="Enter areas of concern..." required></textarea>
                            </div>
                            <div class="col-md-12">
                                <label for="improvement" class="form-label">📈 Improvement Required <span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control" id="improvement" name="improvement" rows="3"
                                    placeholder="Enter suggested improvements..." required></textarea>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="date_updated" class="form-label">📅 Date Updated <span
                                            class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_updated" name="date_updated"
                                        required>
                                </div>
                                <div class="col-md-6">
                                    <label for="clink" class="form-label">🔗 Competitor Link</label>
                                    <div class="input-group">
                                        <a href="#" class="btn btn-outline-primary" id="clink"
                                            target="_blank">
                                            <i class="mdi mdi-eye me-1"></i>
                                            View Competitor Link
                                        </a>
                                    </div>
                                    <small class="text-muted mt-1 d-block">Click to view the competitor link</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">❌ Close</button>
                        <button type="submit" class="btn btn-primary">
                            💾 Save Review
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

@endsection
@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.body.style.zoom = "80%";

            document.documentElement.setAttribute("data-sidenav-size", "condensed");

            const globalPreview = Object.assign(document.createElement("div"), {
                id: "image-hover-preview",
            });

            Object.assign(globalPreview.style, {
                position: "fixed",
                zIndex: 9999,
                border: "1px solid #ccc",
                background: "#fff",
                padding: "4px",
                boxShadow: "0 2px 8px rgba(0,0,0,0.2)",
                display: "none",
            });
            document.body.appendChild(globalPreview);

            let hideTimeout;
            let uniqueSuppliers = [];
            let allSuppliers = @json($allSuppliers ?? []);

            const table = new Tabulator("#toOrderAnalysis-table", {
                ajaxURL: "/to-order-analysis/data",
                ajaxConfig: "GET",
                layout: "fitData",
                height: "700px",
                pagination: true,
                paginationSize: 100,
                paginationCounter: "rows",
                movableColumns: false,
                resizableColumns: true,
                columns: [
                    {
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        width: 50,
                        headerSort: false
                    },
                    {
                        title: "#",
                        field: "Image",
                        headerSort: false,
                        formatter: (cell) => {
                            const url = cell.getValue();
                            return url ?
                                `<img src="${url}" data-full="${url}" class="hover-thumb" 
                                   style="width:30px;height:30px;border-radius:6px;object-fit:contain;
                                   box-shadow:0 1px 4px #0001;cursor: pointer;">` :
                                `<span class="text-muted">N/A</span>`;
                        },
                        cellMouseOver: (e, cell) => {
                            clearTimeout(hideTimeout);

                            const img = cell.getElement().querySelector(".hover-thumb");
                            if (!img) return;

                            globalPreview.innerHTML = `<img src="${img.dataset.full}" style="max-width:350px;max-height:350px;">`;
                            globalPreview.style.display = "block";
                            globalPreview.style.top = `${e.clientY + 15}px`;
                            globalPreview.style.left = `${e.clientX + 15}px`;
                        },
                        cellMouseMove: (e) => {
                            globalPreview.style.top = `${e.clientY + 15}px`;
                            globalPreview.style.left = `${e.clientX + 15}px`;
                        },
                        cellMouseOut: () => {
                            hideTimeout = setTimeout(() => {
                                globalPreview.style.display = "none";
                            }, 150);
                        },
                    },
                    {
                        title: "Parent",
                        field: "Parent",
                        headerFilter: "input",
                        headerFilterPlaceholder: " Filter parent...",
                        width: 180,
                        headerFilterLiveFilter: true,
                    },
                    {
                        title: "SKU",
                        field: "SKU", 
                        headerFilter: "input",
                        width: 180,
                        headerFilterPlaceholder: " Filter SKU...",
                        headerFilterLiveFilter: true,
                    },
                    {
                        title: "MOQ",
                        field: "approved_qty",
                        hozAlign: "center",
                        formatter: function (cell) {
                            const value = cell.getValue() || "";
                            
                            const html = `
                                    <div style="display:flex; justify-content:center; align-items:center; width:100%;">
                                        <input type="number" 
                                            class="form-control form-control-sm order_qty" 
                                            value="${value}" 
                                            min="0" max="99999" 
                                            style="width:80px; text-align:center;">
                                    </div>
                                `;

                            setTimeout(() => {
                                const input = cell.getElement().querySelector(".order_qty");
                                if (input) {
                                    input.addEventListener("change", function () {
                                        const newValue = this.value;
                                        saveLinkUpdate(cell, newValue);
                                    });
                                }
                            }, 10);

                            return html;
                        }
                    },
                    {
                        title: "DOA",
                        field: "Date of Appr",
                        formatter: function (cell) {
                            const value = cell.getValue() || "";
                            const rowData = cell.getRow().getData();

                            let daysDiff = null;
                            let bgColor = "";

                            if (value) {
                                let doa = new Date(value);
                                let today = new Date();
                                let diffTime = today - doa;
                                daysDiff = Math.floor(diffTime / (1000 * 60 * 60 * 24));

                                if (daysDiff >= 14) {
                                    bgColor = "color:red; font-weight:700;";
                                } else if (daysDiff >= 7) {
                                    bgColor = "color:#FFC106; font-weight:700;";
                                }
                            }

                            const html = `
                                <div style="display: flex; flex-direction: column; align-items: flex-start;">
                                    <input type="date" class="form-control form-control-sm doa-input" value="${value}" style="width:82px; ${bgColor}">
                                </div>
                            `;

                            setTimeout(() => {
                                const input = cell.getElement().querySelector(".doa-input");
                                if (input) {
                                    input.addEventListener("change", function () {
                                        const newValue = this.value;
                                        saveLinkUpdate(cell, newValue);
                                    });
                                }
                            }, 10);

                            return html;
                        }
                    },
                    {
                        title: "Supplier",
                        field: "Supplier",
                        formatter: function(cell){
                            let value = cell.getValue() || "";
                            let sku = (cell.getRow().getData().SKU || "").replace(/"/g, "&quot;");
                            let list = [...new Set([...(allSuppliers || []), value].filter(Boolean))].sort();
                            let options = list.map(supplier => {
                                let selected = (supplier === value) ? "selected" : "";
                                return `<option value="${(supplier || "").replace(/"/g, "&quot;")}" ${selected}>${(supplier || "").replace(/</g, "&lt;")}</option>`;
                            }).join("");
                            return `
                                <select class="form-select form-select-sm editable-select" data-sku="${sku}" data-column="Supplier" style="width: 140px; max-width: 100%;">
                                    <option value="">-- Select --</option>
                                    ${options}
                                </select>`;
                        }
                    },
                    {
                        title: "Review",
                        field: "Review",
                        formatter: function(cell){
                            const data = cell.getRow().getData();
                            if(data.has_review){
                                return `<button class="btn btn-sm btn-outline-info review-btn" data-action="view"><i class="fas fa-eye"></i> View</button>`;
                            } else {
                                return `<button class="btn btn-sm btn-outline-dark review-btn" data-action="review"><i class="fas fa-plus"></i> Review</button>`;
                            }
                        }
                    },
                    {
                        title: "RFQ Form",
                        field: "RFQ Form Link",
                        formatter: linkFormatter,
                        editor: "input",  
                        hozAlign: "center",
                        cellEdited: function(cell){
                            saveLinkUpdate(cell, cell.getValue());
                        }
                    },
                    {
                        title: "RFQ Report",
                        field: "Rfq Report Link",
                        formatter: linkFormatter,
                        editor: "input",         
                        hozAlign: "center",
                        cellEdited: function(cell){
                            saveLinkUpdate(cell, cell.getValue());
                        }
                    },
                    {
                        title: "Sheet",
                        field: "sheet_link",
                        formatter: "link",
                        formatterParams: {
                            target: "_blank"
                        },
                        visible: false
                    },
                    {
                        title: "Adv date",
                        field: "Adv date",
                        formatter: function (cell) {
                            const value = cell.getValue() || "";
                            const rowData = cell.getRow().getData();

                            const html = `
                                <div style="display: flex; flex-direction: column; align-items: flex-start;">
                                    <input type="date" class="form-control form-control-sm adv_date_input" value="${value}" style="width:82px;">
                                </div>
                            `;

                            setTimeout(() => {
                                const input = cell.getElement().querySelector(".adv_date_input");
                                if (input) {
                                    input.addEventListener("change", function () {
                                        const newValue = this.value;
                                        saveLinkUpdate(cell, newValue);
                                    });
                                }
                            }, 10);

                            return html;
                        }
                    },
                    {
                        title: "Stage",
                        field: "stage",
                        accessor: row => row?.["stage"] ?? null,
                        headerSort: false,
                        formatter: function(cell) {
                            const value = cell.getValue() ?? '';
                            const rowData = cell.getRow().getData();

                            // Determine background color based on value
                            let bgColor = '#fff';
                            if (value === 'to_order_analysis') {
                                bgColor = '#ffc107'; // Yellow
                            } else if (value === 'mip') {
                                bgColor = '#0d6efd'; // Blue
                            } else if (value === 'r2s') {
                                bgColor = '#198754'; // Green
                            }

                            return `
                        <select class="form-select form-select-sm editable-select"
                            data-type="Stage"
                            data-sku='${rowData["SKU"]}'
                            data-parent='${rowData["Parent"]}'
                            style="width: auto; min-width: 100px; padding: 4px 24px 4px 8px;
                                font-size: 0.875rem; border-radius: 4px; border: 1px solid #dee2e6;
                                background-color: ${bgColor}; color: #000;">
                            <option value="">Select</option>
                            <option value="appr_req" ${value === 'appr_req' ? 'selected' : ''} style="background-color: #fff; color: #000;">Appr. Req</option>
                            <option value="mip" ${value === 'mip' ? 'selected' : ''} style="background-color: #0d6efd; color: #000;">MIP</option>
                            <option value="r2s" ${value === 'r2s' ? 'selected' : ''} style="background-color: #198754; color: #000;">R2S</option>
                            <option value="transit" ${value === 'transit' ? 'selected' : ''} style="background-color: #fff; color: #000;">Transit</option>
                            <option value="all_good" ${value === 'all_good' ? 'selected' : ''} style="background-color: #fff; color: #000;">😊 All Good</option>
                            <option value="to_order_analysis" ${value === 'to_order_analysis' ? 'selected' : ''} style="background-color: #ffc107; color: #000;">2 Order</option>
                        </select>
                    `;
                        }
                    },
                    {
                        title: "NRP",
                        field: "nr",
                        accessor: row => {
                            const val = row?.["nr"];
                            // Return null/undefined as empty string, but preserve actual values
                            if (val === null || val === undefined) return '';
                            // Convert to string and normalize
                            const strVal = String(val);
                            const normalized = strVal.trim().toUpperCase();
                            // Return normalized value (even if empty string)
                            return normalized;
                        },
                        headerSort: false,
                        formatter: function(cell, formatterParams, onRendered) {
                            const rowData = cell.getRow().getData();
                            let value = cell.getValue();
                            if (value === null || value === undefined || value === '') value = rowData["nr"];
                            if (value === null || value === undefined) value = ''; else value = String(value).trim().toUpperCase();
                            if (!value || value === '') value = 'REQ';
                            if (value !== 'REQ' && value !== 'NR' && value !== 'LATER') value = 'REQ';
                            const sku = rowData["SKU"] || '', parent = rowData["Parent"] || '';
                            let bgColor = '#ffffff', textColor = '#000000';
                            if (value === 'NR') { bgColor = '#dc3545'; textColor = '#ffffff'; }
                            else if (value === 'REQ') { bgColor = '#28a745'; textColor = '#000000'; }
                            else if (value === 'LATER') { bgColor = '#ffc107'; textColor = '#000000'; }
                            const html = `
                                <select class="form-select form-select-sm editable-select" data-type="NR" data-sku='${sku}' data-parent='${parent}'
                                    style="width: auto; min-width: 85px; padding: 4px 8px; font-size: 0.875rem; border-radius: 4px; border: 1px solid #dee2e6; background-color: ${bgColor}; color: ${textColor};">
                                    <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>REQ</option>
                                    <option value="NR" ${value === 'NR' ? 'selected' : ''}>2BDC</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>LATER</option>
                                </select>`;
                            if (onRendered) onRendered(function() {
                                const el = cell.getElement().querySelector('select');
                                if (el) el.value = value;
                            });
                            return html;
                        }
                    },
                ],
                ajaxResponse: (url, params, response) => {
                    let data = response.data;

                    // Backend already filters NR and LATER based on showNR/showLATER params
                    // So we only filter for qty, parent, and stage here
                    let filtered = data.filter(item => {
                        let qty = parseFloat(item.approved_qty) || 0;
                        let isParent = item.SKU && item.SKU.startsWith("PARENT");
                        let isMfrg = item.stage && item.stage.trim().toLowerCase() === "mip";

                        return qty > 0 && !isParent && !isMfrg;
                    });

                    uniqueSuppliers = [...new Set(filtered.map(item => item.Supplier))].filter(Boolean);
                    return filtered;
                },
            });

            table.on("rowSelectionChanged", function(data, rows) {
                if (data.length > 0) {
                    $('#delete-selected-btn').removeClass('d-none');
                } else {
                    $('#delete-selected-btn').addClass('d-none');
                }
            });

            deleteWithSelect();

            function deleteWithSelect() {
                const deleteBtn = document.getElementById('delete-selected-btn');

                table.on("rowSelectionChanged", function(data, rows) {
                    deleteBtn.disabled = data.length === 0;
                });

                deleteBtn.addEventListener('click', function() {
                    const selectedRows = table.getSelectedRows();

                    if (selectedRows.length === 0) {
                        alert("Please select rows to delete.");
                        return;
                    }

                    if (!confirm(`Are you sure you want to delete ${selectedRows.length} row(s)?`)) return;

                    const idsToDelete = selectedRows.map(row => row.getData().id);

                    fetch('/to-order-analysis/delete', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                ids: idsToDelete
                            }),
                        })
                        .then(res => res.json())
                        .then(response => {
                            if (response.success) {
                                selectedRows.forEach(row => row.delete());
                            } else {
                                alert('Deletion failed');
                            }
                        })
                        .catch(() => alert('Error deleting rows'));
                });
            }

            function linkFormatter(cell) {
                let url = cell.getValue() || "";
                if (url && url.trim() !== "") {
                    return `
                        <div style="align-items:center;">
                            <a href="${url}" target="_blank" class="btn btn-sm btn-outline-primary" 
                            title="Open Link">
                                <i class="mdi mdi-link"></i> Open
                            </a>
                        </div>
                    `;
                }
            }

            // edit field updated
            function saveLinkUpdate(cell, value) {
                let sku = cell.getRow().getData().SKU;
                let column = cell.getColumn().getField();

                fetch('/update-link', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        sku: sku,
                        column: column,
                        value: value
                    })
                })
                .then(res => res.json())
                .then(res => {
                    if (!res.success) {
                        alert("Error: " + res.message);
                    }
                })
                .catch(err => console.error(err));
            }

            // Reusable AJAX call for forecast data updates
            function updateForecastField(data, onSuccess = () => {}, onFail = () => {}) {
                $.post('/update-forecast-data', {
                    ...data,
                    _token: $('meta[name="csrf-token"]').attr('content')
                }).done(res => {
                    if (res.success) {
                        console.log('Saved:', res.message);
                        onSuccess();
                    } else {
                        console.warn('Not saved:', res.message);
                        onFail();
                    }
                }).fail(err => {
                    console.error('AJAX failed:', err);
                    alert('Error saving data.');
                    onFail();
                });
            }

            // Handle editable select fields
            $(document).off('change', '.editable-select').on('change', '.editable-select', function() {
                const $el = $(this);
                const sku = $el.data('sku');
                const parent = $el.data('parent');
                const column = $el.data('column'); // For other columns like Supplier, nrl
                const field = $el.data('type'); // For Stage column
                const value = $el.val().trim();
                
                // Handle Stage and NR columns using updateForecastField
                if (field === "Stage" || field === "NR") {
                    // Update background color immediately
                    let bgColor = '#fff';
                    if (value === 'to_order_analysis') {
                        bgColor = '#ffc107'; // Yellow
                    } else if (value === 'mip') {
                        bgColor = '#0d6efd'; // Blue
                    } else if (value === 'r2s') {
                        bgColor = '#198754'; // Green
                    }
                    $el.css({
                        'background-color': bgColor,
                        'color': '#000'
                    });
                    const table = Tabulator.findTable("#toOrderAnalysis-table")[0];
                    if (table) {
                        const row = table.searchRows("SKU", "=", sku)[0];
                        const orderQty = row ? row.getData()["approved_qty"] : null;
                        
                        if (!orderQty || orderQty === "0" || parseInt(orderQty) === 0) {
                            alert("MOQ cannot be empty or zero.");
                            $el.val('');
                            return;
                        }
                    }

                    updateForecastField({
                        sku,
                        parent,
                        column: field,
                        value: value
                    }, function() {
                        // Update cell after successful save
                        const table = Tabulator.findTable("#toOrderAnalysis-table")[0];
                        if (table) {
                            const row = table.searchRows("SKU", "=", sku)[0];
                            if (row) {
                                if (field === "Stage") {
                                    // Update row data - this will automatically trigger formatter
                                    row.update({ stage: value }, true);
                                    // Filter table to show only rows with this stage
                                    if (value && value !== '') {
                                        table.setFilter("stage", "=", value);
                                    } else {
                                        table.clearFilter();
                                    }
                                } else if (field === "NR") {
                                    // Update row data - this will automatically trigger formatter
                                    row.update({ nr: value }, true);
                                }
                            }
                        }

                        // Handle MIP stage (equivalent to Mfrg Progress)
                        if (value === "mip") {
                            const table = Tabulator.findTable("#toOrderAnalysis-table")[0];
                            if (!table) return;

                            const row = table.searchRows("SKU", "=", sku)[0];
                            if (!row) return;

                            const rowData = row.getData();
                            const payload = {
                                parent: rowData.Parent || "",
                                sku: rowData.SKU || "",
                                order_qty: rowData.approved_qty || "",
                                supplier: rowData.Supplier || "",
                                adv_date: rowData["Adv date"] || ""
                            };

                            fetch("/mfrg-progresses/insert", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/json",
                                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body: JSON.stringify(payload)
                            }).then(r => r.json()).then(insertRes => {
                                if (insertRes.success) {
                                    row.delete();
                                }
                            });
                        }
                    }, function() {
                        alert('Failed to save ' + field + '.');
                    });
                } else if (column === 'nrl') {
                    // Legacy nrl column - convert to NR and use updateForecastField
                    updateForecastField({
                        sku,
                        parent: parent || '',
                        column: 'NR',
                        value: value
                    }, function() {
                        const table = Tabulator.findTable("#toOrderAnalysis-table")[0];
                        if (table) {
                            const row = table.searchRows("SKU", "=", sku)[0];
                            if (row) {
                                row.update({ nr: value });
                                row.reformat();
                            }
                        }
                    }, function() {
                        alert('Failed to save NRP.');
                    });
                } else {
                    // Handle other columns (Supplier, RFQ links, Adv date, etc.) using /update-link endpoint
                    fetch('/update-link', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ 
                            sku,
                            column, 
                            value 
                        })
                    })
                    .then(res => {
                        if (!res.ok) {
                            return res.json().then(data => { throw new Error(data.message || 'Request failed'); }).catch(() => { throw new Error('Request failed'); });
                        }
                        return res.json();
                    })
                    .then(result => {
                        if (!result.success) {
                            alert('Update failed: ' + (result.message || 'Unknown error'));
                            return;
                        }
                        // Update the row in the table so the UI reflects the change
                        const tbl = Tabulator.findTable("#toOrderAnalysis-table")[0];
                        if (tbl && column) {
                            const row = tbl.searchRows("SKU", "=", sku)[0];
                            if (row) {
                                const fieldMap = { 'Supplier': 'Supplier', 'Adv date': 'Adv date', 'RFQ Form Link': 'RFQ Form Link', 'Rfq Report Link': 'Rfq Report Link' };
                                const field = fieldMap[column] || column;
                                row.update({ [field]: value }, true);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Network error:', error);
                        alert('Error saving: ' + (error.message || 'Please try again.'));
                    });
                }
            });

            let supplierKeys = [];
            let currentIndex = 0;
            let navigationEnabled = false;

            // Update unique suppliers from table
            function updateSupplierKeys() {
                const tableData = table.getData();
                supplierKeys = [...new Set(tableData.map(r => r.Supplier).filter(Boolean))];
            }

            // Get DOA color
            function getRowColor(row) {
                const value = row["Date of Appr"];
                if (!value) return "";
                const doa = new Date(value);
                const today = new Date();
                const diffDays = Math.floor((today - doa) / (1000*60*60*24));
                if(diffDays>=14) return "red";
                if(diffDays>=7) return "yellow";
                return "green";
            }

            // Update counts & totals based on filtered rows
            function updateCounts() {
                const tableData = table.getData("active"); 
                let green=0, yellow=0, red=0;
                let totalApproved=0, pendingItems=0, totalCBM=0;

                tableData.forEach(row => {
                    const color = getRowColor(row);
                    if(color === "green") green++;
                    else if(color === "yellow") yellow++;
                    else if(color === "red") red++;

                    const qty = parseFloat(row["approved_qty"]) || 0;
                    totalApproved += qty;
                    if(qty > 0) pendingItems++;

                    const cbm = parseFloat(row["total_cbm"]) || 0;
                    totalCBM += cbm;
                });

                // Update the display counts immediately
                document.getElementById("greenCount").innerText = `(${green})`;
                document.getElementById("yellowCount").innerText = `(${yellow})`;
                document.getElementById("redCount").innerText = `(${red})`;
                document.getElementById("pendingItemsCount").innerText = pendingItems.toString();
                document.getElementById("totalApprovedQty").innerText = totalApproved.toString();
                document.getElementById("totalCBM").innerText = totalCBM.toFixed(0);
            }

            // Apply all filters + optional supplier override (from nav play uses supplierOverride)
            function applyFilters(supplierOverride = null) {
                const type = document.getElementById("row-data-type").value;
                const pending = document.getElementById("row-data-pending-status").value;
                const stage = document.getElementById("stage-filter").value.toLowerCase().trim();
                const searchText = document.getElementById("search-input").value.trim().toLowerCase();
                const supplierFilterEl = document.getElementById("supplier-filter");
                const supplierFilter = supplierOverride != null ? supplierOverride : (supplierFilterEl ? supplierFilterEl.value.trim() : '');

                table.clearFilter(true);

                table.setFilter(row => {
                    let keep = true;

                    if (type === 'parent') keep = keep && row.is_parent;
                    else if (type === 'sku') keep = keep && !row.SKU.startsWith("PARENT");

                    if (stage) keep = keep && (row.stage || '').toLowerCase() === stage;
                    if (pending) keep = keep && getRowColor(row) === pending;
                    if (supplierFilter) keep = keep && (row.Supplier || '').trim().toLowerCase() === supplierFilter.toLowerCase();
                    if (searchText) keep = keep && Object.values(row).some(val => val && val.toString().toLowerCase().includes(searchText));

                    return keep;
                });

                setTimeout(updateCounts, 0);
            }

            function enableNavigation() {
                navigationEnabled = true;
                document.getElementById("play-auto").style.display = "none";
                document.getElementById("play-pause").style.display = "inline-block";
            }

            function disableNavigation() {
                navigationEnabled = false;
                document.getElementById("play-auto").style.display = "inline-block";
                document.getElementById("play-pause").style.display = "none";
                applyFilters();
            }

            function nextSupplier() {
                updateSupplierKeys();
                if(supplierKeys.length === 0) return;

                if(currentIndex >= supplierKeys.length) currentIndex = 0;
                applyFilters(supplierKeys[currentIndex]);
                currentIndex++;
            }

            function previousSupplier() {
                updateSupplierKeys();
                if(supplierKeys.length === 0) return;

                currentIndex--;
                if(currentIndex < 0) currentIndex = supplierKeys.length - 1;
                applyFilters(supplierKeys[currentIndex]);
            }

            function debounce(func, wait=300) {
                let timeout;
                return function(...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                }
            }

            // Event Listeners
            document.getElementById("play-auto").addEventListener("click", enableNavigation);
            document.getElementById("play-pause").addEventListener("click", disableNavigation);
            document.getElementById("play-forward").addEventListener("click", nextSupplier);
            document.getElementById("play-backward").addEventListener("click", previousSupplier);

            // Filter change events
            document.getElementById("row-data-type").addEventListener("change", () => applyFilters());
            document.getElementById("row-data-pending-status").addEventListener("change", () => applyFilters());
            document.getElementById("stage-filter").addEventListener("change", () => applyFilters());
            document.getElementById("supplier-filter").addEventListener("change", () => applyFilters());
            document.getElementById("search-input").addEventListener("input", debounce(() => applyFilters(), 300));

            // Set default stage filter to "2 Order"
            document.getElementById("stage-filter").value = "to_order_analysis";

            // Table events
            table.on("dataLoaded", function() {
                updateSupplierKeys();
                currentIndex = 0;
                // Set default stage filter to "2 Order" on data load
                document.getElementById("stage-filter").value = "to_order_analysis";
                applyFilters();
            });

            table.on("dataFiltered", updateCounts);
            table.on("dataSorted", updateCounts);
            table.on("dataChanged", updateCounts);
            table.on("cellEdited", updateCounts);

            // add and edit review
            document.addEventListener("click", function(e){
                if(e.target && e.target.classList.contains("review-btn")){
                    const row = Tabulator.findTable("#toOrderAnalysis-table")[0].getRow(e.target.closest(".tabulator-row"));
                    if(!row) return;
                    const rowData = row.getData();

                    const action = e.target.getAttribute("data-action");

                    document.getElementById("review_parent").value = rowData.Parent || "";
                    document.getElementById("review_sku").value = rowData.SKU || "";
                    document.getElementById("review_supplier").value = rowData.Supplier || "";
                    document.getElementById("positive_review").value = rowData.positive_review || "";
                    document.getElementById("negative_review").value = rowData.negative_review || "";
                    document.getElementById("improvement").value = rowData.improvement || "";
                    document.getElementById("date_updated").value = rowData.date_updated || "";
                    document.getElementById("clink").href = rowData.Clink || "#";

                    const reviewModal = new bootstrap.Modal(document.getElementById("reviewModal"));
                    reviewModal.show();
                }
            });

            $('#reviewForm').on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                $.ajax({
                    url: '{{ route('save.to_order_review') }}',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    success: res => {
                        if (res.success) {
                            alert('Review saved successfully!');
                            $('#reviewModal').modal('hide');
                        } else {
                            alert('Failed to save review: ' + (res.message || 'Unknown error'));
                        }
                    },
                    error: xhr => {
                        alert('Error saving review: ' + (xhr.responseJSON?.message ||
                            'Unknown error occurred'));
                    }
                });
            });

            globalPreview.addEventListener("mouseenter", () => clearTimeout(hideTimeout));
            globalPreview.addEventListener("mouseleave", () => {
                globalPreview.style.display = "none";
            });

            // NRP Filter dropdown
            document.getElementById('nrp-filter').addEventListener('change', function() {
                reloadTableWithFilters();
            });

            function reloadTableWithFilters() {
                const stage = document.getElementById("stage-filter").value;
                const searchText = document.getElementById("search-input").value.trim();
                const nrpFilter = document.getElementById("nrp-filter").value;
                
                let showNR = '0';
                let showLATER = '0';
                
                if (nrpFilter === 'show_nr') {
                    showNR = '1';
                } else if (nrpFilter === 'show_later') {
                    showLATER = '1';
                }
                
                // Update AJAX URL with parameters
                const params = new URLSearchParams({
                    stage: stage || '',
                    search: searchText || '',
                    showNR: showNR,
                    showLATER: showLATER
                });
                
                table.setData('/to-order-analysis/data?' + params.toString())
                    .then(() => {
                        updateCounts();
                    })
                    .catch(err => console.error('Error reloading table:', err));
            }
        });
    </script>
@endsection
