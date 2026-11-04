@extends('layouts.vertical', ['title' => 'Sixteen Ration Video'])

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

    .ration-toolbar {
        gap: 8px !important;
    }
    .ration-toolbar > .btn,
    .ration-toolbar > a.btn,
    .ration-toolbar .dropdown > .btn,
    .ration-toolbar .form-select,
    .ration-toolbar .video-link-badge {
        height: 32px;
        min-height: 32px;
        padding: 0 14px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1;
        letter-spacing: 0.2px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
    }
    .ration-toolbar .form-select {
        width: auto;
        padding-right: 28px;
    }
    #row-data-type.ration-filter { min-width: 132px; }
    #dil-color-filter.ration-filter { min-width: 108px; }
    .ration-toolbar .btn-group .btn {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }

    .video-link-badge {
        border: 0;
        background: #0ea5e9;
        color: #fff;
        white-space: nowrap;
        cursor: pointer;
        user-select: none;
    }
    .video-link-badge span {
        font-weight: 800;
        font-size: 13px;
        color: #fff;
    }
    .video-link-badge:hover { opacity: 0.85; }
    .video-link-badge.is-active {
        box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.35);
    }
    .video-link-badge.ration-badge--missing { background: #dc2626; }
    .video-link-badge.ration-badge--checked { background: #16a34a; }
    .video-link-badge.ration-badge--ad { background: #2c6ed5; }
    .video-link-badge.ration-badge--redo { background: #d97706; }
    .video-link-badge.ration-badge--redone { background: #7c3aed; }
    .ration-missing-dot {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #dc2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.18);
    }
    .status-box { width: 16px; height: 16px; cursor: pointer; }
    .status-meta { font-size: 10px; color: #4b5563; line-height: 1.2; margin-top: 2px; }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Sixteen Ration Video', 'sub_title' => 'Sixteen Ration Video'])

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="ration-toolbar mb-3 d-flex flex-wrap align-items-center">

                    <!-- Play/Pause Controls -->
                    <div class="btn-group time-navigation-group me-2" role="group" aria-label="Parent navigation">
                        <button id="play-backward" class="btn btn-light rounded-circle shadow-sm"
                            style="width: 32px; height: 32px; padding: 0;">
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button id="play-pause" class="btn btn-light rounded-circle shadow-sm mx-1"
                            style="width: 32px; height: 32px; padding: 0; display: none;">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button id="play-auto" class="btn btn-primary rounded-circle shadow-sm mx-1"
                            style="width: 32px; height: 32px; padding: 0;">
                            <i class="fas fa-play"></i>
                        </button>
                        <button id="play-forward" class="btn btn-light rounded-circle shadow-sm"
                            style="width: 32px; height: 32px; padding: 0;">
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>

                    <!-- Row Type Filter -->
                    <select id="row-data-type" class="form-select form-select-sm border border-primary ration-filter">
                        <option value="all">🔁 Show All</option>
                        <option value="sku">🔹 SKU (Child)</option>
                        <option value="parent">🔸 Parent</option>
                    </select>

                    <!-- Dil% Color Filter -->
                    <select id="dil-color-filter" class="form-select form-select-sm border border-danger ration-filter">
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
                        <button class="btn btn-sm btn-primary dropdown-toggle"
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
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-eye"></i>
                        Show All
                    </button>

                    <!-- Import Button -->
                    <button id="import-btn" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-upload"></i>
                        Import
                    </button>

                    <!-- Export Button -->
                    <a href="/sixteen-ration-video/export" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-download"></i>
                        Export
                    </a>

                    <button type="button" id="video-link-filter" class="video-link-badge" title="Show rows that have a 16:9 video">
                        <i class="fa fa-link"></i> 16:9 Video: <span id="total-sixteen-link-count">0</span>
                    </button>
                    <button type="button" class="video-link-badge ration-badge--missing" data-status="missing" title="Show rows missing a video">
                        Missing: <span id="count-missing">0</span>
                    </button>
                    <button type="button" class="video-link-badge ration-badge--checked" data-status="checked" title="Show checked rows">
                        Checked: <span id="count-checked">0</span>
                    </button>
                    <button type="button" class="video-link-badge ration-badge--ad" data-status="ad" title="Show rows marked AD">
                        AD: <span id="count-ad">0</span>
                    </button>
                    <button type="button" class="video-link-badge ration-badge--redo" data-status="redo" title="Show rows marked REDO">
                        Redo: <span id="count-redo">0</span>
                    </button>
                    <button type="button" class="video-link-badge ration-badge--redone" data-status="redone" title="Show rows marked REDONE">
                        Redone: <span id="count-redone">0</span>
                    </button>

                </div>
                <div id="sixteen-ration-video"></div>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">
                    <i class="fas fa-upload"></i> Import Sixteen Ration Video
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Download Sample File</label>
                    <div>
                        <a href="{{ asset('sample_excel/sample_sixteen_ration_video.csv') }}" 
                           download 
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-download"></i> Download Sample CSV
                        </a>
                    </div>
                </div>
                <hr>
                <div class="mb-3">
                    <label for="importFile" class="form-label fw-semibold">Select File</label>
                    <input type="file" class="form-control" id="importFile" accept=".csv,.txt,.xlsx,.xls">
                    <small class="text-muted d-block mt-1">
                        Supported formats: CSV, TXT, XLSX, XLS
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmImportBtn">
                    <i class="fas fa-upload"></i> Import
                </button>
            </div>
        </div>
    </div>
</div>

@endsection
@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // document.documentElement.setAttribute("data-sidenav-size", "condensed");

        let groupedSkuData = {};
        let currentParentFilter = null;
        let currentRowTypeFilter = "all";
        let currentDilColorFilter = "";
        let filterHasVideo = false;
        const statusFilters = { missing: false, checked: false, ad: false, redo: false, redone: false };
        const currentUserName = @json(auth()->user()->name ?? '');
        const LINK_FIELD = 'sixteen_ratio_link';
        const SAVE_URL = '/sixteen-ration-video/save';
        let currentIndex = 0;
        let isPlaying = false;
        let selectedColor = null;

        const getDilColor = (value) => {
            const percent = parseFloat(value) * 100;
            if (percent < 25) return 'red';
            if (percent >= 25 && percent < 50) return 'green';
            return 'pink';
        };    

        function hasVideo(row) {
            return String(row[LINK_FIELD] || '').trim() !== '';
        }

        function esc(value) {
            return String(value).replace(/[&<>"']/g, function (c) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
            });
        }

        function statusBoxFormatter(flag) {
            return function (cell) {
                const row = cell.getRow().getData();
                const checked = !!row[flag];
                const by = row[flag + '_by'] ? esc(row[flag + '_by']) : '';
                return `<div class="text-center">
                    <input type="checkbox" class="status-box form-check-input" data-flag="${flag}" ${checked ? 'checked' : ''}>
                    ${checked && by ? `<div class="status-meta">${by}</div>` : ''}
                </div>`;
            };
        }

        function saveStatus(sku, value) {
            fetch(SAVE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ sku: sku, value: value })
            })
            .then(res => res.json())
            .then(res => {
                if (!res.success) alert('Failed to save data.');
                updateTotalInvAndL30(table);
            });
        }

        function onStatusClick(e, cell) {
            const box = e.target.closest('.status-box');
            if (!box) return;
            const flag = box.getAttribute('data-flag');
            const row = cell.getRow();
            const data = row.getData();
            const next = !data[flag];
            const patch = {};
            patch[flag] = next;
            patch[flag + '_by'] = next ? currentUserName : '';
            patch[flag + '_at'] = next ? new Date().toISOString() : '';
            row.update(patch);
            saveStatus(data.Sku, patch);
        }

        function groupBy(array, key) {
            return array.reduce((result, obj) => {
                const groupKey = obj[key];
                if (!result[groupKey]) result[groupKey] = [];
                result[groupKey].push(obj);
                return result;
            }, {});
        }

        const table = new Tabulator("#sixteen-ration-video", {
            index: "Sku",
            ajaxURL: "/sixteen-ration-video/view-data",
            ajaxConfig: "GET",
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
                    title: "16:9 Video",
                    field: "sixteen_ratio_link",
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
                            16:9 Video <i class='fa fa-link'></i>
                        </div>`;
                    },
                    hozAlign: "center",
                },
                {
                    title: "MISSING",
                    field: "is_missing",
                    hozAlign: "center",
                    headerSort: false,
                    width: 110,
                    formatter: function (cell) {
                        const row = cell.getRow().getData();
                        const checked = !!row.is_missing;
                        const by = row.is_missing_by ? esc(row.is_missing_by) : '';
                        const dot = hasVideo(row) ? '' : '<span class="ration-missing-dot" title="No video link"></span>';
                        return `<div class="text-center">
                            ${dot}
                            <input type="checkbox" class="status-box form-check-input" data-flag="is_missing" ${checked ? 'checked' : ''}>
                            ${checked && by ? `<div class="status-meta">${by}</div>` : ''}
                        </div>`;
                    },
                    cellClick: onStatusClick,
                },
                {
                    title: "CHECK",
                    field: "is_checked",
                    hozAlign: "center",
                    headerSort: false,
                    width: 100,
                    formatter: statusBoxFormatter('is_checked'),
                    cellClick: onStatusClick,
                },
                {
                    title: "AD",
                    field: "ad_checked",
                    hozAlign: "center",
                    headerSort: false,
                    width: 100,
                    formatter: statusBoxFormatter('ad_checked'),
                    cellClick: onStatusClick,
                },
                {
                    title: "REDO",
                    field: "redo_checked",
                    hozAlign: "center",
                    headerSort: false,
                    width: 100,
                    formatter: statusBoxFormatter('redo_checked'),
                    cellClick: onStatusClick,
                },
                {
                    title: "REDONE",
                    field: "redone_checked",
                    hozAlign: "center",
                    headerSort: false,
                    width: 110,
                    formatter: statusBoxFormatter('redone_checked'),
                    cellClick: onStatusClick,
                },
            ],
            ajaxResponse: function (url, params, response) {
                const rows = response.data;

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
        });
        table.on("dataProcessed", function() {
            const data = table.getData();
            groupedSkuData = groupBy(data, "Parent");

            setTimeout(() => updateTotalInvAndL30(table), 100);
        });

        table.on("dataFiltered", function() {
            setTimeout(() => updateTotalInvAndL30(table), 50);
        });

        table.on("tableBuilt", function () {
            buildColumnDropdown();
        });

        table.on("cellEdited", function(cell) {
            const rowData = cell.getRow().getData();
            const payload = {
                sku: rowData.Sku,
                value: {
                    sixteen_ratio_link: rowData.sixteen_ratio_link || '',
                }
            };

            fetch("/sixteen-ration-video/save", {
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
                if (cell.getField() === LINK_FIELD) cell.getRow().reformat();
                updateTotalInvAndL30(table);
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
            const totalLinks = data.filter(row => String(row.sixteen_ratio_link || "").trim() !== "").length;

            document.getElementById("total-inv-header").textContent = totalINV.toLocaleString();
            document.getElementById("total-l30-header").textContent = totalL30.toLocaleString();
            const linkBadge = document.getElementById("total-sixteen-link-count");
            if (linkBadge) linkBadge.textContent = totalLinks.toLocaleString();

            const base = table.getData().filter(row => matchesBaseFilters(row));
            const setCount = (id, count) => {
                const el = document.getElementById(id);
                if (el) el.textContent = count.toLocaleString();
            };
            setCount("count-missing", base.filter(row => rowIsMissing(row)).length);
            setCount("count-checked", base.filter(row => !!row.is_checked).length);
            setCount("count-ad", base.filter(row => !!row.ad_checked).length);
            setCount("count-redo", base.filter(row => !!row.redo_checked).length);
            setCount("count-redone", base.filter(row => !!row.redone_checked).length);
        }

        function matchesBaseFilters(data) {
            let matchesParent = true;
            let matchesRowType = true;
            let matchesDilColor = true;
            let matchesVideo = true;

            if (currentParentFilter) {
                matchesParent = data.Parent === currentParentFilter;
            }
            if (currentRowTypeFilter === "sku") {
                matchesRowType = !(data.Sku || '').toUpperCase().includes("PARENT");
            } else if (currentRowTypeFilter === "parent") {
                matchesRowType = (data.Sku || '').toUpperCase().includes("PARENT");
            }
            if (currentDilColorFilter) {
                matchesDilColor = data.dilColor === currentDilColorFilter;
            }
            if (filterHasVideo) {
                matchesVideo = hasVideo(data);
            }

            return matchesParent && matchesRowType && matchesDilColor && matchesVideo;
        }

        function rowIsMissing(data) {
            return !!data.is_missing || !hasVideo(data);
        }

        function matchesStatusFilters(data) {
            if (statusFilters.missing && !rowIsMissing(data)) return false;
            if (statusFilters.checked && !data.is_checked) return false;
            if (statusFilters.ad && !data.ad_checked) return false;
            if (statusFilters.redo && !data.redo_checked) return false;
            if (statusFilters.redone && !data.redone_checked) return false;
            return true;
        }

        // Play/Pause Controls
        function setCombinedFilters() {
            table.clearFilter();

            table.setFilter(function (data) {
                return matchesBaseFilters(data) && matchesStatusFilters(data);
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

        document.getElementById('video-link-filter').addEventListener('click', function () {
            filterHasVideo = !filterHasVideo;
            this.classList.toggle('is-active', filterHasVideo);
            this.title = filterHasVideo
                ? 'Showing rows that have a 16:9 video. Click to show all.'
                : 'Show rows that have a 16:9 video';
            setCombinedFilters();
        });

        document.querySelectorAll('[data-status]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const key = this.dataset.status;
                statusFilters[key] = !statusFilters[key];
                this.classList.toggle('is-active', statusFilters[key]);
                setCombinedFilters();
            });
        });

        // Import Modal functionality
        const importModal = new bootstrap.Modal(document.getElementById('importModal'));
        
        // Open modal when import button is clicked
        document.getElementById('import-btn').addEventListener('click', function() {
            // Reset file input
            document.getElementById('importFile').value = '';
            importModal.show();
        });

        // Handle import confirmation
        document.getElementById('confirmImportBtn').addEventListener('click', function() {
            const fileInput = document.getElementById('importFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a file to import');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);

            // Show loading
            const btn = document.getElementById('confirmImportBtn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';

            fetch('/sixteen-ration-video/import', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            })
            .then(res => {
                if (!res.ok) {
                    return res.json().then(err => Promise.reject(err));
                }
                return res.json();
            })
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                if (data.success) {
                    importModal.hide();
                    let message = `Import successful!\nImported: ${data.imported}\nSkipped: ${data.skipped}`;
                    if (data.message) {
                        message = data.message;
                    }
                    alert(message);
                    table.replaceData(); // Refresh table data
                } else {
                    alert('Import failed: ' + (data.error || data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                const errorMsg = error.error || error.message || error.toString();
                alert('Import failed: ' + errorMsg);
                console.error('Import error:', error);
            });
        });

    });
</script>

@endsection