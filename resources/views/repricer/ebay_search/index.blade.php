@extends('layouts.vertical', ['title' => 'eBay Competitor Search', 'mode' => 'light'])

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
        border-color: #3665f3;
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
        color: #3665f3;
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
        color: #1a47d6;
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
    
    .shipping-badge {
        background: #E3F2FD;
        color: #1976D2;
        font-size: 11px;
        font-weight: 600;
    }
    
    .condition-badge {
        background: #F3E5F5;
        color: #7B1FA2;
        font-size: 11px;
        font-weight: 600;
    }
    
    .item-badge {
        background: #E8F5E9;
        color: #2E7D32;
        font-family: monospace;
        font-size: 10px;
    }
    
    .seller-badge {
        background: #FFF8E1;
        color: #F57C00;
        font-size: 11px;
        font-weight: 600;
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
                        <li class="breadcrumb-item active">eBay Competitor Search</li>
                    </ol>
                </div>
                <h4 class="page-title">eBay Competitor Discovery</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="search-container">
                <h5 class="mb-3">Search eBay Products</h5>
                <p class="text-muted mb-4">Enter a search query to discover competitor items and analyze their pricing, condition, and seller information.</p>
                
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
                                    <option value="ebay" selected>eBay US</option>
                                    <option value="ebay.ca">eBay CA</option>
                                    <option value="ebay.co.uk">eBay UK</option>
                                    <option value="ebay.de">eBay DE</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="mdi mdi-magnify me-2"></i>Search eBay
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
        <p class="mt-3 text-muted">Searching eBay... This may take a moment.</p>
    </div>

    <div class="row mt-4" id="resultsContainer" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">Search Results</h5>
                    
                    <div class="stats-container mb-4" id="statsContainer"></div>
                    
                    <!-- Sorting and Filtering Controls -->
                    <div class="card bg-light mb-4" id="sortFilterContainer" style="display: none;">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Sort By</label>
                                    <select class="form-select" id="sortBy">
                                        <option value="position">eBay Position (Default)</option>
                                        <option value="price_low_high">Price: Low to High</option>
                                        <option value="price_high_low">Price: High to Low</option>
                                        <option value="condition">Condition</option>
                                        <option value="seller_rating">Seller Rating</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Min Price</label>
                                    <input type="number" class="form-control" id="minPrice" placeholder="$0.00" step="0.01" min="0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Max Price</label>
                                    <input type="number" class="form-control" id="maxPrice" placeholder="$999.99" step="0.01" min="0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Condition</label>
                                    <select class="form-select" id="conditionFilter">
                                        <option value="">All Conditions</option>
                                    </select>
                                </div>
                                <div class="col-12 text-center">
                                    <button type="button" class="btn btn-primary" id="applyFiltersBtn">
                                        <i class="mdi mdi-filter me-2"></i>Apply Filters & Sort
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="resetFiltersBtn">
                                        <i class="mdi mdi-refresh me-2"></i>Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4" id="bulkActionsContainer" style="display: none;">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row g-3">
                                    <!-- Select All Checkbox -->
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="selectAllCheckbox" style="width: 22px; height: 22px; cursor: pointer;">
                                            <label class="form-check-label ms-2 fw-bold" for="selectAllCheckbox">
                                                <i class="mdi mdi-checkbox-multiple-marked"></i> Select All Products
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <!-- Quick Single SKU Assignment -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-info text-white py-2">
                                                <h6 class="mb-0"><i class="mdi mdi-lightning-bolt"></i> Quick Single SKU Assignment</h6>
                                            </div>
                                            <div class="card-body">
                                                <label for="bulkSkuSelect" class="form-label mb-2">Select one SKU:</label>
                                                <select class="form-select select2-bulk-sku" id="bulkSkuSelect">
                                                    <option value="">Choose a SKU...</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Multiple SKU Assignment with Checkboxes -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-warning text-dark py-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0"><i class="mdi mdi-checkbox-multiple-marked-outline"></i> Multiple SKU Assignment</h6>
                                                    <button type="button" class="btn btn-sm btn-light" id="toggleSkuList">
                                                        <i class="mdi mdi-chevron-down"></i> Show SKUs
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="card-body" id="skuCheckboxContainer" style="display: none;">
                                                <div class="mb-2 d-flex gap-2">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllSkus">
                                                        <i class="mdi mdi-checkbox-marked"></i> Select All
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllSkus">
                                                        <i class="mdi mdi-checkbox-blank-outline"></i> Deselect All
                                                    </button>
                                                    <input type="text" class="form-control form-control-sm" id="skuSearchInput" placeholder="Search SKUs...">
                                                </div>
                                                <div id="skuCheckboxList" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 10px;">
                                                    <p class="text-muted">Loading SKUs...</p>
                                                </div>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <i class="mdi mdi-information"></i> 
                                                        Selected: <span id="selectedSkuCount" class="fw-bold text-primary">0</span> SKU(s)
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Save Button -->
                                    <div class="col-12 text-center">
                                        <button type="button" class="btn btn-success btn-lg px-5" id="saveCompetitorsBtn">
                                            <i class="mdi mdi-content-save me-2"></i>Save Selected Competitors
                                        </button>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="mdi mdi-information-outline"></i> 
                                                Competitors will be assigned to all selected or checked SKUs
                                            </small>
                                        </div>
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
    let currentSearchQuery = '';
    let currentMarketplace = 'ebay';
    
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
    
    // Apply filters and sorting
    $('#applyFiltersBtn').on('click', function() {
        applyFiltersAndSort();
    });
    
    // Reset filters
    $('#resetFiltersBtn').on('click', function() {
        $('#sortBy').val('position');
        $('#minPrice').val('');
        $('#maxPrice').val('');
        $('#conditionFilter').val('');
        applyFiltersAndSort();
    });
    
    // Perform search
    function performSearch(query, marketplace) {
        currentSearchQuery = query;
        currentMarketplace = marketplace;
        
        $('#loadingSpinner').show();
        $('#resultsContainer').hide();
        
        $.ajax({
            url: '/repricer/ebay-search/search',
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
                    loadFilterOptions(query);
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
    
    // Apply filters and sorting
    function applyFiltersAndSort() {
        if (!currentSearchQuery) {
            alert('Please perform a search first');
            return;
        }
        
        const sortBy = $('#sortBy').val();
        const minPrice = $('#minPrice').val();
        const maxPrice = $('#maxPrice').val();
        const condition = $('#conditionFilter').val();
        
        $('#loadingSpinner').show();
        
        $.ajax({
            url: '/repricer/ebay-search/results',
            method: 'GET',
            data: {
                query: currentSearchQuery,
                sort_by: sortBy,
                min_price: minPrice,
                max_price: maxPrice,
                condition: condition
            },
            success: function(response) {
                $('#loadingSpinner').hide();
                
                if (response.success) {
                    displayResults(response);
                } else {
                    alert('Error: ' + (response.message || 'Failed to apply filters'));
                }
            },
            error: function(xhr) {
                $('#loadingSpinner').hide();
                alert('Error: Failed to apply filters');
                console.error('Filter Error:', xhr.responseJSON || xhr.responseText);
            }
        });
    }
    
    // Load filter options
    function loadFilterOptions(query) {
        $.ajax({
            url: '/repricer/ebay-search/filter-options',
            method: 'GET',
            data: { query: query },
            success: function(response) {
                if (response.success && response.data) {
                    // Populate condition filter
                    let conditionOptions = '<option value="">All Conditions</option>';
                    if (response.data.conditions && response.data.conditions.length > 0) {
                        response.data.conditions.forEach(function(condition) {
                            conditionOptions += `<option value="${condition}">${condition}</option>`;
                        });
                    }
                    $('#conditionFilter').html(conditionOptions);
                    
                    // Show sort/filter container
                    $('#sortFilterContainer').show();
                }
            },
            error: function() {
                console.error('Failed to load filter options');
            }
        });
    }
    
    // Display results
    function displayResults(response) {
        const results = response.data || [];
        const totalResults = response.total_results || 0;
        const query = response.query || '';
        
        // Calculate stats
        const totalPrice = results.length > 0 
            ? results.reduce((sum, r) => sum + (parseFloat(r.price) || 0), 0).toFixed(2)
            : 0;
        
        const avgPrice = results.length > 0 
            ? (results.reduce((sum, r) => sum + (parseFloat(r.price) || 0), 0) / results.filter(r => r.price).length).toFixed(2)
            : 0;
        
        const totalShipping = results.length > 0
            ? results.reduce((sum, r) => sum + (parseFloat(r.shipping_cost) || 0), 0).toFixed(2)
            : 0;
        
        const avgShipping = results.length > 0
            ? (results.reduce((sum, r) => sum + (parseFloat(r.shipping_cost) || 0), 0) / results.filter(r => r.shipping_cost).length).toFixed(2)
            : 0;
        
        $('#statsContainer').html(`
            <div class="stat-card">
                <div class="stat-value">${totalResults}</div>
                <div class="stat-label">Total Items Found</div>
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
                <div class="stat-value">$${avgShipping}</div>
                <div class="stat-label">Average Shipping</div>
            </div>
        `);
        
        // Display results
        let html = '';
        
        if (results.length === 0) {
            html = '<p class="text-muted">No results found for this query.</p>';
            $('#bulkActionsContainer').hide();
        } else {
            $('#bulkActionsContainer').show();
            
            // Populate bulk SKU dropdown (exclude parent SKUs)
            let bulkSkuOptions = '<option value="">Select SKU for all checked rows</option>';
            availableSkus.forEach(function(sku) {
                // Skip parent SKUs (SKUs with "PARENT" prefix)
                if (!sku.toUpperCase().startsWith('PARENT')) {
                    bulkSkuOptions += `<option value="${sku}">${sku}</option>`;
                }
            });
            $('#bulkSkuSelect').html(bulkSkuOptions);
            
            // Reset select all checkbox
            $('#selectAllCheckbox').prop('checked', false);
            
            results.forEach(function(item) {
                const price = item.price ? `$${parseFloat(item.price).toFixed(2)}` : 'N/A';
                const shipping = item.shipping_cost !== null && item.shipping_cost !== undefined
                    ? (parseFloat(item.shipping_cost) === 0 ? 'FREE' : `$${parseFloat(item.shipping_cost).toFixed(2)}`)
                    : '';
                const condition = item.condition || 'Used';
                const sellerName = item.seller_name || '';
                const sellerRating = item.seller_rating ? `(${item.seller_rating})` : '';
                const image = item.image || 'https://via.placeholder.com/100';
                
                // Use the actual eBay link from the database (from SerpApi)
                const productLink = item.link || `https://www.ebay.com/itm/${item.item_id}`;
                const priceValue = item.price || 0;
                const shippingValue = item.shipping_cost || 0;
                
                html += `
                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
                        <div class="product-card">
                            <input type="checkbox" class="competitor-checkbox product-checkbox form-check-input" 
                                   data-item-id="${item.item_id || ''}" 
                                   data-marketplace="${item.marketplace || 'ebay'}"
                                   data-title="${(item.title || '').replace(/"/g, '&quot;')}"
                                   data-price="${priceValue}"
                                   data-shipping="${shippingValue}"
                                   data-link="${productLink}"
                                   data-image="${(item.image || '').replace(/"/g, '&quot;')}">
                            
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
                                    ${shipping ? `<span class="meta-badge shipping-badge">Ship: ${shipping}</span>` : ''}
                                </div>
                                
                                <div class="product-meta">
                                    <span class="meta-badge condition-badge">${condition}</span>
                                    ${sellerName ? `<span class="meta-badge seller-badge">${sellerName} ${sellerRating}</span>` : ''}
                                </div>
                                
                                <div class="product-meta">
                                    <span class="meta-badge item-badge">${item.item_id}</span>
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
            url: '/repricer/ebay-search/history',
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
            url: '/repricer/ebay-search/skus',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    availableSkus = response.data || [];
                    console.log('Loaded ' + availableSkus.length + ' SKUs');
                    populateSkuCheckboxes();
                }
            },
            error: function() {
                console.error('Failed to load SKUs');
            }
        });
    }
    
    // Populate SKU checkboxes
    function populateSkuCheckboxes() {
        let html = '';
        
        // Filter out parent SKUs (SKUs containing "PARENT" prefix)
        const filteredSkus = availableSkus.filter(function(sku) {
            return !sku.toUpperCase().startsWith('PARENT');
        });
        
        if (filteredSkus.length === 0) {
            html = '<p class="text-muted">No SKUs available</p>';
        } else {
            filteredSkus.forEach(function(sku) {
                html += `
                    <div class="form-check mb-2">
                        <input class="form-check-input sku-checkbox" type="checkbox" value="${sku}" id="sku-${sku.replace(/[^a-zA-Z0-9]/g, '_')}">
                        <label class="form-check-label" for="sku-${sku.replace(/[^a-zA-Z0-9]/g, '_')}" style="cursor: pointer;">
                            ${sku}
                        </label>
                    </div>
                `;
            });
        }
        $('#skuCheckboxList').html(html);
        updateSelectedSkuCount();
    }
    
    // Update selected SKU count
    function updateSelectedSkuCount() {
        const count = $('.sku-checkbox:checked').length;
        $('#selectedSkuCount').text(count);
    }
    
    // Toggle SKU list
    $('#toggleSkuList').on('click', function() {
        const $container = $('#skuCheckboxContainer');
        const $icon = $(this).find('i');
        
        if ($container.is(':visible')) {
            $container.slideUp();
            $icon.removeClass('mdi-chevron-up').addClass('mdi-chevron-down');
            $(this).html('<i class="mdi mdi-chevron-down"></i> Show SKUs');
        } else {
            $container.slideDown();
            $icon.removeClass('mdi-chevron-down').addClass('mdi-chevron-up');
            $(this).html('<i class="mdi mdi-chevron-up"></i> Hide SKUs');
        }
    });
    
    // Select all SKUs
    $('#selectAllSkus').on('click', function() {
        $('.sku-checkbox:visible').prop('checked', true);
        updateSelectedSkuCount();
    });
    
    // Deselect all SKUs
    $('#deselectAllSkus').on('click', function() {
        $('.sku-checkbox').prop('checked', false);
        updateSelectedSkuCount();
    });
    
    // Search SKUs
    $('#skuSearchInput').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.sku-checkbox').each(function() {
            const $checkbox = $(this);
            const sku = $checkbox.val().toLowerCase();
            const $parent = $checkbox.closest('.form-check');
            
            if (sku.includes(searchTerm)) {
                $parent.show();
            } else {
                $parent.hide();
            }
        });
    });
    
    // Update count when checkboxes change
    $(document).on('change', '.sku-checkbox', function() {
        updateSelectedSkuCount();
    });
    
    // Save selected competitors
    function saveSelectedCompetitors() {
        // Get selected SKUs (from dropdown or checkboxes)
        const bulkSku = $('#bulkSkuSelect').val();
        const checkedSkus = $('.sku-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        // Combine both sources of SKUs
        let selectedSkus = [];
        if (bulkSku) {
            selectedSkus.push(bulkSku);
        }
        checkedSkus.forEach(function(sku) {
            if (!selectedSkus.includes(sku)) {
                selectedSkus.push(sku);
            }
        });
        
        // Validate SKU selection
        if (selectedSkus.length === 0) {
            alert('Please select at least one SKU:\n- Use the dropdown for quick single SKU assignment, OR\n- Check SKUs in the Multiple SKU Assignment section');
            return;
        }
        
        // Get selected competitors
        const checkedCompetitors = $('.competitor-checkbox:checked');
        if (checkedCompetitors.length === 0) {
            alert('Please select at least one competitor (check the product boxes)');
            return;
        }
        
        // Build competitors array for all SKU combinations
        const competitors = [];
        
        checkedCompetitors.each(function() {
            const checkbox = $(this);
            const itemId = checkbox.data('item-id');
            const marketplace = checkbox.data('marketplace');
            const productTitle = checkbox.data('title');
            const productLink = checkbox.data('link');
            const image = checkbox.data('image');
            const price = checkbox.data('price');
            const shipping = checkbox.data('shipping');
            
            // Create a competitor entry for EACH selected SKU
            selectedSkus.forEach(function(sku) {
                competitors.push({
                    item_id: String(itemId || ''),
                    sku: String(sku || '').trim(),
                    marketplace: marketplace || 'ebay',
                    product_title: productTitle || null,
                    product_link: productLink || null,
                    image: image || null,
                    price: parseFloat(price) || 0,
                    shipping_cost: parseFloat(shipping) || 0
                });
            });
        });
        
        // Confirm before saving
        const totalMappings = competitors.length;
        const competitorCount = checkedCompetitors.length;
        const skuCount = selectedSkus.length;
        
        const confirmMsg = `You are about to create ${totalMappings} competitor mapping(s):\n\n` +
                          `• ${competitorCount} competitor(s)\n` +
                          `• ${skuCount} SKU(s)\n` +
                          `• Total mappings: ${competitorCount} × ${skuCount} = ${totalMappings}\n\n` +
                          `Selected SKUs:\n${selectedSkus.slice(0, 10).join('\n')}` +
                          (selectedSkus.length > 10 ? `\n...and ${selectedSkus.length - 10} more` : '') +
                          `\n\nContinue?`;
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        // Show loading state
        const $btn = $('#saveCompetitorsBtn');
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-2"></i>Saving...');
        
        $.ajax({
            url: '/repricer/ebay-search/store-competitors',
            method: 'POST',
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            data: JSON.stringify({
                competitors: competitors,
                _token: '{{ csrf_token() }}'
            }),
            success: function(response) {
                if (response.success) {
                    alert(`✅ Success!\n\n${response.message}\n\nMappings created: ${totalMappings}`);
                    
                    // Uncheck saved items
                    $('.competitor-checkbox:checked').prop('checked', false);
                    $('#selectAllCheckbox').prop('checked', false);
                    
                    // Optionally clear SKU selections
                    $('#bulkSkuSelect').val('').trigger('change');
                    $('.sku-checkbox').prop('checked', false);
                    updateSelectedSkuCount();
                } else {
                    alert('Error: ' + (response.message || 'Failed to save competitors'));
                }
            },
            error: function(xhr) {
                let errorMsg = 'Failed to save competitors';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.errors) {
                        errorMsg = 'Validation failed:\n' + Object.values(xhr.responseJSON.errors).flat().join('\n');
                    } else if (xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                }
                alert('Error: ' + errorMsg);
                console.error('Save Error:', xhr.responseJSON || xhr.responseText);
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
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
