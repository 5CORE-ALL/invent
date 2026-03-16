@extends('layouts.vertical', ['title' => 'AI Title Manager', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
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

        .marketplace-icon {
            font-size: 20px;
            cursor: pointer;
        }

        .ai-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 12px;
        }

        .ai-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .title-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            height: 100%;
        }

        .title-display {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            min-height: 120px;
            font-size: 14px;
            line-height: 1.6;
        }

        .char-info {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 12px;
            color: #6c757d;
        }

        .score-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 18px;
        }

        .score-low { background: #ff6b6b; color: white; }
        .score-medium { background: #ffd93d; color: #333; }
        .score-high { background: #51cf66; color: white; }

        .btn-action {
            margin: 5px;
            border-radius: 8px;
            font-weight: 500;
            padding: 10px 20px;
            transition: all 0.3s;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .improvement-counter {
            background: #e9ecef;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 13px;
            color: #495057;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'AI Title Manager',
        'sub_title' => 'Manage Marketplace Titles with AI',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div id="ai-title-table"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Title Modal -->
    @include('product-master.partials.ai-title-modal')

    <script>
        let table;
        let currentRow = null;
        let improvementCount = 0;
        let isApproved = false;
        let currentAiTitle = null;

        // Marketplace config
        const marketplaceConfig = @json(config('marketplaces'));

        $(document).ready(function() {
            initializeTable();
        });

        function initializeTable() {
            table = new Tabulator("#ai-title-table", {
                height: "600px",
                layout: "fitDataStretch",
                placeholder: "Loading data...",
                ajaxURL: "{{ route('ai.title.data') }}",
                pagination: true,
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200],
                columns: [
                    {
                        title: "Image",
                        field: "img",
                        width: 80,
                        formatter: function(cell) {
                            const img = cell.getValue();
                            return img ? `<img src="${img}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">` : 
                                       '<div style="width: 50px; height: 50px; background: #e9ecef; border-radius: 5px;"></div>';
                        }
                    },
                    {
                        title: "Parent",
                        field: "parent",
                        width: 120,
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        width: 150,
                    },
                    {
                        title: "Marketplaces",
                        field: "marketplaces",
                        width: 200,
                        formatter: function(cell) {
                            const marketplaces = cell.getValue();
                            let html = '<div style="display: flex; gap: 8px; align-items: center;">';
                            
                            for (const [marketplace, data] of Object.entries(marketplaces)) {
                                const icon = marketplaceConfig.icons[marketplace] || 'fas fa-store';
                                const color = marketplaceConfig.colors[marketplace] || '#000';
                                html += `<div title="${marketplace.toUpperCase()}" style="text-align: center;">
                                    <i class="${icon}" style="font-size: 22px; color: ${color};"></i>
                                </div>`;
                            }
                            
                            html += '</div>';
                            return html;
                        }
                    },
                    {
                        title: "AI",
                        width: 80,
                        formatter: function(cell) {
                            return '<button class="ai-btn"><i class="fas fa-magic"></i></button>';
                        },
                        hozAlign: "center",
                        cellClick: function(e, cell) {
                            openAiModal(cell.getRow().getData());
                        }
                    }
                ],
            });

            // Apply filters
            $('#sku-search').on('keyup', function() {
                table.setFilter("sku", "like", this.value);
            });

            $('#marketplace-filter').on('change', function() {
                const value = this.value;
                if (value === 'all') {
                    table.clearFilter("marketplace");
                } else {
                    table.setFilter("marketplace", "=", value);
                }
            });

            $('#title-status-filter').on('change', function() {
                const value = this.value;
                if (value === 'all') {
                    table.clearFilter("marketplace_title");
                } else if (value === 'has-title') {
                    table.setFilter([
                        {field:"marketplace_title", type:"!=", value:null},
                        {field:"marketplace_title", type:"!=", value:""}
                    ]);
                } else if (value === 'no-title') {
                    table.setFilter([
                        [
                            {field:"marketplace_title", type:"=", value:null},
                            {field:"marketplace_title", type:"=", value:""}
                        ]
                    ]);
                }
            });
        }

        let selectedMarketplace = null;
        let allMarketplaceData = {};

        function openAiModal(rowData) {
            currentRow = rowData;
            allMarketplaceData = rowData.marketplaces || {};
            improvementCount = 0;
            isApproved = false;
            currentAiTitle = null;

            $('#productSku').val(currentRow.sku);
            
            // Create marketplace tabs
            createMarketplaceTabs();
            
            // Select first marketplace by default
            const firstMarketplace = Object.keys(allMarketplaceData)[0];
            if (firstMarketplace) {
                selectMarketplace(firstMarketplace);
            }

            $('#aiTitleModal').modal('show');
        }

        function createMarketplaceTabs() {
            let tabsHtml = '';
            
            for (const [marketplace, data] of Object.entries(allMarketplaceData)) {
                const icon = marketplaceConfig.icons[marketplace] || 'fas fa-store';
                const color = marketplaceConfig.colors[marketplace] || '#000';
                
                tabsHtml += `
                    <button class="btn btn-outline-secondary marketplace-tab" 
                            data-marketplace="${marketplace}"
                            onclick="selectMarketplace('${marketplace}')"
                            style="min-width: 120px;">
                        <i class="${icon}" style="color: ${color};"></i>
                        <span class="ms-2">${marketplace.toUpperCase()}</span>
                    </button>
                `;
            }
            
            $('#marketplaceTabs').html(tabsHtml);
        }

        function selectMarketplace(marketplace) {
            selectedMarketplace = marketplace;
            const data = allMarketplaceData[marketplace];
            
            if (!data) return;
            
            // Update tab active state
            $('.marketplace-tab').removeClass('active btn-primary').addClass('btn-outline-secondary');
            $(`.marketplace-tab[data-marketplace="${marketplace}"]`).removeClass('btn-outline-secondary').addClass('active btn-primary');
            
            // Set original title
            $('#originalTitle').val(data.title || 'No title available');
            
            // Set marketplace link
            if (data.link) {
                $('#originalLink').attr('href', data.link).show();
            } else {
                $('#originalLink').hide();
            }
            
            // Calculate original title stats
            const originalLength = (data.title || '').length;
            const maxChars = marketplaceConfig.character_limits[marketplace] || 150;
            $('#originalChars').text(`${originalLength} / ${maxChars}`);
            $('#originalRemaining').text(maxChars - originalLength);

            // Reset AI section
            $('#aiTitle').val('');
            $('#aiChars').text('0 / 0');
            $('#aiRemaining').text('0');
            $('#scoreValue').text('0').removeClass().addClass('score-badge score-low');
            $('#improvementCounter').text('0');
            $('#seoKeywordsSection').hide();
            $('#improvementsSection').hide();
            
            // Reset state
            improvementCount = 0;
            isApproved = false;
            currentAiTitle = null;
            
            // Reset buttons
            $('#btnApprove').removeClass('btn-success').addClass('btn-warning').html('<i class="fas fa-check-circle"></i> Approve').prop('disabled', true);
            $('#btnImprove').prop('disabled', true);
            $('#btnPush').prop('disabled', true);
            $('#btnCopy').prop('disabled', true);
        }

        async function generateTitle() {
            if (!selectedMarketplace) {
                alert('Please select a marketplace first');
                return;
            }

            const marketplaceData = allMarketplaceData[selectedMarketplace];
            const description = marketplaceData.title || currentRow.sku;
            const keywords = '';

            $('#btnGenerate').prop('disabled', true).html('<span class="loading-spinner"></span> Generating...');

            try {
                const response = await fetch('{{ route("ai.title.generate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({
                        marketplace: selectedMarketplace,
                        description: description,
                        keywords: keywords,
                        mode: 'generate'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    currentAiTitle = data.title;
                    displayAiTitle(data);
                    $('#btnApprove').prop('disabled', false);
                    $('#btnCopy').prop('disabled', false);
                } else {
                    alert(data.message || 'Failed to generate title');
                }
            } catch (error) {
                alert('Network error. Please try again.');
            } finally {
                $('#btnGenerate').prop('disabled', false).html('<i class="fas fa-magic"></i> Generate');
            }
        }

        async function improveTitle() {
            console.log('Improve button clicked', {
                currentAiTitle: currentAiTitle,
                improvementCount: improvementCount,
                isApproved: isApproved
            });

            if (!currentAiTitle) {
                alert('No AI title to improve. Please generate a title first.');
                return;
            }

            if (!isApproved) {
                alert('Please click the Approve button first before improving the title.');
                return;
            }

            console.log('Starting improvement...');
            $('#btnImprove').prop('disabled', true).html('<span class="loading-spinner"></span> Improving...');

            try {
                const marketplaceData = allMarketplaceData[selectedMarketplace];
                const response = await fetch('{{ route("ai.title.generate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({
                        marketplace: selectedMarketplace,
                        description: marketplaceData.title || currentRow.sku,
                        keywords: '',
                        current_title: currentAiTitle,
                        mode: 'improve'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    currentAiTitle = data.title;
                    improvementCount++;
                    displayAiTitle(data);
                    $('#improvementCounter').text(improvementCount);
                    
                    // Always re-enable button (no limit)
                    $('#btnImprove').prop('disabled', false).html('<i class="fas fa-rocket"></i> Improve');
                    console.log('Improvement successful, count:', improvementCount);
                } else {
                    alert(data.message || 'Failed to improve title');
                    $('#btnImprove').prop('disabled', false).html('<i class="fas fa-rocket"></i> Improve');
                }
            } catch (error) {
                console.error('Improve error:', error);
                alert('Network error: ' + error.message);
                $('#btnImprove').prop('disabled', false).html('<i class="fas fa-rocket"></i> Improve');
            }
        }

        function displayAiTitle(data) {
            $('#aiTitle').val(data.title);
            $('#aiChars').text(`${data.characters} / ${data.max_chars}`);
            $('#aiRemaining').text(data.remaining);
            
            // Update score
            const score = data.score;
            let scoreClass = 'score-low';
            if (score >= 80) scoreClass = 'score-high';
            else if (score >= 60) scoreClass = 'score-medium';
            
            $('#scoreValue').text(score).removeClass().addClass('score-badge ' + scoreClass);
            
            // Display SEO Keywords
            if (data.seo_keywords && Object.keys(data.seo_keywords).length > 0) {
                let keywordsHtml = '<div class="d-flex flex-wrap gap-2">';
                for (const [keyword, count] of Object.entries(data.seo_keywords)) {
                    keywordsHtml += `<span class="badge bg-success">${keyword} (${count})</span>`;
                }
                keywordsHtml += '</div>';
                $('#seoKeywordsList').html(keywordsHtml);
                $('#seoKeywordsSection').show();
            } else {
                $('#seoKeywordsSection').hide();
            }
            
            // Display Improvements Needed
            if (data.improvements_needed && data.improvements_needed.length > 0) {
                let improvementsHtml = '';
                data.improvements_needed.forEach(improvement => {
                    improvementsHtml += `<li>${improvement}</li>`;
                });
                $('#improvementsList').html(improvementsHtml);
                
                // Only show if there are actual improvements (not just "Looks good!")
                if (data.improvements_needed[0] !== 'Looks good!') {
                    $('#improvementsSection').show();
                } else {
                    $('#improvementsSection').hide();
                }
            } else {
                $('#improvementsSection').hide();
            }
            
            $('#btnPush').prop('disabled', false);
        }

        function approveTitle() {
            console.log('Approving title...');
            isApproved = true;
            $('#btnApprove').removeClass('btn-warning').addClass('btn-success').html('<i class="fas fa-check-circle"></i> Approved');
            $('#btnImprove').prop('disabled', false).removeClass('disabled');
            $('#btnApprove').prop('disabled', true);
            console.log('Title approved, improve button enabled');
        }

        function copyTitle() {
            const title = $('#aiTitle').val();
            navigator.clipboard.writeText(title).then(() => {
                $('#btnCopy').html('<i class="fas fa-check"></i> Copied!');
                setTimeout(() => {
                    $('#btnCopy').html('<i class="fas fa-copy"></i> Copy');
                }, 2000);
            });
        }

        async function pushToMarketplace() {
            const title = $('#aiTitle').val();
            if (!title) {
                alert('No AI title to push');
                return;
            }

            if (!selectedMarketplace) {
                alert('No marketplace selected');
                return;
            }

            $('#btnPush').prop('disabled', true).html('<span class="loading-spinner"></span> Pushing...');

            try {
                const response = await fetch('{{ route("ai.title.push") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({
                        sku: currentRow.sku,
                        parent: currentRow.parent,
                        marketplace: selectedMarketplace,
                        title: title
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert('✓ Title pushed successfully!');
                    $('#aiTitleModal').modal('hide');
                    table.replaceData();
                } else {
                    alert(data.message || 'Failed to push title');
                }
            } catch (error) {
                alert('Network error. Please try again.');
            } finally {
                $('#btnPush').prop('disabled', false).html('<i class="fas fa-upload"></i> Push to Marketplace');
            }
        }
    </script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'AI Title Manager',
        'sub_title' => 'AI Title Manager',
    ])

    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4><i class="fas fa-magic"></i> AI Title Management</h4>
                
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="width: 200px;">

                    <select id="marketplace-filter" class="form-select form-select-sm" style="width: 150px;">
                        <option value="all">All Marketplaces</option>
                        <option value="amazon">Amazon</option>
                        <option value="ebay">eBay</option>
                        <option value="ebay2">eBay2</option>
                        <option value="ebay3">eBay3</option>
                        <option value="shopify">Shopify</option>
                    </select>

                    <select id="title-status-filter" class="form-select form-select-sm" style="width: 150px;">
                        <option value="all">All Titles</option>
                        <option value="has-title">Has Title</option>
                        <option value="no-title">No Title</option>
                    </select>

                    <button class="btn btn-sm btn-primary" onclick="table.clearFilter()">
                        <i class="fas fa-redo"></i> Reset Filters
                    </button>
                </div>

                <div id="ai-title-table"></div>
            </div>
        </div>
    </div>

    <!-- AI Title Modal -->
    @include('product-master.partials.ai-title-modal')
@endsection
