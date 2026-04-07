@extends('layouts.vertical', ['title' => 'MIP'])
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

        /* Image column: fill cell, no card, object-fit contain */
        .tabulator .tabulator-cell.mip-new-image-cell {
            padding: 0 !important;
            vertical-align: middle !important;
            line-height: 0;
        }
        .tabulator .mip-new-img-aspect {
            width: 46px !important;
            height: 46px !important;
            max-width: 46px !important;
            max-height: 46px !important;
            margin: 0 auto;
            box-sizing: border-box;
        }
        .tabulator .mip-new-img-aspect img {
            width: 100% !important;
            height: 100% !important;
            max-width: 100% !important;
            max-height: 100% !important;
            object-fit: contain;
            display: block;
            cursor: pointer;
            border-radius: 0;
            box-shadow: none;
            background: transparent;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'MIP', 'sub_title' => 'MIP'])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0">MIP</h4>
                    </div>

                    <div class="row mb-4 g-3 align-items-end justify-content-between">
                        {{-- ▶️ Navigation --}}
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block">▶️ Navigation</label>
                            <div class="btn-group time-navigation-group" role="group">
                                <button id="play-backward" class="btn btn-light rounded-circle shadow-sm me-2"
                                    title="Previous parent">
                                    <i class="fas fa-step-backward"></i>
                                </button>
                                <button id="play-pause" class="btn btn-light rounded-circle shadow-sm me-2"
                                    style="display: none;" title="Pause">
                                    <i class="fas fa-pause"></i>
                                </button>
                                <button id="play-auto" class="btn btn-primary rounded-circle shadow-sm me-2" title="Play">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button id="play-forward" class="btn btn-light rounded-circle shadow-sm"
                                    title="Next parent">
                                    <i class="fas fa-step-forward"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block">Pending Status</label>
                            <select id="row-data-pending-status" class="form-select border border-primary" style="width: 150px;">
                                <option value="">select color</option>
                                <option value="green">Green <span id="greenCount"></span></option>
                                <option value="yellow">Yellow <span id="yellowCount"></span></option>
                                <option value="red">Red <span id="redCount"></span></option>
                            </select>
                        </div>

                        {{-- total amount --}}
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block">💰 Amount</label>
                            <div id="totalAmount" class="fw-bold text-primary" style="font-size: 1.1rem;">
                                00
                            </div>
                        </div>

                        {{-- 📦 CBM --}}
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-1 d-block">📦 CBM</label>
                            <div id="totalCBM" class="fw-bold text-success" style="font-size: 1.1rem;">
                                00
                            </div>
                        </div>

                        {{-- 🔍 Search --}}
                        <div class="col-auto">
                            <label for="search-input" class="form-label fw-semibold mb-1 d-block">🔍 Search</label>
                            <input type="text" id="search-input" class="form-control form-control-sm" placeholder="Search anything...">
                        </div>

                        {{-- Archived rows --}}
                        <div class="col-auto d-flex align-items-end">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="show-archived-toggle">
                                <label class="form-check-label fw-semibold" for="show-archived-toggle">Show archived</label>
                            </div>
                        </div>

                        {{-- Archive / Restore selected --}}
                        <div class="col-auto d-flex align-items-end gap-1">
                            <button type="button" class="btn btn-sm btn-warning d-none" id="archive-selected-btn" title="Soft-delete; restore from Show archived">
                                <i class="fas fa-archive me-1"></i> Archive
                            </button>
                            <button type="button" class="btn btn-sm btn-success d-none" id="restore-selected-btn"
                                title="Select archived row(s), then click to move them back to the active list">
                                <i class="fas fa-undo me-1"></i> Restore
                            </button>
                        </div>
                    </div>
                    <p class="text-muted small mb-2 mb-md-3" id="mfrg-archive-hint">
                        <strong>Restore:</strong> turn on <em>Show archived</em>, tick row(s), then click the green <strong>Restore</strong> button.
                    </p>
                    <div id="mfrg-table"></div>
                </div>
            </div>
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
            let showArchived = false;
            let table;

            function postMfrgInlineUpdate(sku, column, value) {
                return fetch('/mfrg-progresses/inline-update-by-sku', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ sku, column, value })
                }).then(res => res.json());
            }

            table = new Tabulator("#mfrg-table", {
                ajaxURL: "/mfrg-in-progress/data",
                ajaxParams: function () {
                    return { archived: showArchived ? 1 : 0 };
                },
                ajaxConfig: "GET",
                selectableRows: true,
                rowHeader: {
                    formatter: "rowSelection",
                    titleFormatter: "rowSelection",
                    headerSort: false,
                    resizable: false,
                    frozen: true,
                    headerHozAlign: "center",
                    hozAlign: "center",
                    width: 50,
                },
                layout: "fitData",
                height: "700px",
                pagination: true,
                paginationSize: 100,
                paginationCounter: "rows",
                movableColumns: false,
                resizableColumns: true,
                columns: [
                    {
                        title: "#",
                        field: "Image",
                        headerSort: false,
                        cssClass: "mip-new-image-cell",
                        width: 52,
                        minWidth: 52,
                        maxWidth: 52,
                        formatter: (cell) => {
                            const url = cell.getValue();
                            return url
                                ? `<div class="w-100 h-100 p-0 m-0 mip-new-img-aspect"><img src="${url}" data-full="${url}" class="w-100 h-100 hover-thumb" style="object-fit: contain; display: block;" /></div>`
                                : `<span class="text-muted" style="line-height: normal;">N/A</span>`;
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
                        field: "parent",
                        headerFilter: "input",
                        headerFilterPlaceholder: " Filter parent...",
                        width: 180,
                        headerFilterLiveFilter: true,
                    },
                    {
                        title: "SKU",
                        field: "sku", 
                        headerFilter: "input",
                        width: 180,
                        headerFilterPlaceholder: " Filter SKU...",
                        headerFilterLiveFilter: true,
                    },
                    {
                        title: "QTY",
                        field: "qty",
                        hozAlign: "center",
                        formatter: function (cell) {
                            const value = cell.getValue() || "";
                            
                            const html = `
                                    <div style="display:flex; justify-content:center; align-items:center; width:100%;">
                                        <input type="number" 
                                            class="form-control form-control-sm qty-input" 
                                            value="${value}" 
                                            min="0" max="99999" 
                                            style="width:80px; text-align:center;">
                                    </div>
                                `;

                            setTimeout(() => {
                                const input = cell.getElement().querySelector(".qty-input");
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
                        title: "Rate", 
                        field: "rate", 
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const sku = row.sku || '';
                            const currency = row.rate_currency || 'USD';
                            const rate = row.rate || '';

                            return `
                                <div class="input-group input-group-sm" style="width:105px;">
                                    <span class="input-group-text" style="padding: 0 6px;">
                                        <select data-sku="${sku}" data-column="rate_currency" 
                                            class="form-select form-select-sm currency-select auto-save" 
                                            style="border: none; background: transparent; font-size: 13px; padding: 0 2px;">
                                            <option value="USD" ${currency === 'USD' ? 'selected' : ''}>$</option>
                                            <option value="CNY" ${currency === 'CNY' ? 'selected' : ''}>¥</option>
                                        </select>
                                    </span>
                                    <input data-sku="${sku}" data-column="rate" type="number" value="${rate}" 
                                        class="form-control form-control-sm amount-input auto-save" 
                                        style="background: #f9f9f9; font-size: 13px;" />
                                </div>
                            `;
                        },
                        hozAlign: "center",
                        headerHozAlign: "center",
                    },
                    {
                        title: "Supplier",
                        field: "supplier",
                        width: 90,
                        hozAlign: "center",
                        formatter: function(cell){
                            let value = cell.getValue() || "";
                            let options = uniqueSuppliers.map(supplier => {
                                let selected = (supplier === value) ? "selected" : "";
                                return `<option value="${supplier}" ${selected}>${supplier}</option>`;
                            }).join("");

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                    data-sku="${cell.getRow().getData().SKU}" data-column="Supplier" style="width: 90px;">
                                    ${options}
                                </select>`;
                        }
                    },
                    {
                        title: "O Date",
                        field: "created_at",
                        hozAlign: "center",
                        formatter: function (cell) {
                            const rawValue = cell.getValue() || "";
                            const formattedDate = rawValue ? new Date(rawValue).toISOString().split('T')[0] : "";
                            const rowData = cell.getRow().getData();

                            const html = `
                                <div style="display: flex; flex-direction: column; align-items: flex-start;">
                                    <input type="date" class="form-control form-control-sm order_date_input" value="${formattedDate}" style="width:85px;">
                                </div>
                            `;

                            // setTimeout(() => {
                            //     const input = cell.getElement().querySelector(".order_date_input");
                            //     if (input) {
                            //         input.addEventListener("change", function () {
                            //             const newValue = this.value;
                            //             saveLinkUpdate(cell, newValue);
                            //         });
                            //     }
                            // }, 10);

                            return html;
                        }
                    },
                    {
                        title: "D date",
                        field: "delivery_date",
                        hozAlign: "center",
                        formatter: function (cell) {
                            const rawValue = cell.getValue() || "";
                            const formattedDate = rawValue ? new Date(rawValue).toISOString().split('T')[0] : "";
                            const sku = cell.getRow().getData().sku || "";

                            const html = `
                                <div style="display: flex; flex-direction: column; align-items: flex-start;">
                                    <input type="date" class="form-control form-control-sm delivery_date_input" value="${formattedDate}" style="width:85px;">
                                </div>
                            `;

                            setTimeout(() => {
                                const input = cell.getElement().querySelector(".delivery_date_input");
                                if (input) {
                                    input.addEventListener("change", function () {
                                        postMfrgInlineUpdate(sku, "delivery_date", this.value)
                                            .then((res) => {
                                                if (!res.success) {
                                                    alert(res.message || "Failed to save delivery date.");
                                                }
                                            })
                                            .catch(() => alert("Network error while saving."));
                                    });
                                }
                            }, 10);

                            return html;
                        }
                    },
                    {
                        title: "CBM",
                        field: "CBM",
                        hozAlign: "center",
                        formatter: function(cell){
                            const cellValue = cell.getValue();
                            const value = cellValue ? Number(cellValue).toFixed(4) : '0.0000';
                            return value;
                        }
                    },
                    {
                        title: "R2S",
                        field: "ready_to_ship",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue() || "";
                            const rowData = cell.getRow().getData();

                            return `
                                <select class="form-select form-select-sm editable-select"
                                    data-column="ready_to_ship"
                                    data-sku='${rowData["SKU"]}'
                                    style="width: 75px;">
                                    <option value="No" ${value === "No" ? "selected" : ""}>No</option>
                                    <option value="Yes" ${value === "Yes" ? "selected" : ""}>Yes</option>
                                </select>
                            `;
                        },
                    },
                ],
                ajaxResponse: (url, params, response) => {
                    let data = response.data;

                    let filtered = data.filter(item => {
                        let qty = parseFloat(item.qty) || 0;
                        let isParent = item.sku && item.sku.startsWith("PARENT");
                        let isReadyToShip = item.ready_to_ship && item.ready_to_ship.trim().toLowerCase() === "yes";

                        return qty > 0 && !isParent && !isReadyToShip;
                    });

                    uniqueSuppliers = [...new Set(filtered.map(item => item.supplier))].filter(Boolean);
                    return filtered;
                },
            });

            function updateMfrgArchiveButtons() {
                const selected = typeof table.getSelectedRows === 'function' ? table.getSelectedRows() : [];
                const n = selected.length;
                if (showArchived) {
                    $('#archive-selected-btn').addClass('d-none');
                    $('#restore-selected-btn').removeClass('d-none');
                    $('#restore-selected-btn').prop('disabled', n === 0);
                } else {
                    $('#restore-selected-btn').addClass('d-none').prop('disabled', false);
                    $('#archive-selected-btn').toggleClass('d-none', n === 0);
                }
            }

            table.on("rowSelectionChanged", function () {
                updateMfrgArchiveButtons();
            });

            table.on("dataLoaded", function () {
                updateMfrgArchiveButtons();
            });

            $('#show-archived-toggle').on('change', function () {
                showArchived = this.checked;
                table.deselectRow();
                table.replaceData();
                updateMfrgArchiveButtons();
            });

            $('#archive-selected-btn').on('click', function () {
                const rows = table.getSelectedData();
                const skus = rows.map(function (r) { return (r.sku || '').trim(); }).filter(Boolean);
                if (!skus.length) {
                    return;
                }
                if (!confirm('Archive ' + skus.length + ' row(s)? You can restore them with “Show archived”.')) {
                    return;
                }
                fetch('/mfrg-progresses/delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ skus: skus }),
                })
                    .then(function (res) { return res.json(); })
                    .then(function (res) {
                        if (res.success) {
                            table.deselectRow();
                            table.replaceData();
                            updateMfrgArchiveButtons();
                            if (res.message) {
                                alert(res.message);
                            }
                        } else {
                            alert(res.message || 'Archive failed.');
                        }
                    })
                    .catch(function () {
                        alert('Network error while archiving.');
                    });
            });

            $('#restore-selected-btn').on('click', function () {
                const rows = table.getSelectedData();
                const skus = rows.map(function (r) { return (r.sku || '').trim(); }).filter(Boolean);
                if (!skus.length) {
                    return;
                }
                if (!confirm('Restore ' + skus.length + ' row(s) to the active list?')) {
                    return;
                }
                fetch('/mfrg-progresses/restore', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ skus: skus }),
                })
                    .then(function (res) { return res.json(); })
                    .then(function (res) {
                        if (res.success) {
                            table.deselectRow();
                            table.replaceData();
                            updateMfrgArchiveButtons();
                            if (res.message) {
                                alert(res.message);
                            }
                        } else {
                            alert(res.message || 'Restore failed.');
                        }
                    })
                    .catch(function () {
                        alert('Network error while restoring.');
                    });
            });
        });
    </script>
@endsection