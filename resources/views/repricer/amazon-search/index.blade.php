@extends('layouts.vertical', ['page_title' => 'Amazon Competitor Search', 'mode' => 'light'])

@section('css')
<link href="{{ URL::asset('build/assets/app-C9T8gcC6.css') }}" rel="stylesheet" type="text/css" />
<style>
    .search-container {
        background: #fff;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .result-card {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        display: flex;
        align-items: flex-start;
        transition: all 0.3s ease;
    }
    
    .result-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }
    
    .result-image {
        width: 100px;
        height: 100px;
        object-fit: contain;
        margin-right: 20px;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 5px;
        background: #fff;
    }
    
    .result-content {
        flex: 1;
    }
    
    .result-title {
        font-weight: 600;
        color: #0066c0;
        margin-bottom: 8px;
        font-size: 16px;
        line-height: 1.4;
    }
    
    .result-meta {
        display: flex;
        gap: 20px;
        margin-top: 10px;
        flex-wrap: wrap;
    }
    
    .meta-item {
        display: flex;
        align-items: center;
        gap: 5px;
        color: #555;
        font-size: 14px;
    }
    
    .price {
        color: #B12704;
        font-weight: 700;
        font-size: 18px;
    }
    
    .rating {
        color: #FF9900;
        font-weight: 600;
    }
    
    .position-badge {
        background: #f0f0f0;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .asin-badge {
        background: #E8F5E9;
        color: #2E7D32;
        padding: 4px 12px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: 600;
        font-family: monospace;
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
                    
                    <div id="resultsContent"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
$(document).ready(function() {
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
                    alert('Error: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr) {
                $('#loadingSpinner').hide();
                const errorMsg = xhr.responseJSON?.message || 'Failed to fetch search results';
                alert('Error: ' + errorMsg);
            }
        });
    }
    
    // Display results
    function displayResults(response) {
        const results = response.data || [];
        const totalResults = response.total_results || 0;
        const query = response.query || '';
        
        // Show stats
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
        } else {
            results.forEach(function(item) {
                const price = item.price ? `$${parseFloat(item.price).toFixed(2)}` : 'N/A';
                const rating = item.rating ? `${parseFloat(item.rating).toFixed(1)} ★` : 'N/A';
                const reviews = item.reviews ? `${item.reviews.toLocaleString()} reviews` : '';
                const image = item.image || 'https://via.placeholder.com/100';
                
                html += `
                    <div class="result-card">
                        <img src="${image}" alt="${item.title || 'Product'}" class="result-image">
                        <div class="result-content">
                            <div class="result-title">${item.title || 'No title'}</div>
                            <div class="result-meta">
                                <span class="asin-badge">${item.asin}</span>
                                <span class="position-badge">Position: #${item.position}</span>
                                <span class="meta-item price">${price}</span>
                                <span class="meta-item rating">${rating}</span>
                                ${reviews ? `<span class="meta-item">${reviews}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        $('#resultsContent').html(html);
        $('#resultsContainer').show();
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
    
    // Make loadHistoryQuery global
    window.loadHistoryQuery = function(query) {
        $('#searchQuery').val(query);
        performSearch(query, $('#marketplace').val());
    };
});
</script>
@endsection
