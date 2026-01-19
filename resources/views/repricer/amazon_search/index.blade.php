@extends('layouts.vertical', ['title' => 'Amazon Competitor Search', 'mode' => 'light'])

@section('css')
<link href="{{ URL::asset('build/assets/app-C9T8gcC6.css') }}" rel="stylesheet" type="text/type" />
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .search-container {
        background: #fff;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .product-card {
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        background: #fff;
        transition: all 0.2s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
    }
    
    .product-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
        border-color: #0066c0;
    }
    
    .product-checkbox {
        position: absolute;
        top: 8px;
        left: 8px;
        width: 20px;
        height: 20px;
        cursor: pointer;
        z-index: 10;
    }
    
    .product-image-container {
        position: relative;
        padding: 10px;
        background: #fafafa;
        text-align: center;
        height: 140px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .product-image {
        max-width: 100%;
        max-height: 120px;
        object-fit: contain;
        cursor: pointer;
        transition: transform 0.2s ease;
    }
    
    .product-image:hover {
        transform: scale(1.05);
    }
    
    .product-body {
        padding: 10px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .product-title {
        font-weight: 500;
        color: #0066c0;
        margin-bottom: 8px;
        font-size: 13px;
        line-height: 1.3;
        height: 50px;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-decoration: none;
        cursor: pointer;
    }
    
    .product-title:hover {
        text-decoration: underline;
        color: #004d99;
    }
    
    .product-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-bottom: 8px;
    }
    
    .meta-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .price-badge {
        background: #FFF3E0;
        color: #E65100;
        font-size: 14px;
        font-weight: 700;
        padding: 4px 10px;
    }
    
    .rating-badge {
        background: #FFF8E1;
        color: #F57C00;
        font-size: 11px;
        font-weight: 600;
    }
    
    .asin-badge {
        background: #E8F5E9;
        color: #2E7D32;
        font-family: monospace;
        font-size: 10px;
    }
    
    .search-history {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 15px;
    }
    
    .history-badge {
        background: #E3F2FD;
        color: #1976D2;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .history-badge:hover {
        background: #1976D2;
        color: white;
    }
    
    .loading-spinner {
        display: none;
        text-align: center;
        padding: 40px;
    }
    
    .spinner-border {
        width: 3rem;
        height: 3rem;
    }
    
    .stats-container {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 8px;
        flex: 1;
        text-align: center;
    }
    
    .stat-value {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 14px;
        opacity: 0.9;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Repricer</a></li>
                        <li class="breadcrumb-item active">Amazon Competitor Search</li>
                    </ol>
                </div>
                <h4 class="page-title">Amazon Competitor Discovery</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="search-container">
                <h5 class="mb-3">Search Amazon Products</h5>
                <p class="text-muted mb-4">Enter a search query to discover competitor ASINs and analyze their pricing, ratings, and positions.</p>
                
                <form id="searchForm">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="searchQuery" class="form-label">Search Query</label>
                                <input type="text" class="form-control" id="searchQuery" name="query" 
                                       placeholder="e.g., wireless headphones, yoga mat, kitchen knife set" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="marketplace" class="form-label">Marketplace</label>
                                <select class="form-select" id="marketplace" name="marketplace">
                                    <option value="amazon" selected>Amazon US</option>
                                    <option value="amazon.ca">Amazon CA</option>
                                    <option value="amazon.co.uk">Amazon UK</option>
                                    <option value="amazon.de">Amazon DE</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="mdi mdi-magnify me-2"></i>Search Amazon
                    </button>
                    <button type="button" class="btn btn-secondary btn-lg" id="loadHistoryBtn">
                        <i class="mdi mdi-history me-2"></i>Load History
                    </button>
                </form>

                <div id="searchHistory" class="mt-4" style="display: none;">
                    <h6 class="text-muted">Recent Searches:</h6>
                    <div class="search-history" id="historyContainer"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3 text-muted">Searching Amazon... This may take a moment.</p>
    </div>

    <div class="row mt-4" id="resultsContainer" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">Search Results</h5>
                    
                    <div class="stats-container mb-4" id="statsContainer"></div>
                    
                    <div class="mb-4" id="bulkActionsContainer" style="display: none;">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row align-items-center g-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="selectAllCheckbox" style="width: 22px; height: 22px; cursor: pointer;">
                                            <label class="form-check-label ms-2 fw-bold" for="selectAllCheckbox">
                                                Select All
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="bulkSkuSelect" class="form-label mb-1 fw-bold">Bulk SKU Assignment:</label>
                                        <select class="form-select select2-bulk-sku" id="bulkSkuSelect">
                                            <option value="">Select SKU for all checked products</option>
                                        </select>
                                    </div>
                                    <div class="col-auto ms-auto">
                                        <button type="button" class="btn btn-success btn-lg" id="saveCompetitorsBtn">
                                            <i class="mdi mdi-content-save me-2"></i>Save Selected Competitors
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3" id="resultsContent"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    let availableSkus = [];
    
    // Initialize Select2 for bulk SKU dropdown
    function initializeSelect2() {
        // Destroy existing Select2 instance to avoid duplicates
        try {
            $('.select2-bulk-sku').select2('destroy');
        } catch(e) {
            // Ignore if not initialized
        }
        
        // Initialize bulk SKU dropdown
        $('.select2-bulk-sku').select2({
            theme: 'bootstrap-5',
            placeholder: 'Select SKU for all checked products',
            allowClear: true,
            width: '100%'
        });
    }
    
    // Load SKUs on page load
    loadSkus();
    
    // Search form submission
    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        
        const query = $('#searchQuery').val().trim();
        const marketplace = $('#marketplace').val();
        
        if (!query) {
            alert('Please enter a search query');
            return;
        }
        
        performSearch(query, marketplace);
    });
    
    // Load history button
    $('#loadHistoryBtn').on('click', function() {
        loadSearchHistory();
    });
    
    // Save competitors button
    $('#saveCompetitorsBtn').on('click', function() {
        saveSelectedCompetitors();
    });
    
    // Select all checkbox
    $('#selectAllCheckbox').on('change', function() {
        const isChecked = $(this).prop('checked');
        $('.competitor-checkbox').prop('checked', isChecked);
    });
    
    // Bulk SKU dropdown - no need to set individual dropdowns since we removed them
    $('#bulkSkuSelect').on('change', function() {
        // SKU will be applied to all checked items on save
    });
    
    // Perform search
    function performSearch(query, marketplace) {
        $('#loadingSpinner').show();
        $('#resultsContainer').hide();
        
        $.ajax({
            url: '/repricer/amazon-search/search',
            method: 'POST',
            data: {
                query: query,
                marketplace: marketplace,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                $('#loadingSpinner').hide();
                
                if (response.success) {
                    displayResults(response);
                } else {
                    let errorDetails = response.message || 'Unknown error';
                    if (response.error) {
                        errorDetails += '\n\nDetails: ' + response.error;
                    }
                    if (response.details) {
                        errorDetails += '\n\nAPI Response: ' + JSON.stringify(response.details, null, 2);
                    }
                    alert('Error: ' + errorDetails);
                    console.error('SerpApi Error:', response);
                }
            },
            error: function(xhr) {
                $('#loadingSpinner').hide();
                let errorMsg = 'Failed to fetch search results';
                
                if (xhr.responseJSON) {
                    errorMsg = xhr.responseJSON.message || errorMsg;
                    if (xhr.responseJSON.error) {
                        errorMsg += '\n\nError: ' + xhr.responseJSON.error;
                    }
                    if (xhr.responseJSON.details) {
                        errorMsg += '\n\nDetails: ' + JSON.stringify(xhr.responseJSON.details, null, 2);
                    }
                }
                
                alert('Error: ' + errorMsg);
                console.error('AJAX Error:', xhr.responseJSON || xhr.responseText);
            }
        });
    }
    
    // Display results
    function displayResults(response) {
        const results = response.data || [];
        const totalResults = response.total_results || 0;
        const query = response.query || '';
        
        // Show stats
        const totalPrice = results.length > 0 
            ? results.reduce((sum, r) => sum + (parseFloat(r.price) || 0), 0).toFixed(2)
            : 0;
        
        const avgPrice = results.length > 0 
            ? (results.reduce((sum, r) => sum + (parseFloat(r.price) || 0), 0) / results.filter(r => r.price).length).toFixed(2)
            : 0;
        
        const avgRating = results.length > 0
            ? (results.reduce((sum, r) => sum + (parseFloat(r.rating) || 0), 0) / results.filter(r => r.rating).length).toFixed(2)
            : 0;
        
        $('#statsContainer').html(`
            <div class="stat-card">
                <div class="stat-value">${totalResults}</div>
                <div class="stat-label">Total ASINs Found</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="stat-value">$${totalPrice}</div>
                <div class="stat-label">Total Price Sum</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="stat-value">$${avgPrice}</div>
                <div class="stat-label">Average Price</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="stat-value">${avgRating} ★</div>
                <div class="stat-label">Average Rating</div>
            </div>
        `);
        
        // Display results
        let html = '';
        
        if (results.length === 0) {
            html = '<p class="text-muted">No results found for this query.</p>';
            $('#bulkActionsContainer').hide();
        } else {
            $('#bulkActionsContainer').show();
            
            // Populate bulk SKU dropdown
            let bulkSkuOptions = '<option value="">Select SKU for all checked rows</option>';
            availableSkus.forEach(function(sku) {
                bulkSkuOptions += `<option value="${sku}">${sku}</option>`;
            });
            $('#bulkSkuSelect').html(bulkSkuOptions);
            
            // Reset select all checkbox
            $('#selectAllCheckbox').prop('checked', false);
            
            results.forEach(function(item) {
                const price = item.price ? `$${parseFloat(item.price).toFixed(2)}` : 'N/A';
                const rating = item.rating ? `${parseFloat(item.rating).toFixed(1)} ★` : '';
                const reviews = item.reviews ? `(${item.reviews.toLocaleString()})` : '';
                const image = item.image || 'https://via.placeholder.com/100';
                
                const productLink = `https://www.amazon.com/dp/${item.asin}`;
                const priceValue = item.price || 0;
                
                html += `
                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
                        <div class="product-card">
                            <input type="checkbox" class="competitor-checkbox product-checkbox form-check-input" 
                                   data-asin="${item.asin}" 
                                   data-marketplace="${item.marketplace}"
                                   data-title="${(item.title || '').replace(/"/g, '&quot;')}"
                                   data-price="${priceValue}"
                                   data-link="${productLink}">
                            
                            <div class="product-image-container">
                                <img src="${image}" 
                                     alt="${item.title || 'Product'}" 
                                     class="product-image"
                                     onclick="window.open('${productLink}', '_blank')">
                            </div>
                            
                            <div class="product-body">
                                <a href="${productLink}" 
                                   target="_blank" 
                                   class="product-title text-decoration-none">
                                    ${item.title || 'No title'}
                                </a>
                                
                                <div class="product-meta">
                                    <span class="meta-badge price-badge">${price}</span>
                                    ${rating ? `<span class="meta-badge rating-badge">${rating} ${reviews}</span>` : ''}
                                </div>
                                
                                <div class="product-meta">
                                    <span class="meta-badge asin-badge">${item.asin}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        $('#resultsContent').html(html);
        $('#resultsContainer').show();
        
        // Initialize Select2 after rendering
        setTimeout(function() {
            initializeSelect2();
        }, 100);
    }
    
    // Load search history
    function loadSearchHistory() {
        $.ajax({
            url: '/repricer/amazon-search/history',
            method: 'GET',
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    let html = '';
                    response.data.forEach(function(query) {
                        html += `<span class="history-badge" onclick="loadHistoryQuery('${query}')">${query}</span>`;
                    });
                    $('#historyContainer').html(html);
                    $('#searchHistory').show();
                } else {
                    alert('No search history found');
                }
            },
            error: function() {
                alert('Failed to load search history');
            }
        });
    }
    
    // Load SKUs from server
    function loadSkus() {
        $.ajax({
            url: '/repricer/amazon-search/skus',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    availableSkus = response.data || [];
                    console.log('Loaded ' + availableSkus.length + ' SKUs');
                }
            },
            error: function() {
                console.error('Failed to load SKUs');
            }
        });
    }
    
    // Save selected competitors
    function saveSelectedCompetitors() {
        // Get bulk SKU
        const bulkSku = $('#bulkSkuSelect').val();
        
        if (!bulkSku) {
            alert('Please select a SKU from the Bulk SKU dropdown');
            return;
        }
        
        const competitors = [];
        
        $('.competitor-checkbox:checked').each(function() {
            const checkbox = $(this);
            const asin = checkbox.data('asin');
            const marketplace = checkbox.data('marketplace');
            const productTitle = checkbox.data('title');
            const productLink = checkbox.data('link');
            const price = checkbox.data('price');
            
            competitors.push({
                asin: asin,
                sku: bulkSku, // Use bulk SKU for all checked items
                marketplace: marketplace,
                product_title: productTitle,
                product_link: productLink,
                price: price
            });
        });
        
        if (competitors.length === 0) {
            alert('Please select at least one competitor (check the boxes)');
            return;
        }
        
        // Confirm before saving
        if (!confirm(`Save ${competitors.length} competitor mapping(s)?`)) {
            return;
        }
        
        $.ajax({
            url: '/repricer/amazon-search/store-competitors',
            method: 'POST',
            data: {
                competitors: competitors,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    // Uncheck saved items
                    $('.competitor-checkbox:checked').prop('checked', false);
                } else {
                    alert('Error: ' + (response.message || 'Failed to save competitors'));
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON?.message || 'Failed to save competitors';
                alert('Error: ' + errorMsg);
                console.error('Save Error:', xhr.responseJSON || xhr.responseText);
            }
        });
    }
    
    // Make loadHistoryQuery global
    window.loadHistoryQuery = function(query) {
        $('#searchQuery').val(query);
        performSearch(query, $('#marketplace').val());
    };
});
</script>
@endsection
