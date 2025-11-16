@extends('layouts.vertical', ['title' => 'Benefits Video Upload'])

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
@include('layouts.shared.page-title', ['page_title' => 'Benefits Video Upload', 'sub_title' => 'Benefits Video Upload'])

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

                </div>
                <div id="posted-video-req"></div>
            </div>
        </div>
    </div>
</div>

@endsection
@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
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

        const table = new Tabulator("#posted-video-req", {
            index: "Sku",
            ajaxURL: "/benefits-video-upload/view-data",
            ajaxConfig: "GET",
            layout: "fitDataFill",
            pagination: true,
            paginationSize: 50,
            paginationMode: "local",
            movableColumns: false,
            resizableColumns: true,
            height: "650px",
            placeholder: "No data available",
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
                    title: "Video",
                    field: "VideoLink", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Video") ,
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
                    hozAlign: "center"
                },
                { 
                    title: "Amazon", 
                    field: "Amazon", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Amazon"),
                    
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
                    hozAlign: "center" 
                },
                { 
                    title: "Doba", 
                    field: "Doba", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Doba"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "eBay", 
                    field: "eBay", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("eBay"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Temu", 
                    field: "Temu", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Temu"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Macys", 
                    field: "Macys", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Macys"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Wayfair", 
                    field: "Wayfair", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Wayfair"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Reverb", 
                    field: "Reverb", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Reverb"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Shopify B2C", 
                    field: "ShopifyB2C", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Shopify B2C"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Aliexpress", 
                    field: "Aliexpress", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Aliexpress"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "eBay Variation", 
                    field: "eBayVariation", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("eBay Variation"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Shopify Wholesale/DS", 
                    field: "ShopifyWholesale", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Shopify Wholesale/DS"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "eBay 2", 
                    field: "eBay2", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("eBay 2"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Faire", 
                    field: "Faire", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Faire"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Tiktok Shop", 
                    field: "TiktokShop", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Tiktok Shop"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Mercari w/ Ship", 
                    field: "MercariWShip", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Mercari w/ Ship"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "FB Marketplace", 
                    field: "FBMarketplace", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("FB Marketplace"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Business 5Core", 
                    field: "Business5Core", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Business 5Core"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Newegg B2C", 
                    field: "NeweggB2C", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Newegg B2C"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "PLS", 
                    field: "PLS", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("PLS"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Auto DS", 
                    field: "AutoDS", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Auto DS"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Mercari w/o Ship", 
                    field: "MercariWOShip", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Mercari w/o Ship"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Poshmark", 
                    field: "Poshmark", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Poshmark"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Tiendamia", 
                    field: "Tiendamia", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Tiendamia"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Shein", 
                    field: "Shein", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Shein"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Spocket", 
                    field: "Spocket", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Spocket"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Zendrop", 
                    field: "Zendrop", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Zendrop"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Syncee", 
                    field: "Syncee", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Syncee"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Newegg B2B", 
                    field: "NeweggB2B", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Newegg B2B"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Appscenic", 
                    field: "Appscenic", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Appscenic"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "FB Shop", 
                    field: "FBShop", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("FB Shop"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Instagram Shop", 
                    field: "InstagramShop", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Instagram Shop"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Amazon FBA", 
                    field: "AmazonFBA", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Amazon FBA"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Walmart", 
                    field: "Walmart", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Walmart"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "DHGate", 
                    field: "DHGate", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("DHGate"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Bestbuy USA", 
                    field: "BestbuyUSA", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Bestbuy USA"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "YouTube", 
                    field: "YouTube", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("YouTube"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Facebook Page", 
                    field: "FacebookPage", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Facebook Page"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Instagram Page", 
                    field: "InstagramPage", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Instagram Page"),
                    
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
                    hozAlign: "center"  
                },
                { 
                    title: "Tiktok", 
                    field: "Tiktok", 
                    editor: "input", 
                    titleFormatter: titleWithLinkIcon("Tiktok"),
                    
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
                    hozAlign: "center"  
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

        table.on("tableBuilt", function () {
            buildColumnDropdown();
        });

        table.on("cellEdited", function(cell) {
            const rowData = cell.getRow().getData();
            const payload = {
                sku: rowData.Sku,
                value: {
                    VideoLink: rowData.VideoLink || '',
                    Amazon: rowData.Amazon || '',
                    Doba: rowData.Doba || '',
                    eBay: rowData.eBay || '',
                    Temu: rowData.Temu || '',
                    Macys: rowData.Macys || '',
                    Wayfair: rowData.Wayfair || '',
                    Reverb: rowData.Reverb || '',
                    ShopifyB2C: rowData.ShopifyB2C || '',
                    Aliexpress: rowData.Aliexpress || '',
                    eBayVariation: rowData.eBayVariation || '',
                    ShopifyWholesale: rowData.ShopifyWholesale || '',
                    eBay2: rowData.eBay2 || '',
                    Faire: rowData.Faire || '',
                    TiktokShop: rowData.TiktokShop || '',
                    MercariWShip: rowData.MercariWShip || '',
                    FBMarketplace: rowData.FBMarketplace || '',
                    Business5Core: rowData.Business5Core || '',
                    NeweggB2C: rowData.NeweggB2C || '',
                    PLS: rowData.PLS || '',
                    AutoDS: rowData.AutoDS || '',
                    MercariWOShip: rowData.MercariWOShip || '',
                    Poshmark: rowData.Poshmark || '',
                    Tiendamia: rowData.Tiendamia || '',
                    Shein: rowData.Shein || '',
                    Spocket: rowData.Spocket || '',
                    Zendrop: rowData.Zendrop || '',
                    Syncee: rowData.Syncee || '',
                    NeweggB2B: rowData.NeweggB2B || '',
                    Appscenic: rowData.Appscenic || '',
                    FBShop: rowData.FBShop || '',
                    InstagramShop: rowData.InstagramShop || '',
                    AmazonFBA: rowData.AmazonFBA || '',
                    Walmart: rowData.Walmart || '',
                    DHGate: rowData.DHGate || '',
                    BestbuyUSA: rowData.BestbuyUSA || '',
                    YouTube: rowData.YouTube || '',
                    FacebookPage: rowData.FacebookPage || '',
                    InstagramPage: rowData.InstagramPage || '',
                    Tiktok: rowData.Tiktok || ''
                }
            };

            fetch("/benefits-video-upload/save", {
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

    });

    function titleWithLinkIcon(title) {
        return function () {
            return `<div>${title} <i class='fa fa-link'></i></div>`;
        };
    }


</script>