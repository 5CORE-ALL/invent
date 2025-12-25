@extends('layouts.vertical', ['title' => 'Temu Pricing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        
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

        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* eBay-style color coding */
        .dil-percent-value {
            font-weight: bold;
            background: none !important;
            background-color: transparent !important;
        }

        .dil-percent-value.red {
            color: #dc3545 !important;
            background: none !important;
        }

        .dil-percent-value.blue {
            color: #3591dc !important;
            background: none !important;
        }

        .dil-percent-value.yellow {
            color: #ffc107 !important;
            background: none !important;
        }

        .dil-percent-value.green {
            color: #28a745 !important;
            background: none !important;
        }

        .dil-percent-value.pink {
            color: #e83e8c !important;
            background: none !important;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Temu Pricing',
        'sub_title' => 'Temu Pricing',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Temu Pricing</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-arrow-down"></i> Decrease Mode
                    </button>
                </div>

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Basic Counts -->
                        <span class="badge bg-primary fs-6 p-2" id="total-products-badge" style="color: black; font-weight: bold;">Total Products: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: black; font-weight: bold;">Total Quantity: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color: black; font-weight: bold;">0 Sold Count: 0</span>
                        
                        <!-- Pricing & Performance -->
                        <span class="badge bg-info fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-cvr-badge" style="color: black; font-weight: bold;">Avg CVR: 0.0%</span>
                        
                        <!-- Financial Totals -->
                        <span class="badge bg-success fs-6 p-2" id="total-revenue-badge" style="color: black; font-weight: bold;">Total Revenue: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-profit-badge" style="color: black; font-weight: bold;">Total Profit: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-lp-badge" style="color: black; font-weight: bold;">Total LP: $0</span>
                        
                        <!-- Percentages (Gross) -->
                        <span class="badge bg-success fs-6 p-2" id="avg-gprft-badge" style="color: black; font-weight: bold;">Avg GPRFT%: 0%</span>
                        <span class="badge bg-primary fs-6 p-2" id="avg-groi-badge" style="color: black; font-weight: bold;">Avg GROI%: 0%</span>
                        
                        <!-- Advertising Metrics -->
                        <span class="badge bg-danger fs-6 p-2" id="total-spend-badge" style="color: black; font-weight: bold;">Total Spend: $0.00</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-ads-badge" style="color: black; font-weight: bold;">Ads %: 0%</span>
                        <span class="badge bg-danger fs-6 p-2" id="total-tcos-badge" style="color: black; font-weight: bold;">Total TCOS: 0%</span>
                        
                        <!-- Percentages (Net) -->
                        <span class="badge bg-success fs-6 p-2" id="avg-npft-badge" style="color: black; font-weight: bold;">Avg NPFT%: 0%</span>
                        <span class="badge bg-primary fs-6 p-2" id="avg-nroi-badge" style="color: black; font-weight: bold;">Avg NROI%: 0%</span>
                        
                        <!-- Engagement -->
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge" style="color: black; font-weight: bold;">Total Views: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="total-temu-l30-badge" style="color: black; font-weight: bold;">Total Temu L30: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-skus-count" class="badge bg-primary">0 SKUs selected</span>
                        <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                            <option value="percentage">Percentage</option>
                            <option value="dollar">Dollar</option>
                        </select>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                               placeholder="Enter %" style="width: 150px;" step="0.01" min="0">
                        <button id="apply-discount-btn" class="btn btn-sm btn-warning">
                            <i class="fas fa-check"></i> Apply Discount
                        </button>
                    </div>
                </div>
                <div id="temu-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                    </div>
                    <div id="temu-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "temu_decrease_column_visibility";
    let table = null;
    let decreaseModeActive = false;
    let selectedSkus = new Set();
    
    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    $(document).ready(function() {
        $('#decrease-btn').on('click', function() {
            decreaseModeActive = !decreaseModeActive;
            const selectColumn = table.getColumn('_select');
            
            if (decreaseModeActive) {
                selectColumn.show();
                $(this).removeClass('btn-warning').addClass('btn-danger');
                $(this).html('<i class="fas fa-times"></i> Cancel Decrease');
            } else {
                selectColumn.hide();
                $(this).removeClass('btn-danger').addClass('btn-warning');
                $(this).html('<i class="fas fa-arrow-down"></i> Decrease Mode');
                selectedSkus.clear();
                updateSelectedCount();
                updateSelectAllCheckbox();
            }
        });

        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            const filteredData = table.getData('active');
            
            filteredData.forEach(row => {
                const sku = row['sku'];
                if (sku) {
                    if (isChecked) {
                        selectedSkus.add(sku);
                    } else {
                        selectedSkus.delete(sku);
                    }
                }
            });
            
            $('.sku-select-checkbox').each(function() {
                const sku = $(this).data('sku');
                $(this).prop('checked', selectedSkus.has(sku));
            });
            
            updateSelectedCount();
        });

        $(document).on('change', '.sku-select-checkbox', function() {
            const sku = $(this).data('sku');
            if ($(this).prop('checked')) {
                selectedSkus.add(sku);
            } else {
                selectedSkus.delete(sku);
            }
            updateSelectedCount();
            updateSelectAllCheckbox();
        });

        $('#apply-discount-btn').on('click', function() {
            applyDiscount();
        });

        $('#discount-percentage-input').on('keypress', function(e) {
            if (e.which === 13) {
                applyDiscount();
            }
        });

        function updateSelectedCount() {
            const count = selectedSkus.size;
            $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
            $('#discount-input-container').toggle(count > 0);
        }

        function updateSelectAllCheckbox() {
            if (!table) return;
            
            const filteredData = table.getData('active');
            
            if (filteredData.length === 0) {
                $('#select-all-checkbox').prop('checked', false);
                return;
            }
            
            const filteredSkus = new Set(filteredData.map(row => row['sku']).filter(sku => sku));
            const allFilteredSelected = filteredSkus.size > 0 && 
                Array.from(filteredSkus).every(sku => selectedSkus.has(sku));
            
            $('#select-all-checkbox').prop('checked', allFilteredSelected);
        }

        function applyDiscount() {
            const discountValue = parseFloat($('#discount-percentage-input').val());
            const discountType = $('#discount-type-select').val();
            
            if (isNaN(discountValue) || discountValue <= 0) {
                showToast('Please enter a valid discount value', 'error');
                return;
            }

            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU', 'error');
                return;
            }

            const allData = table.getData('all');
            let updatedCount = 0;

            allData.forEach(row => {
                const sku = row['sku'];
                if (selectedSkus.has(sku)) {
                    const currentPrice = parseFloat(row['base_price']) || 0;
                    let newPrice;
                    
                    if (discountType === 'percentage') {
                        newPrice = currentPrice * (1 - discountValue / 100);
                    } else {
                        newPrice = currentPrice - discountValue;
                    }
                    
                    if (newPrice > 0) {
                        row['base_price'] = newPrice.toFixed(2);
                        updatedCount++;
                    }
                }
            });

            table.replaceData(allData);
            showToast(`Discount applied to ${updatedCount} SKU(s)`, 'success');
            $('#discount-percentage-input').val('');
            updateSummary();
        }

        function updateSummary() {
            const data = table.getData("active");
            console.log('updateSummary called, data length:', data.length);
            if (data.length > 0) {
                console.log('Sample row:', data[0]);
            }
            
            let totalProducts = data.length;
            let totalQuantity = 0;
            let totalPriceWeighted = 0;
            let totalQty = 0;
            let totalRevenue = 0;
            let totalProfit = 0;
            let totalLp = 0;
            let totalGprft = 0;
            let totalGroi = 0;
            let totalAds = 0;
            let totalNpft = 0;
            let totalNroi = 0;
            let totalCvr = 0;
            let totalDil = 0;
            let totalSpend = 0;
            let totalViews = 0;
            let totalTemuL30 = 0;
            let totalInv = 0;
            let cvrCount = 0;
            let dilCount = 0;
            let zeroSoldCount = 0;
            
            data.forEach(row => {
                const qty = parseInt(row['quantity']) || 0;
                const price = parseFloat(row['base_price']) || 0;
                totalQuantity += qty;
                totalPriceWeighted += price * qty;
                totalQty += qty;
                
                // Revenue = Temu Price × Temu L30
                const temuPrice = parseFloat(row['temu_price']) || 0;
                const temuL30 = parseInt(row['temu_l30']) || 0;
                totalRevenue += temuPrice * temuL30;
                
                // Profit from row data
                totalProfit += parseFloat(row['profit']) || 0;
                
                // LP (Landing Price / COGS) from row data
                totalLp += parseFloat(row['lp']) || 0;
                
                // Percentage metrics (for averaging)
                totalGprft += parseFloat(row['profit_percent']) || 0;
                totalGroi += parseFloat(row['roi_percent']) || 0;
                totalAds += parseFloat(row['ads_percent']) || 0;
                totalNpft += parseFloat(row['npft_percent']) || 0;
                totalNroi += parseFloat(row['nroi_percent']) || 0;
                
                // CVR% (only count non-zero values for average)
                const cvr = parseFloat(row['cvr_percent']) || 0;
                if (cvr > 0) {
                    totalCvr += cvr;
                    cvrCount++;
                }
                
                // DIL% (only count non-zero values for average)
                const dil = parseFloat(row['dil_percent']) || 0;
                if (dil > 0) {
                    totalDil += dil;
                    dilCount++;
                }
                
                // Ad spend and views
                totalSpend += parseFloat(row['spend']) || 0;
                totalViews += parseInt(row['product_clicks']) || 0;
                totalTemuL30 += temuL30;
                totalInv += parseInt(row['inventory']) || 0;
                
                // Count SKUs with 0 sold (Temu L30 = 0)
                if (temuL30 === 0) {
                    zeroSoldCount++;
                }
            });
            
            // Calculate averages
            const avgPrice = totalQty > 0 ? totalPriceWeighted / totalQty : 0;
            const avgGprft = totalProducts > 0 ? totalGprft / totalProducts : 0;
            const avgGroi = totalProducts > 0 ? totalGroi / totalProducts : 0;
            const avgAds = totalProducts > 0 ? totalAds / totalProducts : 0;
            const avgNpft = totalProducts > 0 ? totalNpft / totalProducts : 0;
            const avgNroi = totalProducts > 0 ? totalNroi / totalProducts : 0;
            const avgCvr = cvrCount > 0 ? totalCvr / cvrCount : 0;
            const avgDil = dilCount > 0 ? totalDil / dilCount : 0;
            
            // Calculate TCOS: (Total Ad Spend / Total Revenue) × 100
            const totalTcos = totalRevenue > 0 ? (totalSpend / totalRevenue) * 100 : 0;
            
            // Update badges
            $('#total-products-badge').text('Total Products: ' + totalProducts.toLocaleString());
            $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
            $('#zero-sold-count-badge').text('0 Sold Count: ' + zeroSoldCount.toLocaleString());
            $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
            $('#avg-cvr-badge').text('Avg CVR: ' + avgCvr.toFixed(1) + '%');
            $('#avg-dil-badge').text('Avg DIL: ' + Math.round(avgDil) + '%');
            $('#total-revenue-badge').text('Total Revenue: $' + Math.round(totalRevenue).toLocaleString());
            $('#total-profit-badge').text('Total Profit: $' + Math.round(totalProfit).toLocaleString());
            $('#total-lp-badge').text('Total LP: $' + Math.round(totalLp).toLocaleString());
            $('#avg-gprft-badge').text('Avg GPRFT%: ' + avgGprft.toFixed(1) + '%');
            $('#avg-groi-badge').text('Avg GROI%: ' + avgGroi.toFixed(1) + '%');
            $('#total-spend-badge').text('Total Spend: $' + totalSpend.toFixed(2));
            $('#avg-ads-badge').text('Ads %: ' + Math.round(avgAds) + '%');
            $('#total-tcos-badge').text('Total TCOS: ' + Math.round(totalTcos) + '%');
            $('#avg-npft-badge').text('Avg NPFT%: ' + avgNpft.toFixed(1) + '%');
            $('#avg-nroi-badge').text('Avg NROI%: ' + avgNroi.toFixed(1) + '%');
            $('#total-views-badge').text('Total Views: ' + totalViews.toLocaleString());
            $('#total-temu-l30-badge').text('Total Temu L30: ' + totalTemuL30.toLocaleString());
            $('#total-inv-badge').text('Total INV: ' + totalInv.toLocaleString());
        }

        // eBay-style color functions
        const getPftColor = (value) => {
            const percent = parseFloat(value);
            if (percent < 10) return 'red';
            if (percent >= 10 && percent < 15) return 'yellow';
            if (percent >= 15 && percent < 20) return 'blue';
            if (percent >= 20 && percent <= 40) return 'green';
            return 'pink';
        };

        const getRoiColor = (value) => {
            const percent = parseFloat(value);
            if (percent < 50) return 'red';
            if (percent >= 50 && percent < 75) return 'yellow';
            if (percent >= 75 && percent <= 125) return 'green';
            return 'pink';
        };

        table = new Tabulator("#temu-table", {
            ajaxURL: "/temu-decrease-data",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            columns: [
                {
                    title: '<input type="checkbox" id="select-all-checkbox">',
                    field: "_select",
                    headerSort: false,
                    frozen: true,
                    width: 50,
                    visible: false,
                    formatter: function(cell) {
                        const sku = cell.getRow().getData()['sku'];
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isChecked}>`;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    }
                },
               
                {
                    title: "Image",
                    field: "image_path",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value) {
                            return `<img src="${value}" alt="Product" style="width: 50px; height: 50px; object-fit: cover;">`;
                        }
                        return '';
                    },
                    headerSort: false,
                    width: 80
                },
                {
                    title: "SKU",
                    field: "sku",
                    headerFilter: "input",
                    frozen: true,
                    width: 150
                },
                {
                    title: "INV",
                    field: "inventory",
                    hozAlign: "center",
                    sorter: "number",
                    width: 80
                },
                {
                    title: "OVL30",
                    field: "ovl30",
                    hozAlign: "center",
                    sorter: "number",
                    width: 80
                },
                {
                    title: "Temu L30",
                    field: "temu_l30",
                    hozAlign: "center",
                    sorter: "number",
                    width: 80
                },
                {
                    title: "Dil%",
                    field: "dil_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const dil = parseFloat(cell.getValue()) || 0;
                        
                        let color = '';
                        if (dil < 16.66) color = '#a00211'; // red (includes 0)
                        else if (dil >= 16.66 && dil < 25) color = '#ffc107'; // yellow
                        else if (dil >= 25 && dil < 50) color = '#28a745'; // green
                        else color = '#e83e8c'; // pink (50 and above)
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                    },
                    width: 80
                },
                {
                    title: "CVR %",
                    field: "cvr_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        let color = '#000';
                        
                        // eBay CVR color logic
                        if (value <= 4) color = '#a00211'; // red
                        else if (value > 4 && value <= 7) color = '#ffc107'; // yellow
                        else if (value > 7 && value <= 10) color = '#28a745'; // green
                        else color = '#ff1493'; // pink for > 10
                        
                        return `<span style="color: ${color}; font-weight: 600;">${value.toFixed(1)}%</span>`;
                    },
                    width: 90
                },
                 {
                    title: "Views",
                    field: "product_clicks",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue()) || 0;
                        return value.toLocaleString();
                    },
                    width: 110
                },
               
                //  {
                //     title: "CTR",
                //     field: "ctr",
                //     hozAlign: "center",
                //     sorter: "number",
                //     formatter: function(cell) {
                //         const value = parseFloat(cell.getValue()) || 0;
                //         return value.toFixed(2) + '%';
                //     },
                //     width: 80
                // },
                {
                    title: "Base Price",
                    field: "base_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    width: 120,
                    editor: "number",
                    editorParams: {
                        min: 0,
                        step: 0.01
                    }
                },
                {
                    title: "Temu Price",
                    field: "temu_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const basePrice = parseFloat(cell.getRow().getData()['base_price']) || 0;
                        const temuPrice = basePrice < 26.99 ? basePrice + 2.99 : basePrice;
                        return '$' + temuPrice.toFixed(2);
                    },
                    width: 120
                },
                {
                    title: "PRFT AMT",
                    field: "profit",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value < 0 ? '#dc3545' : (value > 0 ? '#28a745' : '#6c757d');
                        return `<span style="color: ${color}; font-weight: 600;">$${value.toFixed(2)}</span>`;
                    },
                    width: 100
                },
                {
                    title: "GPRFT %",
                    field: "profit_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getPftColor(value);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    },
                    width: 100
                },
                {
                    title: "GROI %",
                    field: "roi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getRoiColor(value);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    },
                    width: 100
                },
               
               
                
                {
                    title: "LP",
                    field: "lp",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    width: 100
                },
                {
                    title: "Temu Ship",
                    field: "temu_ship",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    width: 100
                },

                 {
                    title: "Spend",
                    field: "spend",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        return '$' + value.toFixed(2);
                    },
                    width: 100
                },
                {
                    title: "ADS%",
                    field: "ads_percent",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                        // Custom sorter to handle the 100% case properly
                        const aData = aRow.getData();
                        const bData = bRow.getData();
                        
                        const aSpend = parseFloat(aData['spend'] || 0);
                        const bSpend = parseFloat(bData['spend'] || 0);
                        const aTemuL30 = parseFloat(aData['temu_l30'] || 0);
                        const bTemuL30 = parseFloat(bData['temu_l30'] || 0);
                        
                        // Calculate effective ADS% (100 if spend > 0 and sales = 0)
                        let aVal = parseFloat(a || 0);
                        let bVal = parseFloat(b || 0);
                        
                        if (aSpend > 0 && aTemuL30 === 0) aVal = 100;
                        if (bSpend > 0 && bTemuL30 === 0) bVal = 100;
                        
                        return aVal - bVal;
                    },
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const rowData = cell.getRow().getData();
                        const spend = parseFloat(rowData['spend'] || 0);
                        const temuL30 = parseFloat(rowData['temu_l30'] || 0);
                        let color = '#000';
                        
                        // If spend > 0 but no sales, show 100% in red
                        if (spend > 0 && temuL30 === 0) {
                            return `<span style="color: #a00211; font-weight: 600;">100%</span>`;
                        }
                        
                        // eBay ACOS color logic (includes 0 and 100 conditions)
                        if (value == 0 || value == 100) color = '#a00211'; // red
                        else if (value > 0 && value <= 7) color = '#ff1493'; // pink
                        else if (value > 7 && value <= 14) color = '#28a745'; // green
                        else if (value > 14 && value <= 21) color = '#ffc107'; // yellow
                        else if (value > 21) color = '#a00211'; // red
                        
                        return `<span style="color: ${color}; font-weight: 600;">${value.toFixed(1)}%</span>`;
                    },
                    width: 90
                },
                {
                    title: "NPFT %",
                    field: "npft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getPftColor(value);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    },
                    width: 100
                },
                {
                    title: "NROI %",
                    field: "nroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getRoiColor(value);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    },
                    width: 100
                },
                {
                    title: "Goods ID",
                    field: "goods_id",
                    width: 150,
                    visible: false
                }
            ]
        });

        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("sku", "like", value);
            updateSummary();
        });

        table.on('cellEdited', function(cell) {
            const row = cell.getRow();
            const data = row.getData();
            const field = cell.getColumn().getField();
            
            if (field === 'base_price') {
                const newPrice = parseFloat(cell.getValue());
                if (newPrice < 0) {
                    showToast('Price cannot be negative', 'error');
                    cell.restoreOldValue();
                    return;
                }
                
                $.ajax({
                    url: '/temu-pricing/update-price',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: data['sku'],
                        base_price: newPrice
                    },
                    success: function(response) {
                        showToast('Price updated successfully', 'success');
                        updateSummary();
                    },
                    error: function(xhr) {
                        showToast('Failed to update price', 'error');
                        cell.restoreOldValue();
                    }
                });
            }
        });

        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            fetch('/temu-decrease-column-visibility', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(savedVisibility => {
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (def.field && def.field !== '_select') {
                        const visible = savedVisibility[def.field] !== undefined ? savedVisibility[def.field] : def.visible !== false;
                        const li = document.createElement('li');
                        li.className = 'dropdown-item';
                        li.innerHTML = `
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="${def.field}" 
                                       id="col-${def.field}" ${visible ? 'checked' : ''}>
                                <label class="form-check-label" for="col-${def.field}">
                                    ${def.title}
                                </label>
                            </div>
                        `;
                        menu.appendChild(li);
                    }
                });
            });
        }

        function saveColumnVisibilityToServer() {
            const visibility = {};
            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field) {
                    visibility[def.field] = col.isVisible();
                }
            });

            fetch('/temu-decrease-column-visibility', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    visibility: visibility
                })
            });
        }

        function applyColumnVisibilityFromServer() {
            fetch('/temu-decrease-column-visibility', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(savedVisibility => {
                table.getColumns().forEach(col => {
                    const field = col.getField();
                    if (field && savedVisibility[field] !== undefined) {
                        if (savedVisibility[field]) {
                            col.show();
                        } else {
                            col.hide();
                        }
                    }
                });
            });
        }

        table.on('tableBuilt', function() {
            applyColumnVisibilityFromServer();
            buildColumnDropdown();
        });

        table.on('dataLoaded', function() {
            updateSummary();
            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            }, 100);
        });

        table.on('renderComplete', function() {
            updateSummary();
            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            }, 100);
        });

        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.type === 'checkbox') {
                const field = e.target.value;
                const col = table.getColumn(field);
                if (e.target.checked) {
                    col.show();
                } else {
                    col.hide();
                }
                saveColumnVisibilityToServer();
            }
        });

        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => {
                col.show();
            });
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });
    });
</script>
@endsection
