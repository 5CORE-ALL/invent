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
        let maxImprovements = 3;
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
                        title: "Marketplace",
                        field: "marketplace",
                        width: 140,
                        formatter: function(cell) {
                            const marketplace = cell.getValue();
                            const icon = marketplaceConfig.icons[marketplace] || 'fas fa-store';
                            const color = marketplaceConfig.colors[marketplace] || '#000';
                            return `<div style="text-align: center;"><i class="${icon}" style="font-size: 24px; color: ${color};"></i><div style="font-size: 10px; margin-top: 3px; text-transform: uppercase; font-weight: 600;">${marketplace}</div></div>`;
                        }
                    },
                    {
                        title: "Current Title",
                        field: "marketplace_title",
                        width: 350,
                        formatter: function(cell) {
                            const title = cell.getValue() || 'No title';
                            const truncated = title.length > 100 ? title.substring(0, 100) + '...' : title;
                            return `<div style="font-size: 12px; line-height: 1.4;">${truncated}</div>`;
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

        function openAiModal(rowData) {
            currentRow = rowData;
            improvementCount = 0;
            isApproved = false;
            currentAiTitle = null;

            // Set modal content
            $('#modalMarketplace').text(currentRow.marketplace.toUpperCase());
            $('#originalTitle').val(currentRow.marketplace_title || 'No title available');
            $('#aiTitle').val('');
            $('#productSku').val(currentRow.sku);
            
            // Set marketplace link
            if (currentRow.marketplace_link) {
                $('#originalLink').attr('href', currentRow.marketplace_link).show();
            } else {
                $('#originalLink').hide();
            }
            
            // Calculate original title stats
            const originalLength = (currentRow.marketplace_title || '').length;
            const maxChars = marketplaceConfig.character_limits[currentRow.marketplace] || 150;
            $('#originalChars').text(`${originalLength} / ${maxChars}`);
            $('#originalRemaining').text(maxChars - originalLength);

            // Reset AI section
            $('#aiChars').text('0 / 0');
            $('#aiRemaining').text('0');
            $('#scoreValue').text('0').removeClass().addClass('score-badge score-low');
            $('#improvementCounter').text('0 / 3');
            
            // Reset buttons
            $('#btnApprove').prop('disabled', true);
            $('#btnImprove').prop('disabled', true);
            $('#btnPush').prop('disabled', true);
            $('#btnCopy').prop('disabled', true);

            $('#aiTitleModal').modal('show');
        }

        async function generateTitle() {
            const description = currentRow.marketplace_title || currentRow.sku;
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
                        marketplace: currentRow.marketplace,
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
            if (!currentAiTitle || improvementCount >= maxImprovements) return;

            $('#btnImprove').prop('disabled', true).html('<span class="loading-spinner"></span> Improving...');

            try {
                const response = await fetch('{{ route("ai.title.generate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({
                        marketplace: currentRow.marketplace,
                        description: currentRow.marketplace_title || currentRow.sku,
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
                    $('#improvementCounter').text(`${improvementCount} / ${maxImprovements}`);
                    
                    if (improvementCount >= maxImprovements) {
                        $('#btnImprove').prop('disabled', true);
                    }
                } else {
                    alert(data.message || 'Failed to improve title');
                }
            } catch (error) {
                alert('Network error. Please try again.');
            } finally {
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
            
            $('#btnPush').prop('disabled', false);
        }

        function approveTitle() {
            isApproved = true;
            $('#btnApprove').removeClass('btn-warning').addClass('btn-success').html('<i class="fas fa-check-circle"></i> Approved');
            $('#btnImprove').prop('disabled', false);
            $('#btnApprove').prop('disabled', true);
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
                        marketplace: currentRow.marketplace,
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
