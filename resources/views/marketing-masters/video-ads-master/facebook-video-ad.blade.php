@extends('layouts.vertical', ['title' => 'Facebook Video Ads'])

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    /* Custom styles for the Tabulator table */
    .tabulator-tableholder{
        height: calc(100% - 104px);
        max-height: calc(92% - 65px) !important;
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
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Facebook Video Ads', 'sub_title' => 'Facebook Video Ads'])

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="mb-3 d-flex flex-wrap align-items-center gap-3">

                    <!-- Play/Pause Controls -->
                    <div class="btn-group time-navigation-group me-2" role="group" aria-label="Parent navigation">
                        <button id="play-backward" class="btn btn-light rounded-circle shadow-sm"
                            style="width: 36px; height: 36px; padding: 6px;">
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button id="play-pause" class="btn btn-light rounded-circle shadow-sm mx-1"
                            style="width: 36px; height: 36px; padding: 6px; display: none;">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button id="play-auto" class="btn btn-primary rounded-circle shadow-sm mx-1"
                            style="width: 36px; height: 36px; padding: 6px;">
                            <i class="fas fa-play"></i>
                        </button>
                        <button id="play-forward" class="btn btn-light rounded-circle shadow-sm"
                            style="width: 36px; height: 36px; padding: 6px;">
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>

                    <!-- Row Type Filter -->
                    <select id="row-data-type" class="form-select border border-primary" style="width: 150px;">
                        <option value="all">🔁 Show All</option>
                        <option value="sku">🔹 SKU (Child)</option>
                        <option value="parent">🔸 Parent</option>
                    </select>

                    <!-- Dil% Color Filter -->
                    <select id="dil-color-filter" class="form-select border border-danger" style="width: 120px;">
                        <option value="">🎯 Dil%</option>
                        <option value="red">🔴 Red</option>
                        <option value="yellow">🟡 Yellow</option>
                        <option value="green">🟢 Green</option>
                        <option value="pink">🟣 Pink</option>
                    </select>

                    <!-- Toggle NR -->
                    <button id="toggle-nr-rows" class="btn btn-outline-secondary" hidden>
                        Show NR
                    </button>

                    <!-- Column Management -->
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle d-flex align-items-center gap-1"
                            type="button" id="hide-column-dropdown" data-bs-toggle="dropdown">
                            <i class="bi bi-grid-3x3-gap-fill"></i>
                            Manage Columns
                        </button>
                        <ul class="dropdown-menu p-3 shadow-lg border rounded-3" id="column-dropdown-menu"
                            style="max-height: 300px; overflow-y: auto; min-width: 250px;">
                            <li class="fw-semibold text-muted mb-2">Toggle Columns</li>
                        </ul>
                    </div>

                    <!-- Show All Columns -->
                    <button id="show-all-columns-btn" class="btn btn-outline-success d-flex align-items-center gap-1">
                        <i class="bi bi-eye"></i>
                        Show All
                    </button>

                    <!-- Download/Upload Excel -->
                    <input type="file" id="uploadExcel" accept=".xlsx,.xls" style="display: none;">
                    <button type="button" class="btn btn-primary" id="uploadExcelBtn">
                        <i class="fas fa-upload me-1"></i> Upload Excel
                    </button>
                    <button type="button" class="btn btn-success" id="downloadExcel">
                        <i class="fas fa-file-excel me-1"></i> Download Excel
                    </button>

                </div>
                <div id="facebook-video-ads"></div>
            </div>
        </div>
    </div>
</div>

@endsection
@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.documentElement.setAttribute("data-sidenav-size", "condensed");

        let groupedSkuData = {};
        let currentParentFilter = null;
        let currentRowTypeFilter = "all";
        let currentDilColorFilter = "";
        let currentIndex = 0;
        let isPlaying = false;
        let selectedColor = null;

        const getDilColor = (value) => {
            const percent = parseFloat(value) * 100;
            if (percent < 16.66) return 'red';
            if (percent >= 16.66 && percent < 25) return 'yellow';
            if (percent >= 25 && percent < 50) return 'green';
            return 'pink';
        };    

        function groupBy(array, key) {
            return array.reduce((result, obj) => {
                const groupKey = obj[key];
                if (!result[groupKey]) result[groupKey] = [];
                result[groupKey].push(obj);
                return result;
            }, {});
        }

        const table = new Tabulator("#facebook-video-ads", {
            index: "Sku",
            ajaxURL: "/facebook-video-ads",
            ajaxConfig: {
                method: "GET",
                headers: {
                    "Accept": "application/json",
                    "Content-Type": "application/json",
                }
            },
            layout: "fitColumns",
            pagination: true,
            paginationSize: 50,
            paginationMode: "local",
            movableColumns: false,
            resizableColumns: true,
            height: "650px",
            rowFormatter: function(row) {
                const data = row.getData();
                const sku = data["Sku"] || '';

                if (sku.toUpperCase().includes("PARENT")) {
                    row.getElement().classList.add("parent-row");
                }
            },
            columns: [
                { 
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
                    title: "Category",
                    field: "category",
                    minWidth: 150,
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search category.",
                    headerFilterFunc: "like",
                    titleFormatter: function() {
                        return `<div>
                            <span>Category</span>
                        </div>`;
                    },
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowData = row.getData();
                        const categoryId = rowData.category_id || '';
                        const categoryName = rowData.category || '';
                        const rowId = rowData.id;
                        const sku = rowData.Sku;
                        
                        let categoryOptions = '<option value="">-- No Category --</option>';
                        if (window.allCategories && window.allCategories.length > 0) {
                            window.allCategories.forEach(cat => {
                                const selected = cat.id == categoryId ? 'selected' : '';
                                categoryOptions += `<option value="${cat.id}" ${selected}>${cat.category_name}</option>`;
                            });
                        }
                        
                        return `
                            <div class="editable-cell" data-field="category_id" data-row-id="${rowId}" data-sku="${sku}">
                                <span class="editable-value">${categoryName || '-'}</span>
                                <select class="editable-select form-select form-select-sm" style="display:none;" data-original="${categoryId}">
                                    ${categoryOptions}
                                </select>
                            </div>
                        `;
                    },
                    cellClick: function(e, cell) {
                        const cellEl = cell.getElement();
                        const editableCell = cellEl.querySelector('.editable-cell');
                        if (editableCell) {
                            const span = editableCell.querySelector('.editable-value');
                            const select = editableCell.querySelector('.editable-select');
                            if (span && select) {
                                span.style.display = 'none';
                                select.style.display = 'block';
                                select.focus();
                            }
                        }
                    }
                },
                {
                    title: "Group",
                    field: "group",
                    minWidth: 150,
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search group.",
                    headerFilterFunc: "like",
                    titleFormatter: function() {
                        return `<div>
                            <span>Group</span>
                        </div>`;
                    },
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowData = row.getData();
                        const groupId = rowData.group_id || '';
                        const groupName = rowData.group || '';
                        const rowId = rowData.id;
                        const sku = rowData.Sku;
                        
                        let groupOptions = '<option value="">-- No Group --</option>';
                        if (window.allGroups && window.allGroups.length > 0) {
                            window.allGroups.forEach(grp => {
                                const selected = grp.id == groupId ? 'selected' : '';
                                groupOptions += `<option value="${grp.id}" ${selected}>${grp.group_name}</option>`;
                            });
                        }
                        
                        return `
                            <div class="editable-cell" data-field="group_id" data-row-id="${rowId}" data-sku="${sku}">
                                <span class="editable-value">${groupName || '-'}</span>
                                <select class="editable-select form-select form-select-sm" style="display:none;" data-original="${groupId}">
                                    ${groupOptions}
                                </select>
                            </div>
                        `;
                    },
                    cellClick: function(e, cell) {
                        const cellEl = cell.getElement();
                        const editableCell = cellEl.querySelector('.editable-cell');
                        if (editableCell) {
                            const span = editableCell.querySelector('.editable-value');
                            const select = editableCell.querySelector('.editable-select');
                            if (span && select) {
                                span.style.display = 'none';
                                select.style.display = 'block';
                                select.focus();
                            }
                        }
                    }
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
                    formatter: "plaintext",
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
                    formatter: "plaintext",
                    hozAlign: "center"
                },
                {
                    title: "Dil%",
                    field: "Dil",
                    formatter: function (cell) {
                        const data = cell.getData();
                        const l30 = parseFloat(data.L30);
                        const inv = parseFloat(data.INV);

                        if (!isNaN(l30) && !isNaN(inv) && inv !== 0) {
                            const dilDecimal = (l30 / inv);
                            const color = getDilColor(dilDecimal); 
                            return `<div class="text-center"><span class="dil-percent-value ${color}">${Math.round(dilDecimal * 100)}%</span></div>`;
                        }
                        return `<div class="text-center"><span class="dil-percent-value red">0%</span></div>`;
                    }
                },
                {
                    title: "4:5 Video",
                    field: "four_ratio_link",
                    editor: "input",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value && value.trim() !== "") {
                            return `<a href="${value}" target="_blank" style="text-decoration:none;">
                                        <i class="fa fa-link"></i> Open
                                    </a>`;
                        }
                        return "";
                    },
                    cellDblClick: function(e, cell) {
                        cell.edit();        
                    },
                    titleFormatter: function () {
                        return `<div>
                            4:5 Video <i class='fa fa-link'></i>
                        </div>`;
                    },
                    hozAlign: "center",
                },
                {
                    title: "Posted",
                    field: "posted",
                    editor: "input",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value && value.trim() !== "") {
                            return `<a href="${value}" target="_blank" style="text-decoration:none;">
                                        <i class="fa fa-link"></i> Open
                                    </a>`;
                        }
                        return "";
                    },
                    cellDblClick: function(e, cell) {
                        cell.edit();        
                    },
                    titleFormatter: function () {
                        return `<div>
                            Posted <i class='fa fa-link'></i>
                        </div>`;
                    },
                    hozAlign: "center",
                },
                {
                    title: "Ad Req",
                    field: "ad_req",
                    formatter: function (cell) {
                        const row = cell.getRow();
                        const sku = row.getData().Sku;
                        return `
                            <select class="form-select form-select-sm editable-select" 
                                    data-row-id="${sku}" 
                                    data-type="ad_req"
                                style="width: 90px;">
                                <option value="">Select</option>
                                <option value="NR" ${cell.getValue() === 'NR' ? 'selected' : ''}>NR</option>
                                <option value="REQ" ${cell.getValue() === 'REQ' ? 'selected' : ''}>REQ</option>
                            </select>
                        `;

                    },
                    hozAlign: "center"
                },
                {
                    title: "AD",
                    field: "ads",
                    titleFormatter: function() {
                        return `<div>
                            AD<br>
                            <span id="total-ad-header" style="font-size:13px;color:white;font-weight:600;"></span>
                        </div>`;
                    },
                    formatter: function (cell) {
                        const row = cell.getRow();
                        const sku = row.getData().Sku;
                        return `
                            <div class="form-check d-flex justify-content-center">
                                <input class="form-check-input table-update" 
                                    type="checkbox"
                                    data-row-id="${sku}"
                                    data-type="ads"
                                    ${cell.getValue() ? 'checked' : ''}>
                            </div>
                        `;
                    },
                    hozAlign: "center"
                },
            ],
            ajaxResponse: function (url, params, response) {
                // Handle different response formats
                let rows = [];
                
                if (Array.isArray(response)) {
                    // If response is already an array
                    rows = response;
                } else if (response && response.data && Array.isArray(response.data)) {
                    // If response has a data property with array
                    rows = response.data;
                } else if (response && Array.isArray(response)) {
                    rows = response;
                } else {
                    console.error("Unexpected response format:", response);
                    return [];
                }

                rows.forEach(row => {
                    const inv = parseFloat(row.INV);
                    const l30 = parseFloat(row.L30);
                    if (!isNaN(inv) && inv !== 0 && !isNaN(l30)) {
                        row.dilColor = getDilColor(l30 / inv);
                    } else {
                        row.dilColor = "red";
                    }
                });

                return rows;
            },
            ajaxError: function(error) {
                console.error("Error loading Facebook video ads data:", error);
                let errorMessage = "Error loading data. ";
                
                if (error && error.responseText) {
                    // Check if response is HTML (error page)
                    if (error.responseText.trim().startsWith('<!DOCTYPE') || error.responseText.trim().startsWith('<html')) {
                        errorMessage += "Server returned an HTML page instead of JSON. This might be a routing or authentication issue.";
                    } else {
                        try {
                            const errorData = JSON.parse(error.responseText);
                            errorMessage += errorData.message || "Please refresh the page and try again.";
                        } catch (e) {
                            errorMessage += "Please refresh the page and try again.";
                        }
                    }
                } else {
                    errorMessage += "Please refresh the page and try again.";
                }
                
                alert(errorMessage);
            },
        });
        table.on("dataProcessed", function() {
            const data = table.getData();
            groupedSkuData = groupBy(data, "Parent");

            setTimeout(() => updateTotalInvAndL30(table), 100);
        });

        table.on("tableBuilt", function () {
            buildColumnDropdown();
        });

        table.on("cellEdited", function(cell) {
            const rowData = cell.getRow().getData();
            const payload = {
                sku: rowData.Sku,
                value: {
                    four_ratio_link: rowData.four_ratio_link || '',
                    posted: rowData.posted || '',
                }
            };

            fetch("/facebook-video-ads/save", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(res => {
                if (!res.success) alert("Failed to save data.");
            });
        });

        const COLUMN_VIS_KEY = "tabulator_column_visibility";

        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            const savedVisibility = JSON.parse(localStorage.getItem(COLUMN_VIS_KEY) || '{}');

            const columns = table.getColumns().filter(col => col.getField());

            columns.forEach(col => {
                const field = col.getField();
                const title = col.getDefinition().title;

                // Apply saved visibility on table
                if (savedVisibility[field] === false) {
                    col.hide();
                } else {
                    col.show();
                }

                const li = document.createElement("li");
                const div = document.createElement("div");
                div.className = "form-check d-flex align-items-center gap-2 py-1 px-2 rounded hover-bg-light";

                const input = document.createElement("input");
                input.className = "form-check-input shadow-sm cursor-pointer";
                input.type = "checkbox";
                input.id = `col-${field}`;
                input.value = field;
                input.checked = col.isVisible();
                input.style.cssText = `
                    width: 18px;
                    height: 18px;
                    cursor: pointer;
                    border-color: #dee2e6;
                `;

                const label = document.createElement("label");
                label.className = "form-check-label cursor-pointer mb-0 text-dark";
                label.htmlFor = `col-${field}`;
                label.innerText = title;
                label.style.cssText = `
                    cursor: pointer;
                    font-size: 0.9rem;
                    user-select: none;
                `;

                // Add hover effect
                div.addEventListener('mouseover', () => {
                    div.style.backgroundColor = '#f8f9fa';
                });
                
                div.addEventListener('mouseout', () => {
                    div.style.backgroundColor = 'transparent';
                });

                // Add ripple effect on click
                div.addEventListener('click', (e) => {
                    if (e.target !== input) {
                        input.click();
                    }
                });

                div.appendChild(input);
                div.appendChild(label);
                li.appendChild(div);
                menu.appendChild(li);
            });
        }

        function saveColumnVisibilityToLocalStorage() {
            const visibility = {};
            table.getColumns().forEach(col => {
                const field = col.getField();
                if (field) {
                    visibility[field] = col.isVisible();
                }
            });
            localStorage.setItem(COLUMN_VIS_KEY, JSON.stringify(visibility));
        }

        buildColumnDropdown();

        // Toggle column from dropdown
        document.getElementById("column-dropdown-menu").addEventListener("change", function (e) {
            if (e.target.type === "checkbox") {
                const field = e.target.value;
                const col = table.getColumn(field);
                if (col) {
                    e.target.checked ? col.show() : col.hide();
                    saveColumnVisibilityToLocalStorage();
                }
            }
        });

        // Show All Columns button
        document.getElementById("show-all-columns-btn").addEventListener("click", function () {
            const checkboxes = document.querySelectorAll("#column-dropdown-menu input[type='checkbox']");
            checkboxes.forEach(cb => {
                cb.checked = true;
                const col = table.getColumn(cb.value);
                if (col) col.show();
            });
            saveColumnVisibilityToLocalStorage();
        });

        function updateTotalInvAndL30(table) {
            const data = table.getData("active");

            const totalINV = data.reduce((sum, row) => sum + (parseFloat(row["INV"]) || 0), 0);
            const totalL30 = data.reduce((sum, row) => sum + (parseFloat(row["L30"]) || 0), 0);

            document.getElementById("total-inv-header").textContent = totalINV.toLocaleString();
            document.getElementById("total-l30-header").textContent = totalL30.toLocaleString();

        }

        // Play/Pause Controls
        function setCombinedFilters() {
            table.clearFilter();

            table.setFilter(function (data) {
                let matchesParent = true;
                let matchesRowType = true;
                let matchesDilColor = true;

                // Parent filter
                if (currentParentFilter) {
                    matchesParent = data.Parent === currentParentFilter;
                }

                // Row type filter
                if (currentRowTypeFilter === "sku") {
                    matchesRowType = !data.Sku.toUpperCase().includes("PARENT");
                } else if (currentRowTypeFilter === "parent") {
                    matchesRowType = data.Sku.toUpperCase().includes("PARENT");
                }

                // Dil color filter
                if (currentDilColorFilter) {
                    matchesDilColor = data.dilColor === currentDilColorFilter;
                }

                return matchesParent && matchesRowType && matchesDilColor;
            });
        }

        function renderGroup(parentKey) {
            if (!groupedSkuData[parentKey]) return;
            currentParentFilter = parentKey;
            setCombinedFilters();
        }

        document.getElementById('play-auto').addEventListener('click', () => {
            isPlaying = true;
            currentIndex = 0;
            renderGroup(parentKeys()[currentIndex]);
            togglePlayPauseUI(true);
        });

        document.getElementById('play-forward').addEventListener('click', () => {
            if (!isPlaying) return;
            currentIndex = (currentIndex + 1) % parentKeys().length;
            renderGroup(parentKeys()[currentIndex]);
        });

        document.getElementById('play-backward').addEventListener('click', () => {
            if (!isPlaying) return;
            currentIndex = (currentIndex - 1 + parentKeys().length) % parentKeys().length;
            renderGroup(parentKeys()[currentIndex]);
        });

        document.getElementById('play-pause').addEventListener('click', () => {
            isPlaying = false;
            currentParentFilter = null;
            setCombinedFilters();
            togglePlayPauseUI(false);
        });

        function parentKeys() {
            return Object.keys(groupedSkuData);
        }

        function togglePlayPauseUI(isPlaying) {
            document.getElementById('play-pause').style.display = isPlaying ? 'inline-block' : 'none';
            document.getElementById('play-auto').style.display = isPlaying ? 'none' : 'inline-block';
        }

        // Show Parent, Sku and All Rows
        document.getElementById('row-data-type').addEventListener('change', function (e) {
            currentRowTypeFilter = e.target.value;
            setCombinedFilters();
        });

        // Dil Color Filter
        document.getElementById('dil-color-filter').addEventListener('change', function (e) {
            currentDilColorFilter = e.target.value;
            setCombinedFilters();
        });

        //select video
        document.addEventListener("change", function (e) {
            const isEditable = e.target.classList.contains("editable-select") || e.target.classList.contains("form-check-input");
            if (!isEditable) return;

            const tableInstance = Tabulator.findTable("#facebook-video-ads")[0];
            const rowId = e.target.getAttribute("data-row-id");
            if (!rowId) {
                console.warn("No data-row-id found on element", e.target);
                return;
            }

            const row = tableInstance.getRow(rowId);
            if (!row) {
                console.warn("No matching row found for SKU:", rowId);
                return;
            }

            const rowData = row.getData();
            const field = e.target.getAttribute("data-type");
            const value = e.target.type === "checkbox" ? e.target.checked : e.target.value;

            row.update({ [field]: value });

            const payload = {
                sku: rowData.Sku,
                value: {
                    [field]: value
                }
            };

            fetch("/facebook-video-ads/save", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(res => {
                console.log("Saved response:", res);
                if (!res.success) alert("Save failed");
            })
            .catch(err => {
                console.error("Error saving:", err);
            });
        });

        // document.body.style.zoom = "90%";

        // Initialize groups and categories
        window.allGroups = [];
        window.allCategories = [];

        // Load groups and categories from Group Master
        function loadGroupsAndCategories() {
            return Promise.all([
                fetch('/group-master-groups')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.allGroups = data.groups || [];
                        }
                    })
                    .catch(error => {
                        console.error('Error loading groups:', error);
                        window.allGroups = [];
                    }),
                fetch('/group-master-categories')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.allCategories = data.categories || [];
                        }
                    })
                    .catch(error => {
                        console.error('Error loading categories:', error);
                        window.allCategories = [];
                    })
            ]);
        }

        // Setup group/category editing
        function setupGroupCategoryEditing() {
            document.addEventListener('change', function(e) {
                const select = e.target;
                if (!select.classList.contains('editable-select') || !select.closest('.editable-cell')) return;
                
                const cell = select.closest('.editable-cell');
                const span = cell.querySelector('.editable-value');
                const field = cell.dataset.field;
                const rowId = cell.dataset.rowId;
                const sku = cell.dataset.sku;
                const newValue = select.value;
                const originalValue = select.dataset.original || '';

                if (newValue !== originalValue) {
                    saveGroupCategoryField(rowId, sku, field, newValue, cell, span, select);
                } else {
                    span.style.display = '';
                    select.style.display = 'none';
                }
            });

            document.addEventListener('blur', function(e) {
                const select = e.target;
                if (!select.classList.contains('editable-select') || !select.closest('.editable-cell')) return;
                
                const cell = select.closest('.editable-cell');
                const span = cell.querySelector('.editable-value');
                const originalValue = select.dataset.original || '';
                
                if (select.value !== originalValue && !cell.dataset.saving) {
                    select.value = originalValue;
                    updateDisplayValue(cell, originalValue, cell.dataset.field);
                }
                
                span.style.display = '';
                select.style.display = 'none';
                delete cell.dataset.saving;
            }, true);
        }

        function updateDisplayValue(cell, valueId, field) {
            const span = cell.querySelector('.editable-value');
            if (!valueId || valueId === '') {
                span.textContent = '-';
                return;
            }
            
            if (field === 'group_id') {
                const group = window.allGroups.find(g => g.id == valueId);
                span.textContent = group ? group.group_name : '-';
            } else if (field === 'category_id') {
                const category = window.allCategories.find(c => c.id == valueId);
                span.textContent = category ? category.category_name : '-';
            }
        }

        function saveGroupCategoryField(rowId, sku, field, value, cell, span, select) {
            cell.dataset.saving = 'true';
            span.textContent = 'Saving...';
            span.style.display = '';
            select.style.display = 'none';
            cell.style.opacity = '0.6';

            fetch('/facebook-video-ads-update-field', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    id: rowId,
                    sku: sku,
                    field: field,
                    value: value ? parseInt(value) : null
                })
            })
            .then(response => response.json())
            .then(data => {
                cell.style.opacity = '1';
                delete cell.dataset.saving;
                
                if (data.success) {
                    updateDisplayValue(cell, value, field);
                    select.value = value;
                    select.dataset.original = value;
                    
                    // Update table data
                    const tableData = table.getData();
                    const item = tableData.find(d => d.id == rowId);
                    if (item) {
                        if (field === 'group_id') {
                            item.group_id = value ? parseInt(value) : null;
                            item.group = data.data.group_name || null;
                        } else if (field === 'category_id') {
                            item.category_id = value ? parseInt(value) : null;
                            item.category = data.data.category_name || null;
                        }
                    }
                    
                    alert(data.message || `${field.replace('_id', '')} updated successfully!`);
                } else {
                    const originalValue = select.dataset.original || '';
                    select.value = originalValue;
                    updateDisplayValue(cell, originalValue, field);
                    alert(data.message || `Failed to update ${field}.`);
                }
            })
            .catch(error => {
                cell.style.opacity = '1';
                delete cell.dataset.saving;
                const originalValue = select.dataset.original || '';
                select.value = originalValue;
                updateDisplayValue(cell, originalValue, field);
                console.error('Error:', error);
                alert(`Error updating ${field}. Please try again.`);
            });
        }

        // Note: Groups and Categories are managed in Group Master page
        // They will automatically sync here when created there

        // Setup Excel download
        function setupExcelDownload() {
            document.getElementById('downloadExcel').addEventListener('click', function() {
                window.location.href = '/facebook-video-ads-download-excel';
            });
        }

        // Setup Excel upload
        function setupExcelUpload() {
            const uploadBtn = document.getElementById('uploadExcelBtn');
            const fileInput = document.getElementById('uploadExcel');

            uploadBtn.addEventListener('click', function() {
                fileInput.click();
            });

            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;

                const validTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 
                                  'application/vnd.ms-excel'];
                if (!validTypes.includes(file.type) && !file.name.match(/\.(xlsx|xls)$/i)) {
                    alert('Please upload a valid Excel file (.xlsx or .xls)');
                    fileInput.value = '';
                    return;
                }

                uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading...';
                uploadBtn.disabled = true;

                const formData = new FormData();
                formData.append('excel_file', file);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                fetch('/facebook-video-ads-upload-excel', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Excel file uploaded and data updated successfully!');
                        setTimeout(() => {
                            table.replaceData();
                        }, 1000);
                    } else {
                        alert(data.message || 'Failed to upload Excel file.');
                    }
                })
                .catch(error => {
                    console.error('Upload error:', error);
                    alert('An error occurred while uploading the file.');
                })
                .finally(() => {
                    uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload Excel';
                    uploadBtn.disabled = false;
                    fileInput.value = '';
                });
            });
        }

        // Add create buttons to column headers
        function addCreateButtonsToHeaders() {
            // This will be handled by updating the column definitions
        }

        // Initialize everything
        loadGroupsAndCategories().then(() => {
            setupGroupCategoryEditing();
            setupExcelDownload();
            setupExcelUpload();
        });
    });

    </script>