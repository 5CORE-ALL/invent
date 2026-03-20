@extends('layouts.vertical', ['title' => 'Review Master'])

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    .tabulator-tableholder{
        height: calc(100% - 104px);
        max-height: calc(92% - 38px) !important;
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

    .btn-soft-primary {
        background-color: rgba(85, 110, 230, 0.1);
        color: #556ee6;
        border: none;
    }
    .btn-soft-primary:hover {
        background-color: #556ee6;
        color: white;
    }
    .btn-soft-success {
        background-color: rgba(52, 195, 143, 0.1);
        color: #34c38f;
        border: none;
    }
    .btn-soft-success:hover {
        background-color: #34c38f;
        color: white;
    }
    .filter-group select {
        border-radius: 6px;
        border-color: #dee2e6;
    }
    .filter-group select:focus {
        border-color: #556ee6;
        box-shadow: 0 0 0 0.2rem rgba(85, 110, 230, 0.25);
    }
    .custom-date-range input {
        min-width: 130px;
    }
    .custom-date-range.d-none {
        opacity: 0;
        pointer-events: none;
    }
    .custom-date-range:not(.d-none) {
        opacity: 1;
        pointer-events: auto;
        transition: opacity 0.3s ease;
    }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Review Master', 'sub_title' => 'Review Master'])

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <!-- Top Controls Row -->
                @php
                    $previousUrl = url()->previous();
                    $currentUrl = url()->current();
                    $fallbackUrl = route('review.masters.index');
                @endphp

                <div class="mb-3">
                    <a href="{{ $previousUrl !== $currentUrl ? $previousUrl : $fallbackUrl }}" 
                    class="btn btn-outline-primary d-inline-flex align-items-center">
                        <i class="fas fa-arrow-left me-2"></i> Go Back
                    </a>
                </div>


                <div class="row mb-4">
                    <div class="col-12">
                        <!-- Main Controls Container -->
                        <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between p-3 bg-light rounded-3 border">
                            <!-- Left Side Controls -->
                            <div class="d-flex flex-wrap gap-3 align-items-center flex-grow-1">
                                <!-- Date Filter Group -->
                                <div class="filter-group d-flex align-items-center gap-2 flex-wrap w-100">
                                    <!-- Date Filter -->
                                    <span class="text-muted"><i class="fas fa-calendar-alt"></i></span>
                                    <select id="quick-date-filter" class="form-select form-select-sm" style="width: 150px;">
                                        <option value="">All Time</option>
                                        <option value="7">Last 7 days</option>
                                        <option value="30">Last 30 days</option>
                                        <option value="custom">Custom Range</option>
                                    </select>
                                    <div class="custom-date-range d-none" id="custom-date-range">
                                        <div class="input-group input-group-sm">
                                            <input type="date" id="filter-start-date" class="form-control" placeholder="Start Date" style="width: 130px;">
                                            <span class="input-group-text"><i class="fas fa-arrow-right"></i></span>
                                            <input type="date" id="filter-end-date" class="form-control" placeholder="End Date" style="width: 130px;">
                                        </div>
                                    </div>
                                    <!-- Star Rating Filter -->
                                    <select class="form-select form-select-sm" id="starFilter" style="width: 170px;" onchange="handleStarFilter(this.value)" hidden>
                                        <option value="all">🚩 All Negative Reviews</option>
                                        <option value="1">⭐ 1-star Reviews</option>
                                        <option value="2">⭐⭐ 2-star Reviews</option>
                                        <option value="3">⭐⭐⭐ 3-star Reviews</option>
                                    </select>
                                    <!-- Status Filter -->
                                    <select id="status-filter" class="form-select form-select-sm" style="width: 170px;" hidden>
                                        <option value="">✔️ Resolved vs Unresolved</option>
                                        <option value="Resolved">✔️ Resolved</option>
                                        <option value="Pending">🕒 Pending</option>
                                        <option value="pending_percentage" disabled>📊 Pending %</option>
                                    </select>
                                    <!-- Resolution Metrics -->
                                    <div class="d-flex align-items-center gap-3 ms-3 d-none">
                                        <span class="fw-semibold text-secondary">
                                            <i class="fas fa-check-circle text-primary me-1"></i> Actions:
                                            <span id="total-actions" class="fw-bold text-primary ms-1">0</span>
                                        </span>
                                        <span class="fw-semibold text-secondary">
                                            <i class="fas fa-stopwatch text-success me-1"></i> Avg Time:
                                            <span id="avg-action-time" class="fw-bold text-success ms-1">0 days</span>
                                        </span>
                                    </div>
                                    <div class="form-group mb-2" hidden>
                                        <label for="negativityFilter" class="form-label">📊 Top Marketplace by Negativity</label>
                                        <select id="negativityFilter" class="form-select">
                                            <option value="">All</option> <!-- Default -->
                                        </select>
                                    </div>

                                    <!-- Export/Import Buttons -->
                                    <div class="d-flex gap-2 ms-auto">
                                        <a href="{{ route('negative.reviews.export') }}" class="btn btn-success ">
                                            <i class="fas fa-file-export me-1"></i>Export Excel/CSV
                                        </a>
                                        <button type="button" class="btn btn-primary " data-bs-toggle="modal" data-bs-target="#importModal">
                                            <i class="fas fa-file-import me-1"></i>Import Excel/CSV
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="selected-rating" value="{{ request()->get('rating') }}">
                <input type="hidden" id="selected-status" value="{{ request()->get('action_status') }}">
                <input type="hidden" id="selected-marketplace" value="{{ request()->get('marketplace') }}">
                <!-- Review Master Table -->
                <div id="review-master-table"></div>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('negative.reviews.import') }}" method="POST" enctype="multipart/form-data" class="modal-content" id="importForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import Reviews</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Drag & Drop Area -->
                <div id="drop-area" class="border border-2 border-primary rounded p-4 text-center mb-3" style="cursor:pointer; position:relative;">
                    <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                    <p class="mb-1">Drag & drop your file here, or click to select</p>
                    <input type="file" name="review_file" id="importFileInput" style="opacity:0;position:absolute;top:0;left:0;width:100%;height:100%;cursor:pointer;" required>
                    <div id="fileName" class="small text-muted"></div>
                </div>
                <div id="selectedFilePreview" class="mt-2"></div>
                <a href="{{ asset('sample_excel/negative_reviews_template.xlsx') }}" class="btn btn-link">
                    <i class="fas fa-download me-1"></i> Download Sample File
                </a>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-import me-1"></i> Import
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection
@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    let table;
    let fullTableData = [];
    document.addEventListener("DOMContentLoaded", function() {
        document.body.style.zoom = "0.86";
        const rating = document.getElementById("selected-rating").value;
        const status = document.getElementById("selected-status").value;
        const marketplace = document.getElementById("selected-marketplace").value;
        table = new Tabulator("#review-master-table", {
            ajaxURL: "/negative-reviews/data",
            ajaxParams: {
                rating: rating || null,
                action_status: status || null,
                marketplace: marketplace || null
            },
            layout: "fitDataFill",
            pagination: true,
            paginationSize: 50,
            paginationMode: "local",
            movableColumns: false,
            resizableColumns: true,
            height: "600px",
            columns: [
                { title: "📅 Date", field: "review_date", sorter: "date" },
                { title: "🛒 Marketplace", field: "marketplace" },
                { title: "🔢 SKU", field: "sku" },
                {
                    title: "⭐ Rating",
                    field: "rating",
                    formatter: function (cell) {
                        const value = parseInt(cell.getValue()) || 0;

                        let bgColor = "#f8d7da"; // red
                        let textColor = "#721c24";

                        if (value >= 4) {
                            bgColor = "#d4edda"; // green
                            textColor = "#155724";
                        } else if (value >= 2) {
                            bgColor = "#fff3cd"; // yellow
                            textColor = "#856404";
                        }

                        return `
                            <select class="form-select form-select-sm auto-save"
                                data-column="rating"
                                style="min-width: 90px; background-color: ${bgColor}; color: ${textColor};">
                                <option value="">Select</option>
                                <option value="1" ${value === 1 ? "selected" : ""}>⭐</option>
                                <option value="2" ${value === 2 ? "selected" : ""}>⭐ ⭐</option>
                                <option value="3" ${value === 3 ? "selected" : ""}>⭐ ⭐ ⭐</option>
                                <option value="4" ${value === 4 ? "selected" : ""}>⭐ ⭐ ⭐ ⭐</option>
                                <option value="5" ${value === 5 ? "selected" : ""}>⭐ ⭐ ⭐ ⭐ ⭐</option>
                            </select>
                        `;
                    },
                    hozAlign: "center",
                },
                { 
                    title: "📂 Category",
                    field: "review_category",
                    formatter: function(cell) {
                        const value = cell.getValue() || "";
                        const row = cell.getRow();
                        const data = row.getData();

                        return `
                            <select class="form-select form-select-sm auto-save"
                                data-sku="${data.sku}" 
                                data-column="review_category"
                                style="min-width: 130px; color: black;">
                                <option value="Product Issues" ${value === 'Product Issues' ? 'selected' : ''}>Product Issues</option>
                                <option value="Delivery Issues" ${value === 'Delivery Issues' ? 'selected' : ''}>Delivery Issues</option>
                                <option value="Listing Accuracy" ${value === 'Listing Accuracy' ? 'selected' : ''}>Listing Accuracy</option>
                                <option value="Misuse/External" ${value === 'Misuse/External' ? 'selected' : ''}>Misuse/External</option>
                                <option value="Support Issues" ${value === 'Support Issues' ? 'selected' : ''}>Support Issues</option>
                            </select>
                        `;
                    },
                    hozAlign: "center",
                },
                { title: "📝 Review Text", field: "review_text", editor: "textarea" },
                { title: "📝 Summary", field: "review_summary", editor: "textarea" },
                { title: "👤 Reviewer", field: "reviewer_name", editor: "input", hozAlign: "center" },
                {
                    title: "✅ Status",
                    field: "action_status",
                    formatter: function (cell) {
                        const value = cell.getValue() || "";
                        let bgColor = "#fff3cd"; // default: warning yellow
                        let textColor = "#000000";

                        if (value === "Resolved") {
                            bgColor = "#d4edda"; // green
                            textColor = "#155724";
                        } else if (value === "Pending") {
                            bgColor = "#fff3cd"; // warning yellow
                            textColor = "#856404";
                        }

                        return `
                            <select class="form-select form-select-sm auto-save"
                                data-column="action_status"
                                style="min-width: 110px; background-color: ${bgColor}; color: ${textColor};">
                                <option value="">Select</option>
                                <option value="Pending" ${value === 'Pending' ? 'selected' : ''}>Pending</option>
                                <option value="Resolved" ${value === 'Resolved' ? 'selected' : ''}>Resolved</option>
                            </select>
                        `;
                    },
                    hozAlign: "center",
                },
                { title: "⚙️ Action Taken", field: "action_taken", editor: "input" },
                { 
                    title: "🗓️ Action Date", 
                    field: "action_date", 
                    sorter: "date", 
                    editor: "date", 
                    hozAlign: "center",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return "";
                        return new Date(value).toLocaleDateString();
                    }
                },
            ],
            ajaxResponse: function(url, params, response){
                fullTableData = response; // store full unfiltered data once
                return response;
            },
        });
        table.on("cellEdited", function(cell){
            const field = cell.getField();
            const value = cell.getValue();
            const row = cell.getRow();
            const data = row.getData();

            fetch('/update-review-field', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    sku: data.sku,
                    column: field,
                    value: value
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    console.log(`✅ Saved ${field} = ${value} for SKU: ${data.sku}`);
                    // Manually set the updated value in case it doesn't auto-update
                    cell.setValue(value);
                } else {
                    console.error("❌ Error:", res.message);
                }
            })
            .catch(err => {
                console.error("❌ Fetch error:", err);
            });
        });

        table.on("renderComplete", function () {
            updateStarCounts();
            updateStatusFilterOptions();
            calculateResolutionStats();
            populateNegativityDropdown();
        });

        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('auto-save')) {
                const select = e.target;
                const column = select.getAttribute('data-column');
                const value = select.value;

                const cellEl = select.closest('.tabulator-cell');
                const rowEl = select.closest('.tabulator-row');

                if (!cellEl || !rowEl) return;

                const rowComponent = table.getRow(rowEl);  // <- use your actual Tabulator instance
                const data = rowComponent.getData();

                fetch('/update-review-field', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        sku: data.sku,
                        column: column,
                        value: value
                    })
                })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        console.log(`✅ Saved ${column} = ${value} for SKU: ${data.sku}`);
                        rowComponent.getTable().replaceData([
                            ...rowComponent.getTable().getData().map(item => {
                                if (item.sku === data.sku) {
                                return { ...item, [column]: value };
                                }
                                return item;
                            })
                        ]);

                    }
                })
                .catch(err => {
                    console.error("❌ Error saving select:", err);
                });
            }
        });


        openImportModal();
        setupQuickDateFilter();

        document.getElementById("status-filter").addEventListener("change", function () {
            const value = this.value;

            table.clearFilter(true);

            if (value === "Resolved" || value === "Pending") {
                table.setFilter("action_status", "=", value);
            }
        });
        document.getElementById("negativityFilter").addEventListener("change", function () {
            const selected = this.value;

            table.clearFilter(true); // Clear existing filters

            if (selected) {
                const filtered = fullTableData.filter(row =>
                    row.marketplace === selected
                );
                table.setData(filtered);
            } else {
                table.setData(fullTableData); // show ALL negative reviews
            }
        });
    });

    function populateNegativityDropdown() {
        const select = document.getElementById("negativityFilter");
        const selected = select.value; // preserve current selection

        const negativityMap = {};

        fullTableData.forEach(row => {
            if (row.rating <= 2) {
                const market = row.marketplace || "Unknown";
                negativityMap[market] = (negativityMap[market] || 0) + 1;
            }
        });

        const sorted = Object.entries(negativityMap).sort((a, b) => b[1] - a[1]);

        select.innerHTML = `<option value="">All</option>`;

        sorted.forEach(([market, count]) => {
            const option = document.createElement("option");
            option.value = market;
            option.textContent = `${market} (${count})`;

            if (market === selected) {
                option.selected = true; // re-select if it was previously selected
            }

            select.appendChild(option);
        });
    }

    function calculateResolutionStats() {
        const data = table.getData("active"); // filtered/visible rows

        let totalActions = 0;
        let totalDays = 0;

        data.forEach(row => {
            if (row.action_date && row.review_date) {
                const actionDate = new Date(row.action_date);
                const reviewDate = new Date(row.review_date);

                const diffInTime = actionDate.getTime() - reviewDate.getTime();
                const diffInDays = diffInTime / (1000 * 3600 * 24);

                if (diffInDays >= 0) {
                    totalActions++;
                    totalDays += diffInDays;
                }
            }
        });

        const avgDays = totalActions > 0 ? (totalDays / totalActions).toFixed(1) : "N/A";

        // ✅ Show result in DOM
        document.getElementById("total-actions").textContent = totalActions;
        document.getElementById("avg-action-time").textContent = avgDays + " days";
    }


    function updateStatusFilterOptions() {
        const data = table.getData(); 

        let resolved = 0;
        let pending = 0;

        data.forEach(row => {
            if (row.action_status === 'Resolved') resolved++;
            else if (row.action_status === 'Pending') pending++;
        });

        const total = resolved + pending;
        const pendingPercentage = total > 0 ? ((pending / total) * 100).toFixed(1) : 0;

        const statusFilter = document.getElementById("status-filter");

        const currentValue = statusFilter.value;

        statusFilter.innerHTML = `
            <option value="">✔️ Resolved vs Unresolved</option>
            <option value="Resolved">✔️ Resolved (${resolved})</option>
            <option value="Pending">🕒 Pending (${pending})</option>
            <option value="pending_percentage" disabled>📊 Pending % (${pendingPercentage}%)</option>
        `;

        statusFilter.value = currentValue;

    }


    function handleStarFilter(value) {
        if (value === "all") {
            table.clearFilter();
        } else {
            table.setFilter("rating", "=", parseInt(value));
        }
    }

    function updateStarCounts() {
        const data = table.getData();

        let one = 0, two = 0, three = 0;
        data.forEach(row => {
            const rating = parseInt(row.rating); 
            if (rating === 1) one++;
            else if (rating === 2) two++;
            else if (rating === 3) three++;
        });

        const total = one + two + three;

        const select = document.getElementById("starFilter");
        if (select) {
            const currentValue = select.value;
            select.innerHTML = `
                <option value="all">🚩 Total Negative Reviews (${total})</option>
                <option value="1">⭐ 1-star Reviews (${one})</option>
                <option value="2">⭐⭐ 2-star Reviews (${two})</option>
                <option value="3">⭐⭐⭐ 3-star Reviews (${three})</option>
            `;
            select.value = currentValue;
        }
    }

    function filterByStar(starCount) {
        if (table) {
            table.setFilter("rating", "=", starCount);
        }
    }

    function resetStarFilter() {
        if (table) {
            table.clearFilter();
        }
    }

    // Quick Date Filter Logic
    function setupQuickDateFilter(){
        const quickFilter = document.getElementById("quick-date-filter");
        const startInput = document.getElementById("filter-start-date");
        const endInput = document.getElementById("filter-end-date");
        const customRange = document.getElementById("custom-date-range");

        const today = new Date().toISOString().split('T')[0];
        startInput.max = today;
        endInput.max = today;

        quickFilter.addEventListener("change", function () {
            const value = this.value;

            if (value === "custom") {
                customRange.classList.remove("d-none");
                return;
            } else {
                customRange.classList.add("d-none");
            }

            if (value === "7" || value === "30") {
                const today = new Date();
                const past = new Date();
                past.setDate(today.getDate() - parseInt(value));

                const startStr = past.toISOString().slice(0, 10);
                const endStr = today.toISOString().slice(0, 10);
                applyDateFilter(startStr, endStr);
            } else {
                table.clearFilter();
            }
        });

        startInput.addEventListener("change", () => {
            endInput.min = startInput.value;
            
            if (endInput.value && endInput.value < startInput.value) {
                endInput.value = '';
            }

            if (quickFilter.value === "custom") {
                applyDateFilter(startInput.value, endInput.value);
            }
        });

        endInput.addEventListener("change", () => {
            if (endInput.value) {
                startInput.max = endInput.value;
            } else {
                startInput.max = today;
            }

            if (quickFilter.value === "custom") {
                applyDateFilter(startInput.value, endInput.value);
            }
        });

        function applyDateFilter(start, end) {
            if (!start && !end) {
                table.clearFilter();
                return;
            }

            table.setFilter((data) => {
            const reviewDate = new Date(data.review_date);
            if (start && reviewDate < new Date(start)) return false;
            if (end && reviewDate > new Date(end)) return false;
                return true;
            });
        }

        window.clickToFilterSKU = function (skuValue) {
            table.setFilter("sku", "=", skuValue);
        };
    }

    //openImportModal function to handle file import
    function openImportModal(){
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
    
</script>