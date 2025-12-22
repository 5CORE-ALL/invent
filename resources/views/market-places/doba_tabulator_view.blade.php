@extends('layouts.vertical', ['title' => 'Doba Listing', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .parent-row {
            background-color: #bde0ff !important;
            font-weight: bold !important;
        }

        .tabulator-row.parent-row {
            background-color: #bde0ff !important;
            font-weight: bold !important;
        }

        .tabulator-row.nr-hide {
            background-color: rgba(220, 53, 69, 0.1) !important;
        }

        /* Color coded cells */
        .dil-percent-value {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        .dil-percent-value.red {
            color: #dc3545;
        }

        .dil-percent-value.blue {
            color: #3591dc;
        }

        .dil-percent-value.yellow {
            color: #ffc107;
        }

        .dil-percent-value.green {
            color: #28a745;
        }

        .dil-percent-value.pink {
            color: #e83e8c;
        }

        .dil-percent-value.gray {
            color: #6c757d;
        }

        /* SKU Tooltips */
        .sku-tooltip-container {
            position: relative;
            display: inline-block;
        }

        .sku-tooltip {
            visibility: hidden;
            width: auto;
            min-width: 120px;
            background-color: #fff;
            color: #333;
            text-align: left;
            border-radius: 4px;
            padding: 8px;
            position: absolute;
            z-index: 1001;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #ddd;
            white-space: nowrap;
        }

        .sku-tooltip-container:hover .sku-tooltip {
            visibility: visible;
            opacity: 1;
        }

        .sku-link {
            padding: 4px 0;
            white-space: nowrap;
        }

        .sku-link a {
            color: #0d6efd;
            text-decoration: none;
        }

        .sku-link a:hover {
            text-decoration: underline;
        }

        /* Vertical column headers */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }
        
        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Doba Listing',
        'sub_title' => 'Doba Listing',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <!-- SKU Search -->
                    <input type="text" id="sku-search" class="form-control form-control-sm" 
                           placeholder="Search SKU..." style="width: 200px;">

                    <!-- Filters -->
                    <select id="inventory-filter" class="form-select form-select-sm" style="width: 120px;">
                        <option value="">All INV</option>
                        <option value="positive" selected>INV > 0</option>
                        <option value="zero">INV = 0</option>
                    </select>

                    <select id="parent-filter" class="form-select form-select-sm" style="width: 150px;">
                        <option value="">All Products</option>
                        <option value="parent">Parent Only</option>
                        <option value="child">Child Only</option>
                    </select>

                    <select id="missing-filter" class="form-select form-select-sm" style="width: 150px;">
                        <option value="">All Products</option>
                        <option value="missing">Missing Only</option>
                    </select>

                    <!-- Column Visibility -->
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" 
                                data-bs-toggle="dropdown" id="column-visibility-btn">
                            <i class="fas fa-columns"></i> Columns
                        </button>
                        <div class="dropdown-menu" id="column-dropdown-menu" style="max-height: 300px; overflow-y: auto;">
                            <button class="dropdown-item" id="show-all-columns-btn">Show All Columns</button>
                            <div class="dropdown-divider"></div>
                        </div>
                    </div>

                    <!-- Export -->
                    <button id="export-csv-btn" class="btn btn-success btn-sm">
                        <i class="fas fa-download"></i> Export CSV
                    </button>

                    <!-- Reload -->
                    <button id="reload-data-btn" class="btn btn-info btn-sm">
                        <i class="fas fa-refresh"></i> Reload
                    </button>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-3 mb-2">
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <span id="total-skus" class="badge bg-primary p-2 fw-bold fs-6" style="color: #000 !important;">Total SKUs: 0</span>
                        <span id="zero-sold-count" class="badge bg-danger p-2 fw-bold fs-6" style="color: #000 !important;">0 SOLD: 0</span>
                        <span id="sold-count" class="badge bg-success p-2 fw-bold fs-6" style="color: #000 !important;">SOLD: 0</span>
                        <span id="missing-count" class="badge bg-warning p-2 fw-bold fs-6" style="color: #000 !important; cursor: pointer;" title="Click to filter missing items"><i class="fas fa-exclamation-triangle"></i> Missing: 0</span>
                        <span id="promo-count" class="badge bg-info p-2 fw-bold fs-6" style="color: #000 !important;">Promo Items: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="doba-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div id="doba-table"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        const COLUMN_VIS_KEY = "doba_tabulator_column_visibility";
        let table = null; // Global table reference

        $(document).ready(function() {

            // SKU Search functionality
            $('#sku-search').on('keyup', function() {
                const value = $(this).val();
                table.setFilter("(Child) sku", "like", value);
            });

            table = new Tabulator("#doba-table", {
                ajaxURL: "/doba-data-view",
                ajaxConfig: "GET",
                ajaxResponse: function(url, params, response) {
                    // Process data exactly like doba_pricing_cvr.blade.php
                    if (response && response.data) {
                        const processedData = response.data.map((item, index) => {
                            const inv = Number(item.INV) || 0;
                            const l30 = Number(item.L30) || 0;
                            const dobaL30 = Number(item['doba L30']) || 0;
                            const dobaL60 = Number(item['doba L60']) || 0;
                            const ovDil = inv > 0 ? l30 / inv : 0;
                            const price = Number(item['doba Price']) || 0;
                            const ship = Number(item.Ship_productmaster) || 0;
                            const lp = Number(item.LP_productmaster) || 0;
                            const npft_pct = price > 0 ? ((price * 0.95) - ship - lp) / price : 0;
                            const price_pu = price - ship;
                            const sold = Number(item['doba L30']) || 0;
                            const promo = sold === 0 ? price * 0.90 : 0;
                            const promo_pu = promo - ship;

                            // SPFT and SROI calculations using same formula as PFT and ROI
                            const sprice = Number(item.SPRICE) || 0;
                            const spft = sprice > 0 ? ((sprice * 0.95) - ship - lp) / sprice * 100 : 0;
                            const sprofit = sprice > 0 ? (sprice * 0.95) - ship - lp : 0;
                            const sroi = lp > 0 && sprice > 0 ? (((sprice * 0.95) - ship - lp) / lp) * 100 : 0;

                            return {
                                sl_no: index + 1,
                                'Sl': index + 1,
                                Parent: item.Parent || item.parent || item.parent_asin || item.Parent_ASIN || '(No Parent)',
                                '(Child) sku': item['(Child) sku'] || '',
                                'R&A': item['R&A'] !== undefined ? item['R&A'] : '',
                                INV: inv,
                                L30: l30,
                                ov_dil: ovDil,
                                'doba L30': dobaL30,
                                'doba L60': dobaL60,
                                'doba Price': price,
                                Profit: item.Profit || item['Profit'] || item['profit'] || item['PFT'] || 0,
                                'Sales L30': dobaL30,
                                Roi: item['ROI'] || 0,
                                pickup_price: item['PICK UP PRICE '] || item.pickup_price || 0,
                                is_parent: item['(Child) sku'] ? item['(Child) sku'].toUpperCase().includes("PARENT") : false,
                                raw_data: item || {},
                                NR: item.NR || '',
                                NPFT_pct: npft_pct,
                                Price_PU: price_pu,
                                Promo: promo,
                                Promo_PU: promo_pu,
                                missing: (inv === 0 && dobaL30 === 0) ? 1 : 0, // Missing indicator
                                LP_productmaster: lp,
                                Ship_productmaster: ship,
                                sprice: item.SPRICE || 0,
                                spft: item.SPFT || spft,
                                sprofit: sprofit,
                                sroi: item.SROI || sroi
                            };
                        });
                        return processedData;
                    }
                    return [];
                },
                pagination: "local",
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200],
                layout: "fitData",
                responsiveLayout: "hide",
                placeholder: "No Data Available",
                selectable: false,
                headerFilterPlaceholder: "",
                columns: [
                    {
                        title: "SKU",
                        field: "(Child) sku",
                        width: 150,
                        frozen: true,
                        formatter: function(cell, formatterParams) {
                            const value = cell.getValue();
                            const rawData = cell.getRow().getData().raw_data || {};
                            const imageUrl = rawData.image || '';
                            const buyerLink = rawData['B Link'] || '';
                            const sellerLink = rawData['S Link'] || '';
                            
                            if (buyerLink || sellerLink || imageUrl) {
                                return `
                                    <div class="sku-tooltip-container">
                                        <span class="sku-text">${value}</span>
                                        <div class="sku-tooltip">
                                            ${imageUrl ? `<img src="${imageUrl}" alt="SKU Image" style="max-width:120px;max-height:120px;border-radius:6px;display:block;margin:0 auto 6px auto;">` : ''}
                                            ${buyerLink ? `<div class="sku-link"><a href="${buyerLink}" target="_blank" rel="noopener noreferrer">Buyer link</a></div>` : ''}
                                            ${sellerLink ? `<div class="sku-link"><a href="${sellerLink}" target="_blank" rel="noopener noreferrer">Seller link</a></div>` : ''}
                                        </div>
                                    </div>
                                `;
                            }
                            return value;
                        }
                    },
                    
                    {
                        title: "INV",
                        field: "INV",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value.toString();
                        }
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            return parseFloat(cell.getValue()) || 0;
                        }
                    },
                    {
                        title: "OV DIL",
                        field: "ov_dil",
                        width: 80,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            const percent = value * 100;
                            let textColor = '';
                            if (percent < 16.66) textColor = '#dc3545';
                            else if (percent >= 16.66 && percent < 25) textColor = '#ffc107';
                            else if (percent >= 25 && percent < 50) textColor = '#28a745';
                            else textColor = '#e83e8c';
                            
                            return `<span style="color: ${textColor}; font-weight: bold;">${Math.round(percent)}%</span>`;
                        }
                    },
                    {
                        title: "SOLD",
                        field: "doba L30",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            return parseFloat(cell.getValue()) || 0;
                        }
                    },                    {
                        title: "SOLD L60",
                        field: "doba L60",
                        width: 70,
                        sorter: "number",
                        visible: false,
                        formatter: function(cell, formatterParams) {
                            return cell.getValue() || 0;
                        }
                    },                    {
                        title: "PROMO",
                        field: "Promo",
                        width: 80,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return `<span style="color: #000; font-weight: bold;">$${value.toFixed(2)}</span>`;
                        }
                    },
                    {
                        title: "Promo PU",
                        field: "Promo_PU",
                        width: 90,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return `<span style="color: #000; font-weight: bold;">$${value.toFixed(2)}</span>`;
                        }
                    },
                    {
                        title: "Missing",
                        field: "missing",
                        width: 80,
                        hozAlign: "center",
                        formatter: function(cell, formatterParams) {
                            const value = parseInt(cell.getValue()) || 0;
                            if (value === 1) {
                                return '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i></span>';
                            }
                            return '';
                        }
                    },
                    {
                        title: "PRICE",
                        field: "doba Price",
                        width: 80,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return `$${value.toFixed(2)}`;
                        }
                    },
                    {
                        title: "P Price",
                        field: "pickup_price",
                        width: 100,
                        sorter: "number",
                        frozen: true,
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return `$${value.toFixed(2)}`;
                        }
                    },
                    {
                        title: "Price (PU)",
                        field: "Price_PU",
                        width: 90,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return `$${value.toFixed(2)}`;
                        }
                    },
                    {
                        title: "NPFT%",
                        field: "NPFT_pct",
                        width: 80,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            const percent = value * 100;
                            let textColor = '';
                            // getPftColor logic from pricing CVR
                            if (percent < 10) textColor = '#dc3545'; // red
                            else if (percent >= 10 && percent < 15) textColor = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) textColor = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) textColor = '#28a745'; // green
                            else textColor = '#e83e8c'; // pink
                            
                            return `<span style="color: ${textColor}; font-weight: bold;">${Math.round(percent)}%</span>`;
                        }
                    },
                    {
                        title: "ROI",
                        field: "Roi",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            const percent = value * 100;
                            let color = '';
                            
                            // getRoiColor logic from pricing CVR
                            if (percent < 50) color = '#dc3545'; // red
                            else if (percent >= 50 && percent < 75) color = '#ffc107'; // yellow
                            else if (percent >= 75 && percent <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(percent)}%</span>`;
                        }
                    },
                    {
                        title: "SPRICE",
                        field: "sprice",
                        width: 80,
                        sorter: "number",
                        editor: "number",
                        editorParams: {
                            min: 0,
                            step: 0.01
                        },
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value > 0 ? `<span class="badge bg-primary">$${value.toFixed(2)}</span>` : '';
                        }
                    },
                    {
                        title: "SPFT",
                        field: "spft",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value !== 0 ? `<span class="badge bg-success">${value.toFixed(2)}%</span>` : '';
                        }
                    },
                    {
                        title: "SROI",
                        field: "sroi",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value !== 0 ? `<span class="badge bg-info">${value.toFixed(2)}%</span>` : '';
                        }
                    }
                ],
                rowFormatter: function(row) {
                    const data = row.getData();
                    if (data.is_parent) {
                        row.getElement().classList.add("parent-row");
                    }
                    if (data.NR === 'NRA') {
                        row.getElement().classList.add('nr-hide');
                    }
                }
            });

            // Apply filters
            function applyFilters() {
                const inventoryFilter = $('#inventory-filter').val();
                const parentFilter = $('#parent-filter').val();
                const missingFilter = $('#missing-filter').val();
                
                table.clearFilter(true);
                
                if (inventoryFilter === 'positive') {
                    table.setFilter("INV", ">", 0);
                } else if (inventoryFilter === 'zero') {
                    table.setFilter("INV", "=", 0);
                }
                
                if (parentFilter === 'parent') {
                    table.setFilter("is_parent", "=", true);
                } else if (parentFilter === 'child') {
                    table.setFilter("is_parent", "!=", true);
                }

                if (missingFilter === 'missing') {
                    table.setFilter("missing", "=", 1);
                }
            }

            $('#inventory-filter, #parent-filter, #missing-filter').on('change', function() {
                applyFilters();
            });

            // Update summary badges (same as original CVR logic)
            function updateSummary() {
                const tableData = table.getData("active");
                const filteredData = tableData.filter(row => !row.is_parent);
                
                // For missing count, use all data (not filtered by INV)
                const allData = table.getData();
                const allNonParentData = allData.filter(row => !row.is_parent);
                
                const totalSkus = filteredData.length;
                let zeroSold = 0;
                let sold = 0;
                let missing = 0;
                let promo = 0;

                filteredData.forEach(row => {
                    const inv = parseFloat(row.INV) || 0;
                    const l30 = parseFloat(row.L30) || 0;
                    const promoValue = parseFloat(row.Promo) || 0;
                    
                    // 0 SOLD: L30 == 0, INV > 0
                    if (l30 === 0 && inv > 0) zeroSold++;
                    // SOLD: count all SKUs with any L30
                    if (l30 > 0) sold++;
                    // Promo: has promo value > 0
                    if (promoValue > 0) promo++;
                });

                // Calculate missing from all data (not filtered)
                allNonParentData.forEach(row => {
                    const inv = parseFloat(row.INV) || 0;
                    const l30 = parseFloat(row.L30) || 0;
                    // Missing: INV == 0 AND L30 == 0
                    if (inv === 0 && l30 === 0) missing++;
                });

                $('#total-skus').text('Total SKUs: ' + totalSkus);
                $('#zero-sold-count').text('0 SOLD: ' + zeroSold);
                $('#sold-count').text('SOLD: ' + sold);
                $('#missing-count').html('<i class="fas fa-exclamation-triangle"></i> Missing: ' + missing);
                $('#promo-count').text('Promo Items: ' + promo);
            }

            // Build Column Visibility Dropdown
            function buildColumnDropdown() {
                const columns = table.getColumns();
                const menu = document.getElementById("column-dropdown-menu");
                
                // Clear existing column items
                const existingItems = menu.querySelectorAll('.column-toggle-item');
                existingItems.forEach(item => item.remove());
                
                columns.forEach(column => {
                    if (column.getField() === 'sl_no') return; // Skip sl_no column
                    
                    const item = document.createElement('label');
                    item.className = 'dropdown-item column-toggle-item d-flex align-items-center';
                    item.innerHTML = `
                        <input type="checkbox" class="form-check-input me-2" 
                               data-column="${column.getField()}" 
                               ${column.isVisible() ? 'checked' : ''}>
                        ${column.getDefinition().title}
                    `;
                    menu.appendChild(item);
                });
            }

            // Handle SPRICE cell edit
            table.on('cellEdited', function(cell) {
                const field = cell.getColumn().getField();
                
                if (field === 'sprice') {
                    const rowData = cell.getRow().getData();
                    const sprice = parseFloat(cell.getValue()) || 0;
                    const lp = parseFloat(rowData.LP_productmaster) || 0;
                    const ship = parseFloat(rowData.Ship_productmaster) || 0;
                    const sku = rowData['(Child) sku'];
                    
                    if (sprice > 0 && lp > 0) {
                        // Calculate SPFT% = ((sprice * 0.95) - ship - lp) / sprice * 100
                        const spft = ((sprice * 0.95) - ship - lp) / sprice * 100;
                        
                        // Calculate SROI% = ((sprice * 0.95) - ship - lp) / lp * 100
                        const sroi = ((sprice * 0.95) - ship - lp) / lp * 100;
                        
                        // Update row data
                        cell.getRow().update({
                            spft: spft,
                            sroi: sroi
                        });
                        
                        // Save to backend
                        $.ajax({
                            url: '/doba/save-sprice',
                            method: 'POST',
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                sku: sku,
                                sprice: sprice,
                                spft_percent: spft,
                                sroi_percent: sroi
                            },
                            success: function(response) {
                                showToast('success', 'SPRICE updated successfully');
                            },
                            error: function(xhr) {
                                showToast('danger', 'Failed to update SPRICE');
                                console.error(xhr);
                            }
                        });
                    }
                }
            });

            // Wait for table to be built
            table.on('tableBuilt', function() {
                buildColumnDropdown();
                updateSummary();
                applyFilters(); // Apply default INV > 0 filter
            });

            table.on('dataLoaded', function() {
                setTimeout(() => {
                    updateSummary();
                }, 100);
            });

            table.on('renderComplete', function() {
                setTimeout(() => {
                    updateSummary();
                }, 100);
            });

            // Toggle column from dropdown
            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.type === 'checkbox') {
                    const columnField = e.target.getAttribute('data-column');
                    const column = table.getColumn(columnField);
                    
                    if (e.target.checked) {
                        column.show();
                    } else {
                        column.hide();
                    }
                }
            });

            // Show All Columns button
            document.getElementById("show-all-columns-btn").addEventListener("click", function() {
                table.getColumns().forEach(column => {
                    column.show();
                });
                buildColumnDropdown();
            });

            // Export CSV
            $('#export-csv-btn').on('click', function() {
                table.download("csv", "doba_pricing_cvr_export.csv");
            });

            // Reload Data
            $('#reload-data-btn').on('click', function() {
                table.replaceData("/doba-data-view");
            });

            // Toast notification
            function showToast(type, message) {
                const toast = $(`
                    <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">${message}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `);
                $('.toast-container').append(toast);
                const bsToast = new bootstrap.Toast(toast[0]);
                bsToast.show();
                setTimeout(() => toast.remove(), 5000);
            }

            // Missing filter toggle on badge click
            let missingFilterActive = false;
            $('#missing-count').on('click', function() {
                if (missingFilterActive) {
                    // Remove filter
                    table.clearFilter();
                    $('#missing-filter').val('');
                    $(this).css({
                        'background-color': '#ffc107',
                        'color': '#000'
                    });
                    showToast('info', 'Showing all items');
                } else {
                    // Apply missing filter
                    table.setFilter("missing", "=", 1);
                    $('#missing-filter').val('missing');
                    $(this).css({
                        'background-color': '#dc3545',
                        'color': '#ffffff'
                    });
                    const filteredCount = table.getData("active").length;
                    showToast('warning', `Filtered to ${filteredCount} missing items`);
                }
                missingFilterActive = !missingFilterActive;
            });
        });
    </script>
@endsection
